"""
MEDCAST full research pipeline:
- trend / seasonality summary
- demand thresholds (low / moderate / high)
- compare Naive, Seasonal Naive, SARIMA, Prophet, Holt-Winters
- horizons: 1, 7, 30
- metrics: MAE, RMSE, MASE
- produce forecasts (+ PI where possible) up to 30 days
"""

from __future__ import annotations

import argparse
import hashlib
import json
import math
import uuid
import warnings
from pathlib import Path

import numpy as np
import pandas as pd
from statsmodels.tsa.holtwinters import ExponentialSmoothing
from statsmodels.tsa.statespace.sarimax import SARIMAX

warnings.filterwarnings("ignore")

try:
    from prophet import Prophet

    HAS_PROPHET = True
except Exception:
    HAS_PROPHET = False


MODELS = ["Naive", "SeasonalNaive", "SARIMA", "Prophet", "HoltWinters"]
HORIZONS = [1, 7, 30]


def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser()
    p.add_argument("--input", required=True)
    p.add_argument("--output", required=True)
    p.add_argument(
        "--holdout",
        type=int,
        default=None,
        help="Optional holdout days. Defaults to a 20%% chronological test split.",
    )
    p.add_argument("--season-length", type=int, default=7)
    return p.parse_args()


def load_series(path: Path) -> pd.Series:
    df = pd.read_csv(path)
    cols = {c.lower().strip(): c for c in df.columns}
    date_col = cols.get("date")
    adm_col = cols.get("admissions") or cols.get("total_admissions")
    df[date_col] = pd.to_datetime(df[date_col])
    df = df.sort_values(date_col).drop_duplicates(date_col, keep="last")
    y = pd.Series(df[adm_col].astype(float).values, index=df[date_col]).asfreq("D")
    if y.isna().any():
        y = y.interpolate(method="time").bfill().ffill()
    return y


def mae(a, p):
    return float(np.mean(np.abs(np.asarray(a) - np.asarray(p))))


def rmse(a, p):
    return float(np.sqrt(np.mean((np.asarray(a) - np.asarray(p)) ** 2)))


def mase(a, p, y_train, m=7):
    a = np.asarray(a, dtype=float)
    p = np.asarray(p, dtype=float)
    scale = np.mean(np.abs(y_train[m:].values - y_train[:-m].values))
    if scale < 1e-9:
        scale = 1.0
    return float(np.mean(np.abs(a - p)) / scale)


def safe_rate(numerator: float, denominator: float) -> float | None:
    if denominator <= 0:
        return None
    return float(numerator / denominator)


def pct(value: float | None) -> float | None:
    return None if value is None else round(value * 100.0, 2)


