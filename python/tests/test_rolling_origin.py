from __future__ import annotations

import sys
import unittest
from pathlib import Path
from unittest.mock import patch

import pandas as pd

PYTHON_DIR = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PYTHON_DIR))

import forecast_benchmark as benchmark  # noqa: E402


class RollingOriginEvaluationTest(unittest.TestCase):
    def test_expands_history_and_collects_each_eligible_horizon(self) -> None:
        index = pd.date_range("2024-01-01", periods=20, freq="D")
        series = pd.Series(range(20), index=index, dtype=float)
        fitted_lengths: list[int] = []

        def fake_forecast_model(name, history, steps, sarima_selection=None):
            fitted_lengths.append(len(history))
            rows = [
                {
                    "point_forecast": float(history.iloc[-1]),
                    "pi80_low": float(history.iloc[-1] - 1),
                    "pi80_high": float(history.iloc[-1] + 1),
                    "pi95_low": float(history.iloc[-1] - 2),
                    "pi95_high": float(history.iloc[-1] + 2),
                }
                for _ in range(steps)
            ]
            return rows, None

        with patch.object(benchmark, "forecast_model", side_effect=fake_forecast_model):
            result = benchmark.rolling_origin_evaluation(
                "Naive",
                series,
                initial_train_size=12,
                horizons=[1, 3],
            )

        self.assertEqual(fitted_lengths, list(range(12, 20)))
        self.assertEqual(len(result[1]["actual"]), 8)
        self.assertEqual(len(result[3]["actual"]), 6)
        self.assertEqual(result[1]["origin_dates"][0], "2024-01-12")
        self.assertEqual(result[1]["target_dates"][0], "2024-01-13")
        self.assertEqual(result[3]["target_dates"][-1], "2024-01-20")
        self.assertEqual(result[1]["rows"][0]["point_forecast"], 11.0)

    def test_sarima_selection_reuses_order_but_not_fitted_result(self) -> None:
        selection = {
            "selection_criterion": "AIC",
            "selected": {"order": [1, 1, 0], "seasonal_order": [0, 1, 1, 7]},
            "candidates": [],
            "fitted_result": object(),
        }

        reusable = benchmark.reusable_sarima_selection(selection)

        self.assertNotIn("fitted_result", reusable)
        self.assertEqual(reusable["selected"]["order"], [1, 1, 0])


if __name__ == "__main__":
    unittest.main()
