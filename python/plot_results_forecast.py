"""
Thesis Results figure: historical monthly admissions + 12-month SARIMA forecast.

Usage:
  python python/plot_results_forecast.py
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

import matplotlib.dates as mdates
import matplotlib.pyplot as plt
import pandas as pd

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(Path(__file__).resolve().parent))

from forecast_benchmark import (  # noqa: E402
    build_monthly_outlook,
    load_series,
    select_sarima_order,
)


def main() -> int:
    csv_path = ROOT / "database" / "data" / "admissions-2022-2024.csv"
    out_dir = ROOT / "public" / "figures"
    out_dir.mkdir(parents=True, exist_ok=True)
    out_path = out_dir / "sarima-12month-forecast.png"

    y = load_series(csv_path)
    selection = select_sarima_order(y)
    outlook = build_monthly_outlook(y, selection, months=12)
    order = outlook["model_order"]

    actual = pd.DataFrame(outlook["actual"])
    forecast = pd.DataFrame(outlook["forecast"])
    actual["date"] = pd.to_datetime(actual["month"] + "-01")
    forecast["date"] = pd.to_datetime(forecast["month"] + "-01")

    # Connect red line to the last actual point so the series meets
    bridge = actual.iloc[[-1]]
    forecast_plot = pd.concat([bridge, forecast], ignore_index=True)

    fig, ax = plt.subplots(figsize=(11, 6.2), dpi=140)
    ax.plot(
        actual["date"],
        actual["value"],
        color="#1f77b4",
        linewidth=2.2,
        label=f"Actual Data ({outlook['actual_start']} – {outlook['actual_end']})",
    )
    ax.plot(
        forecast_plot["date"],
        forecast_plot["value"],
        color="#d62728",
        linewidth=2.2,
        label="Predicted (Next 12 months)",
    )

    ax.set_title("Forecast: Dami ng Pasyente sa Hospital\nNorala District Hospital", fontsize=14, pad=12)
    ax.set_xlabel("Taon")
    ax.set_ylabel("Dami ng Pasyente\n(average daily admissions)")
    ax.grid(True, color="#c5c5c5", linewidth=0.8)
    ax.legend(loc="upper center", frameon=True, fancybox=False)
    ax.xaxis.set_major_locator(mdates.MonthLocator(interval=6))
    ax.xaxis.set_major_formatter(mdates.DateFormatter("%Y-%m"))
    ax.set_ylim(bottom=0)
    fig.autofmt_xdate(rotation=0, ha="center")
    fig.text(
        0.5,
        0.01,
        f"SARIMA{order}  ·  monthly averages of daily admissions and 12-month forecast",
        ha="center",
        fontsize=8,
        color="#555555",
    )
    fig.tight_layout(rect=(0, 0.04, 1, 1))
    fig.savefig(out_path, bbox_inches="tight", facecolor="white")
    plt.close(fig)

    json_path = out_dir / "monthly-outlook.json"
    json_path.write_text(json.dumps(outlook, indent=2), encoding="utf-8")

    print(f"Wrote {out_path}")
    print(f"Wrote {json_path}")
    print(f"Selected order: {order}")
    print(f"Actual: {outlook['actual_start']} to {outlook['actual_end']}")
    print(f"Forecast: {outlook['forecast_start']} to {outlook['forecast_end']}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