def benchmark_diagnostics(actual, rows, y_train, horizon: int, m: int = 7) -> dict:
    """Compute interval, high-demand classification, and robustness diagnostics."""
    all_actual = np.asarray(actual, dtype=float)
    all_point = np.asarray([r["point_forecast"] for r in rows], dtype=float)
    all_n = min(len(all_actual), len(all_point))
    all_actual = all_actual[:all_n]
    all_point = all_point[:all_n]

    actual = all_actual[:horizon]
    rows = rows[:horizon]
    point = np.asarray([r["point_forecast"] for r in rows], dtype=float)
    low80 = np.asarray([r["pi80_low"] for r in rows], dtype=float)
    high80 = np.asarray([r["pi80_high"] for r in rows], dtype=float)
    low95 = np.asarray([r["pi95_low"] for r in rows], dtype=float)
    high95 = np.asarray([r["pi95_high"] for r in rows], dtype=float)

    width80 = high80 - low80
    width95 = high95 - low95
    actual_mean = max(float(np.mean(np.abs(actual))), 1e-9)

    high_threshold = float(np.percentile(y_train.values, 66))
    actual_high = actual > high_threshold
    predicted_high = point > high_threshold
    tp = int(np.sum(actual_high & predicted_high))
    tn = int(np.sum(~actual_high & ~predicted_high))
    fp = int(np.sum(~actual_high & predicted_high))
    fn = int(np.sum(actual_high & ~predicted_high))

    sensitivity = safe_rate(tp, tp + fn)
    specificity = safe_rate(tn, tn + fp)
    precision = safe_rate(tp, tp + fp)
    f1 = (
        safe_rate(2 * precision * sensitivity, precision + sensitivity)
        if precision is not None and sensitivity is not None
        else None
    )

    absolute_errors = np.abs(actual - point)
    rolling_errors = np.abs(all_actual - all_point)
    rolling_window = max(1, min(m, horizon, len(rolling_errors)))
    rolling_mae = np.convolve(
        rolling_errors,
        np.ones(rolling_window) / rolling_window,
        mode="valid",
    )
    rolling_mean = float(np.mean(rolling_mae))
    rolling_std = float(np.std(rolling_mae, ddof=1)) if len(rolling_mae) > 1 else 0.0
    rolling_cv = rolling_std / max(rolling_mean, 1e-9)
    robustness_score = max(0.0, 100.0 * (1.0 - min(1.0, rolling_cv)))

    high_errors = absolute_errors[actual_high]
    p99 = float(np.percentile(y_train.values, 99))
    winsorized_mae = mae(np.minimum(actual, p99), np.minimum(point, p99))
    baseline_mae = mae(actual, point)

    threshold_variants = {}
    for quantile in (60, 66, 75):
        threshold = float(np.percentile(y_train.values, quantile))
        truth = actual > threshold
        predicted = point > threshold
        vtp = int(np.sum(truth & predicted))
        vtn = int(np.sum(~truth & ~predicted))
        vfp = int(np.sum(~truth & predicted))
        vfn = int(np.sum(truth & ~predicted))
        vsensitivity = safe_rate(vtp, vtp + vfn)
        vprecision = safe_rate(vtp, vtp + vfp)
        vf1 = (
            safe_rate(2 * vprecision * vsensitivity, vprecision + vsensitivity)
            if vprecision is not None and vsensitivity is not None
            else None
        )
        threshold_variants[f"p{quantile}"] = {
            "threshold": round(threshold, 2),
            "sensitivity": pct(vsensitivity),
            "specificity": pct(safe_rate(vtn, vtn + vfp)),
            "precision": pct(vprecision),
            "f1_score": pct(vf1),
        }

    penalty_variants = {
        "balanced": {"missed_event_weight": 1.0, "false_alert_weight": 1.0},
        "overload_sensitive": {"missed_event_weight": 2.0, "false_alert_weight": 0.5},
        "resource_conservative": {"missed_event_weight": 0.75, "false_alert_weight": 2.0},
    }
    for settings in penalty_variants.values():
        weighted_errors = (
            fn * settings["missed_event_weight"] + fp * settings["false_alert_weight"]
        )
        settings["weighted_error_rate"] = round(weighted_errors / max(1, len(actual)) * 100.0, 2)

    capacity_variants = {}
    for quantile in (50, 66, 90):
        capacity = float(np.percentile(y_train.values, quantile))
        capacity_variants[f"p{quantile}"] = {
            "capacity": round(capacity, 2),
            "actual_exceedance_days": int(np.sum(actual > capacity)),
            "predicted_exceedance_days": int(np.sum(point > capacity)),
            "forecast_overload": round(float(np.maximum(point - capacity, 0).sum()), 2),
        }

    return {
        "coverage_80": pct(float(np.mean((actual >= low80) & (actual <= high80)))),
        "coverage_95": pct(float(np.mean((actual >= low95) & (actual <= high95)))),
        "avg_width_80": round(float(np.mean(width80)), 4),
        "avg_width_95": round(float(np.mean(width95)), 4),
        "relative_width_80": round(float(np.mean(width80)) / actual_mean * 100.0, 2),
        "relative_width_95": round(float(np.mean(width95)) / actual_mean * 100.0, 2),
        "high_demand_mae": round(float(np.mean(high_errors)), 4) if len(high_errors) else None,
        "high_demand_days": int(np.sum(actual_high)),
        "high_demand_threshold": round(high_threshold, 2),
        "sensitivity": pct(sensitivity),
        "specificity": pct(specificity),
        "precision": pct(precision),
        "f1_score": pct(f1),
        "false_alert_rate": pct(safe_rate(fp, fp + tn)),
        "missed_event_rate": pct(safe_rate(fn, fn + tp)),
        "rolling_mae_mean": round(rolling_mean, 4),
        "rolling_mae_std": round(rolling_std, 4),
        "robustness_score": round(robustness_score, 2),
        "confusion_matrix": {"tp": tp, "tn": tn, "fp": fp, "fn": fn},
        "sensitivity_analysis": {
            "capacity": capacity_variants,
            "threshold": threshold_variants,
            "penalty": penalty_variants,
            "outlier": {
                "winsorization_cap_p99": round(p99, 2),
                "baseline_mae": round(baseline_mae, 4),
                "winsorized_mae": round(winsorized_mae, 4),
                "mae_change_percent": round(
                    (winsorized_mae - baseline_mae) / max(baseline_mae, 1e-9) * 100.0,
                    2,
                ),
            },
        },
    }


