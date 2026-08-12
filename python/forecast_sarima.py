"""
MEDCAST SARIMA forecast runner.

Reads a daily admissions CSV and writes a JSON result for Laravel to import.

CSV columns required: date, admissions
"""

from __future__ import annotations

import argparse
import json
import math
import sys
from pathlib import Path

import numpy as np
import pandas as pd
from statsmodels.tsa.statespace.sarimax import SARIMAX


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Run SARIMA admission forecast for MEDCAST")
    parser.add_argument("--input", required=True, help="Path to admissions CSV (date,admissions)")
    parser.add_argument("--output", required=True, help="Path to write JSON result")
    parser.add_argument("--horizon", type=int, default=7, help="Forecast horizon in days")
    parser.add_argument("--order", default="1,1,1", help="Non-seasonal order p,d,q")
    parser.add_argument("--seasonal-order", default="1,1,1,7", help="Seasonal order P,D,Q,m")
    parser.add_argument("--holdout", type=int, default=30, help="Holdout days for evaluation")
    return parser.parse_args()


def parse_order(text: str) -> tuple[int, ...]:
    parts = [int(x.strip()) for x in text.split(",")]
    return tuple(parts)


def load_series(path: Path) -> pd.Series:
    df = pd.read_csv(path)
    cols = {c.lower().strip(): c for c in df.columns}
    date_col = cols.get("date")
    adm_col = cols.get("admissions") or cols.get("total_admissions") or cols.get("daily admissions")
    if not date_col or not adm_col:
        raise ValueError("CSV must include date and admissions columns")

    df[date_col] = pd.to_datetime(df[date_col])
    df = df.sort_values(date_col).drop_duplicates(date_col, keep="last")
    series = pd.Series(df[adm_col].astype(float).values, index=df[date_col])
    series = series.asfreq("D")
    # Fill rare gaps with interpolation then round-ish for count data
    if series.isna().any():
        series = series.interpolate(method="time").bfill().ffill()
    return series


def fit_sarima(y: pd.Series, order: tuple[int, ...], seasonal_order: tuple[int, ...]):
    model = SARIMAX(
        y,
        order=order,
        seasonal_order=seasonal_order,
        enforce_stationarity=False,
        enforce_invertibility=False,
    )
    return model.fit(disp=False)


def forecast_frame(result, steps: int) -> pd.DataFrame:
    pred = result.get_forecast(steps=steps)
    # 80% and 95% prediction intervals
    mean = pred.predicted_mean
    ci80 = pred.conf_int(alpha=0.20)
    ci95 = pred.conf_int(alpha=0.05)

    # statsmodels labels columns like lower y / upper y
    low80 = ci80.iloc[:, 0]
    high80 = ci80.iloc[:, 1]
    low95 = ci95.iloc[:, 0]
    high95 = ci95.iloc[:, 1]

    out = pd.DataFrame(
        {
            "forecast_date": mean.index,
            "point_forecast": mean.values,
            "pi80_low": low80.values,
            "pi80_high": high80.values,
            "pi95_low": low95.values,
            "pi95_high": high95.values,
        }
    )
    # Admissions can't be negative
    for col in ["point_forecast", "pi80_low", "pi80_high", "pi95_low", "pi95_high"]:
        out[col] = out[col].clip(lower=0)
    return out


def metrics(actual: np.ndarray, predicted: np.ndarray) -> dict:
    actual = np.asarray(actual, dtype=float)
    predicted = np.asarray(predicted, dtype=float)
    err = actual - predicted
    mae = float(np.mean(np.abs(err)))
    rmse = float(np.sqrt(np.mean(err**2)))
    mape = float(np.mean(np.abs(err) / np.maximum(np.abs(actual), 1e-6)) * 100)
    ss_res = float(np.sum(err**2))
    ss_tot = float(np.sum((actual - np.mean(actual)) ** 2))
    r2 = float(1 - ss_res / ss_tot) if ss_tot > 0 else None
    return {
        "mae": round(mae, 3),
        "rmse": round(rmse, 3),
        "mape": round(mape, 3),
        "r_squared": None if r2 is None or math.isnan(r2) else round(r2, 4),
    }