def clip_nonneg(x):
    return np.maximum(np.asarray(x, dtype=float), 0.0)


def residual_intervals(point, resid_std, steps):
    # Widen slightly with horizon
    out = []
    for i, mu in enumerate(point, start=1):
        z80, z95 = 1.2816, 1.96
        s = resid_std * math.sqrt(i)
        out.append(
            {
                "point_forecast": round(float(mu), 2),
                "pi80_low": round(float(max(0, mu - z80 * s)), 2),
                "pi80_high": round(float(mu + z80 * s), 2),
                "pi95_low": round(float(max(0, mu - z95 * s)), 2),
                "pi95_high": round(float(mu + z95 * s), 2),
            }
        )
    return out


def forecast_naive(y: pd.Series, steps: int):
    last = float(y.iloc[-1])
    point = np.repeat(last, steps)
    # residual from one-step naive
    resid = y.diff().dropna()
    std = float(resid.std(ddof=1) or 1.0)
    return point, std


def forecast_seasonal_naive(y: pd.Series, steps: int, m=7):
    hist = y.values
    point = []
    for i in range(1, steps + 1):
        point.append(float(hist[-m + ((i - 1) % m)]))
    point = np.asarray(point)
    resid = y.diff(m).dropna()
    std = float(resid.std(ddof=1) or 1.0)
    return point, std


# Candidate SARIMA orders for AIC/BIC selection (weekly seasonality m=7)
SARIMA_ORDER_CANDIDATES = [
    ((0, 1, 1), (0, 1, 1, 7)),
    ((0, 1, 1), (1, 1, 0, 7)),
    ((0, 1, 1), (1, 1, 1, 7)),
    ((1, 1, 0), (0, 1, 1, 7)),
    ((1, 1, 0), (1, 1, 0, 7)),
    ((1, 1, 0), (1, 1, 1, 7)),
    ((1, 1, 1), (0, 1, 1, 7)),
    ((1, 1, 1), (1, 1, 0, 7)),
    ((1, 1, 1), (1, 1, 1, 7)),
    ((2, 1, 1), (1, 1, 1, 7)),
    ((1, 1, 2), (1, 1, 1, 7)),
]


def fit_sarima_model(y: pd.Series, order, seasonal_order):
    model = SARIMAX(
        y,
        order=order,
        seasonal_order=seasonal_order,
        enforce_stationarity=False,
        enforce_invertibility=False,
    )
    return model.fit(disp=False)


def select_sarima_order(y: pd.Series) -> dict:
    """
    Compare candidate SARIMA orders using AIC (primary) and BIC.
    Returns selected order plus full comparison table for thesis Results.
    """
    rows = []
    best = None
    for order, seasonal_order in SARIMA_ORDER_CANDIDATES:
        label = (
            f"({order[0]},{order[1]},{order[2]})"
            f"({seasonal_order[0]},{seasonal_order[1]},{seasonal_order[2]}){seasonal_order[3]}"
        )
        try:
            res = fit_sarima_model(y, order, seasonal_order)
            row = {
                "model_order": label,
                "order": list(order),
                "seasonal_order": list(seasonal_order),
                "aic": round(float(res.aic), 3),
                "bic": round(float(res.bic), 3),
                "hqic": round(float(res.hqic), 3) if res.hqic is not None else None,
            }
            rows.append(row)
            if best is None or row["aic"] < best["aic"]:
                best = {**row, "result": res}
        except Exception as e:
            rows.append(
                {
                    "model_order": label,
                    "order": list(order),
                    "seasonal_order": list(seasonal_order),
                    "aic": None,
                    "bic": None,
                    "hqic": None,
                    "error": str(e),
                }
            )

    if best is None:
        # Fallback if all candidates fail
        order, seasonal_order = (1, 1, 1), (1, 1, 1, 7)
        res = fit_sarima_model(y, order, seasonal_order)
        label = "(1,1,1)(1,1,1)7"
        best = {
            "model_order": label,
            "order": list(order),
            "seasonal_order": list(seasonal_order),
            "aic": round(float(res.aic), 3),
            "bic": round(float(res.bic), 3),
            "hqic": round(float(res.hqic), 3) if res.hqic is not None else None,
            "result": res,
        }

    rows_sorted = sorted(
        [r for r in rows if r.get("aic") is not None],
        key=lambda r: r["aic"],
    )
    return {
        "selection_criterion": "AIC (primary), BIC reported for comparison",
        "selected": {
            "model_order": best["model_order"],
            "order": best["order"],
            "seasonal_order": best["seasonal_order"],
            "aic": best["aic"],
            "bic": best["bic"],
            "hqic": best.get("hqic"),
        },
        "candidates": rows_sorted,
        "fitted_result": best["result"],
    }


def forecast_sarima(y: pd.Series, steps: int, selection: dict | None = None):
    if selection is None:
        selection = select_sarima_order(y)
        res = selection["fitted_result"]
    else:
        # Reuse fitted result when available; otherwise refit selected order
        res = selection.get("fitted_result")
        if res is None:
            order = tuple(selection["selected"]["order"])
            seasonal_order = tuple(selection["selected"]["seasonal_order"])
            res = fit_sarima_model(y, order, seasonal_order)

    pred = res.get_forecast(steps=steps)
    mean = clip_nonneg(pred.predicted_mean.values)
    ci80 = pred.conf_int(alpha=0.20)
    ci95 = pred.conf_int(alpha=0.05)
    rows = []
    for i in range(steps):
        rows.append(
            {
                "point_forecast": round(float(mean[i]), 2),
                "pi80_low": round(float(max(0, ci80.iloc[i, 0])), 2),
                "pi80_high": round(float(max(0, ci80.iloc[i, 1])), 2),
                "pi95_low": round(float(max(0, ci95.iloc[i, 0])), 2),
                "pi95_high": round(float(max(0, ci95.iloc[i, 1])), 2),
            }
        )
    return rows, selection