def coverage(actual: np.ndarray, low: np.ndarray, high: np.ndarray) -> float:
    inside = (actual >= low) & (actual <= high)
    return round(float(np.mean(inside) * 100), 2)


def status_from_mape(mape: float) -> str:
    if mape <= 12:
        return "Good"
    if mape <= 20:
        return "Fair"
    return "Poor"


def main() -> int:
    args = parse_args()
    order = parse_order(args.order)
    seasonal_order = parse_order(args.seasonal_order)
    if len(order) != 3 or len(seasonal_order) != 4:
        print("Invalid order. Use p,d,q and P,D,Q,m", file=sys.stderr)
        return 1

    series = load_series(Path(args.input))
    if len(series) < max(60, seasonal_order[-1] * 8):
        print("Not enough history to fit seasonal SARIMA reliably.", file=sys.stderr)
        return 1

    holdout = min(args.holdout, max(7, len(series) // 5))
    train = series.iloc[:-holdout]
    test = series.iloc[-holdout:]

    # Fit on train for evaluation
    train_result = fit_sarima(train, order, seasonal_order)
    test_fc = forecast_frame(train_result, steps=len(test))
    test_fc.index = test.index

    eval_metrics = metrics(test.values, test_fc["point_forecast"].values)
    cov80 = coverage(test.values, test_fc["pi80_low"].values, test_fc["pi80_high"].values)
    cov95 = coverage(test.values, test_fc["pi95_low"].values, test_fc["pi95_high"].values)

    # Refit on full series for the live forecast
    full_result = fit_sarima(series, order, seasonal_order)
    live = forecast_frame(full_result, steps=args.horizon)

    payload = {
        "model_name": "SARIMA",
        "model_order": f"({order[0]},{order[1]},{order[2]})({seasonal_order[0]},{seasonal_order[1]},{seasonal_order[2]}){seasonal_order[3]}",
        "model_params": {
            "p": order[0],
            "d": order[1],
            "q": order[2],
            "P": seasonal_order[0],
            "D": seasonal_order[1],
            "Q": seasonal_order[2],
            "m": seasonal_order[3],
            "aic": float(full_result.aic) if full_result.aic is not None else None,
            "bic": float(full_result.bic) if full_result.bic is not None else None,
            "holdout_days": holdout,
            "n_obs": int(len(series)),
        },
        "train_start_date": series.index.min().date().isoformat(),
        "train_end_date": series.index.max().date().isoformat(),
        "horizon_days": args.horizon,
        "points": [
            {
                "forecast_date": row.forecast_date.date().isoformat()
                if hasattr(row.forecast_date, "date")
                else str(row.forecast_date)[:10],
                "point_forecast": round(float(row.point_forecast), 2),
                "pi80_low": round(float(row.pi80_low), 2),
                "pi80_high": round(float(row.pi80_high), 2),
                "pi95_low": round(float(row.pi95_low), 2),
                "pi95_high": round(float(row.pi95_high), 2),
            }
            for row in live.itertuples(index=False)
        ],
        "evaluation": {
            "period_label": f"Holdout last {holdout} days",
            "period_start": test.index.min().date().isoformat(),
            "period_end": test.index.max().date().isoformat(),
            "mae": eval_metrics["mae"],
            "rmse": eval_metrics["rmse"],
            "mape": eval_metrics["mape"],
            "r_squared": eval_metrics["r_squared"],
            "coverage_80": cov80,
            "coverage_95": cov95,
            "status": status_from_mape(eval_metrics["mape"]),
        },
    }

    out_path = Path(args.output)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(json.dumps(payload, indent=2), encoding="utf-8")
    print(json.dumps({"ok": True, "output": str(out_path), "aic": payload["model_params"]["aic"]}))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