def build_monthly_outlook(y: pd.Series, selection: dict, months: int = 12) -> dict:
    """
    Monthly average daily admissions: historical actuals + 12-month SARIMA forecast.
    Used for the Results & Discussions figure (actual vs predicted).
    """
    monthly_actual = y.resample("MS").mean()
    res = selection.get("fitted_result")
    if res is None:
        order = tuple(selection["selected"]["order"])
        seasonal_order = tuple(selection["selected"]["seasonal_order"])
        res = fit_sarima_model(y, order, seasonal_order)

    steps = 400
    pred = res.get_forecast(steps=steps)
    mean = clip_nonneg(pred.predicted_mean.values)
    start = y.index.max() + pd.Timedelta(days=1)
    fc = pd.Series(mean, index=pd.date_range(start, periods=steps, freq="D"))
    monthly_fc = fc.resample("MS").mean()
    # Skip a partial first forecast month so plotted months are complete
    if start.day != 1 and len(monthly_fc) > 1:
        monthly_fc = monthly_fc.iloc[1:]
    monthly_fc = monthly_fc.iloc[:months]

    actual = [
        {"month": d.strftime("%Y-%m"), "value": round(float(v), 2)}
        for d, v in monthly_actual.items()
        if pd.notna(v)
    ]
    forecast = [
        {"month": d.strftime("%Y-%m"), "value": round(float(v), 2)}
        for d, v in monthly_fc.items()
        if pd.notna(v)
    ]
    return {
        "unit": "average daily admissions",
        "model_order": selection["selected"]["model_order"],
        "actual_start": actual[0]["month"] if actual else None,
        "actual_end": actual[-1]["month"] if actual else None,
        "forecast_start": forecast[0]["month"] if forecast else None,
        "forecast_end": forecast[-1]["month"] if forecast else None,
        "actual": actual,
        "forecast": forecast,
    }


def forecast_holtwinters(y: pd.Series, steps: int):
    model = ExponentialSmoothing(
        y,
        trend="add",
        seasonal="add",
        seasonal_periods=7,
        initialization_method="estimated",
    )
    res = model.fit(optimized=True)
    point = clip_nonneg(res.forecast(steps).values)
    fitted = res.fittedvalues
    resid = (y - fitted).dropna()
    std = float(resid.std(ddof=1) or 1.0)
    return residual_intervals(point, std, steps)


def forecast_prophet(y: pd.Series, steps: int):
    if not HAS_PROPHET:
        # Fourier weekly seasonal OLS fallback if prophet unavailable
        df = pd.DataFrame({"y": y.values, "t": np.arange(len(y), dtype=float)}, index=y.index)
        for k in (1, 2):
            df[f"s{k}"] = np.sin(2 * math.pi * k * df["t"] / 7.0)
            df[f"c{k}"] = np.cos(2 * math.pi * k * df["t"] / 7.0)
        X = np.column_stack([np.ones(len(df)), df["t"], df["s1"], df["c1"], df["s2"], df["c2"]])
        beta, *_ = np.linalg.lstsq(X, df["y"].values, rcond=None)
        future_t = np.arange(len(y), len(y) + steps, dtype=float)
        Xf = np.column_stack(
            [
                np.ones(steps),
                future_t,
                np.sin(2 * math.pi * 1 * future_t / 7.0),
                np.cos(2 * math.pi * 1 * future_t / 7.0),
                np.sin(2 * math.pi * 2 * future_t / 7.0),
                np.cos(2 * math.pi * 2 * future_t / 7.0),
            ]
        )
        point = clip_nonneg(Xf @ beta)
        resid = df["y"].values - (X @ beta)
        std = float(np.std(resid, ddof=1) or 1.0)
        return residual_intervals(point, std, steps)

    df = pd.DataFrame({"ds": y.index, "y": y.values})
    m = Prophet(
        daily_seasonality=False,
        weekly_seasonality=True,
        yearly_seasonality=True,
        interval_width=0.8,
    )
    m.fit(df)
    future = m.make_future_dataframe(periods=steps, include_history=False)
    fc80 = m.predict(future)
    m.interval_width = 0.95
    # re-predict uncertainty with same model params is awkward; approximate 95 from 80 using z-ratio
    rows = []
    for i in range(steps):
        mu = float(max(0, fc80.loc[i, "yhat"]))
        lo80 = float(max(0, fc80.loc[i, "yhat_lower"]))
        hi80 = float(max(0, fc80.loc[i, "yhat_upper"]))
        # expand 80% half-width to approx 95%
        half80 = (hi80 - lo80) / 2.0
        half95 = half80 * (1.96 / 1.2816)
        rows.append(
            {
                "point_forecast": round(mu, 2),
                "pi80_low": round(lo80, 2),
                "pi80_high": round(hi80, 2),
                "pi95_low": round(max(0, mu - half95), 2),
                "pi95_high": round(mu + half95, 2),
            }
        )
    return rows


def forecast_model(name: str, y: pd.Series, steps: int, sarima_selection: dict | None = None):
    if name == "Naive":
        point, std = forecast_naive(y, steps)
        return residual_intervals(point, std, steps), None
    if name == "SeasonalNaive":
        point, std = forecast_seasonal_naive(y, steps)
        return residual_intervals(point, std, steps), None
    if name == "SARIMA":
        rows, selection = forecast_sarima(y, steps, selection=sarima_selection)
        # Drop non-serializable fitted result before returning metadata
        meta = {
            "selection_criterion": selection["selection_criterion"],
            "selected": selection["selected"],
            "candidates": selection["candidates"],
        }
        return rows, meta
    if name == "HoltWinters":
        return forecast_holtwinters(y, steps), None
    if name == "Prophet":
        return forecast_prophet(y, steps), None
    raise ValueError(name)


def compute_trends(y: pd.Series) -> dict:
    by_weekday = (
        y.groupby(y.index.day_name())
        .mean()
        .reindex(["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"])
    )
    monthly = y.groupby(y.index.to_period("M")).mean()
    # overall linear trend slope (admissions per day)
    t = np.arange(len(y), dtype=float)
    slope = float(np.polyfit(t, y.values, 1)[0])
    overall = {
        "mean": round(float(y.mean()), 3),
        "min": round(float(y.min()), 3),
        "max": round(float(y.max()), 3),
        "std": round(float(y.std(ddof=1)), 3),
        "slope_per_day": round(slope, 5),
        "slope_per_month": round(slope * 30.0, 4),
        "direction": "increasing" if slope > 0.01 else ("decreasing" if slope < -0.01 else "stable"),
        "n_days": int(len(y)),
        "start": y.index.min().date().isoformat(),
        "end": y.index.max().date().isoformat(),
    }
    return {
        "weekday": {k: round(float(v), 3) for k, v in by_weekday.items() if pd.notna(v)},
        "monthly": {str(k): round(float(v), 3) for k, v in monthly.items()},
        "overall": overall,
    }


def compute_thresholds(y: pd.Series) -> dict:
    # Low <= P33, Moderate <= P66, High > P66
    p33 = float(np.percentile(y.values, 33))
    p66 = float(np.percentile(y.values, 66))
    return {
        "low_max": round(p33, 2),
        "moderate_max": round(p66, 2),
        "high_min": round(p66, 2),
        "method": "percentile_33_66",
        "meta": {
            "p33": round(p33, 2),
            "p66": round(p66, 2),
            "p50": round(float(np.percentile(y.values, 50)), 2),
            "rule": "low <= P33; P33 < moderate <= P66; high > P66",
        },
    }


def classify_demand(value: float, thr: dict) -> str:
    if value <= thr["low_max"]:
        return "Low"
    if value <= thr["moderate_max"]:
        return "Moderate"
    return "High"


def main() -> int:
    args = parse_args()
    y = load_series(Path(args.input))
    requested_holdout = args.holdout if args.holdout is not None else round(len(y) * 0.20)
    holdout = max(1, min(int(requested_holdout), len(y) - 60))

    train = y.iloc[:-holdout]
    test = y.iloc[-holdout:]
    steps = holdout

    trends = compute_trends(y)
    thresholds = compute_thresholds(y)
    dataset_fingerprint = hashlib.sha256(y.to_csv().encode("utf-8")).hexdigest()[:12]

    benchmarks = []
    forecasts = {}
    model_errors = {}
    sarima_selection_meta = None

    # Select SARIMA order once on full series using AIC/BIC (for reporting + live forecast)
    full_sarima_selection = select_sarima_order(y)
    sarima_selection_meta = {
        "selection_criterion": full_sarima_selection["selection_criterion"],
        "selected": full_sarima_selection["selected"],
        "candidates": full_sarima_selection["candidates"],
    }

    for model_name in MODELS:
        try:
            # evaluation on holdout using train-only fit
            # For SARIMA evaluation, re-select on train only (honest holdout)
            train_sel = select_sarima_order(train) if model_name == "SARIMA" else None
            ev_rows, _meta = forecast_model(model_name, train, steps, sarima_selection=train_sel)
            pred = np.array([r["point_forecast"] for r in ev_rows], dtype=float)
            actual = test.values.astype(float)
            # align lengths
            n = min(len(pred), len(actual))
            pred = pred[:n]
            actual = actual[:n]

            for h in HORIZONS:
                if h > n:
                    continue
                diagnostics = benchmark_diagnostics(
                    actual,
                    ev_rows,
                    train,
                    h,
                    m=args.season_length,
                )
                benchmarks.append(
                    {
                        "model_name": model_name,
                        "horizon_days": h,
                        "mae": round(mae(actual[:h], pred[:h]), 4),
                        "rmse": round(rmse(actual[:h], pred[:h]), 4),
                        "mase": round(mase(actual[:h], pred[:h], train, m=args.season_length), 4),
                        **diagnostics,
                    }
                )

            # live forecast on full history (30 days)
            live_rows, _meta = forecast_model(
                model_name,
                y,
                30,
                sarima_selection=full_sarima_selection if model_name == "SARIMA" else None,
            )
            start = y.index.max() + pd.Timedelta(days=1)
            points = []
            for i, row in enumerate(live_rows):
                dt = (start + pd.Timedelta(days=i)).date().isoformat()
                level = classify_demand(row["point_forecast"], thresholds)
                points.append({"forecast_date": dt, "demand_level": level, **row})
            forecasts[model_name] = points
        except Exception as e:
            model_errors[model_name] = str(e)

    # mark best model per horizon by MASE then MAE
    best = {}
    for h in HORIZONS:
        subset = [b for b in benchmarks if b["horizon_days"] == h]
        if not subset:
            continue
        subset = sorted(subset, key=lambda b: (b["mase"], b["mae"], b["rmse"]))
        best[str(h)] = subset[0]["model_name"]
        for b in benchmarks:
            if b["horizon_days"] == h:
                b["is_best_for_horizon"] = b["model_name"] == best[str(h)]

    primary_model = best.get("7") or best.get("1") or "SARIMA"
    selected_order = sarima_selection_meta["selected"]["model_order"]
    monthly_outlook = build_monthly_outlook(y, full_sarima_selection, months=12)

    payload = {
        "batch_id": uuid.uuid4().hex,
        "dataset_version": f"MEDCAST-{dataset_fingerprint}",
        "dataset_records": int(len(y)),
        "dataset_coverage_start": y.index.min().date().isoformat(),
        "dataset_coverage_end": y.index.max().date().isoformat(),
        "training_records": int(len(train)),
        "testing_records": int(len(test)),
        "training_percent": round(len(train) / len(y) * 100.0, 2),
        "testing_percent": round(len(test) / len(y) * 100.0, 2),
        "split_method": "chronological_80_20",
        "prophet_backend": "prophet" if HAS_PROPHET else "fourier_fallback",
        "train_start_date": y.index.min().date().isoformat(),
        "train_end_date": y.index.max().date().isoformat(),
        "holdout_days": holdout,
        "trends": trends,
        "thresholds": thresholds,
        "benchmarks": benchmarks,
        "best_model_by_horizon": best,
        "primary_model": primary_model,
        "forecasts": forecasts,
        "model_errors": model_errors,
        "sarima_order_selection": sarima_selection_meta,
        "selected_sarima_order": selected_order,
        "monthly_outlook": monthly_outlook,
    }

    out = Path(args.output)
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(payload, indent=2), encoding="utf-8")
    print(
        json.dumps(
            {
                "ok": True,
                "output": str(out),
                "primary_model": primary_model,
                "best": best,
                "selected_sarima_order": selected_order,
                "selected_aic": sarima_selection_meta["selected"]["aic"],
                "selected_bic": sarima_selection_meta["selected"]["bic"],
            }
        )
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
