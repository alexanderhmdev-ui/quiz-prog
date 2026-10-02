from __future__ import annotations

from pathlib import Path
from typing import Any, Dict
import joblib
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score
from sklearn.model_selection import train_test_split

BASE_DIR = Path(__file__).resolve().parent
DATASET = BASE_DIR / "dataset.csv"
MODEL_FILE = BASE_DIR / "difficulty_model.joblib"
FEATURES = ["accuracy", "avg_response_time", "streak", "rounds"]

class DifficultyModel:
    def __init__(self) -> None:
        self.model: RandomForestClassifier | None = None
        self.validation_accuracy: float | None = None
        self.train_rows = 0
        self.load_or_train()

    def load_or_train(self) -> None:
        if MODEL_FILE.exists():
            try:
                bundle = joblib.load(MODEL_FILE)
                self.model = bundle["model"]
                self.validation_accuracy = bundle.get("validation_accuracy")
                self.train_rows = bundle.get("train_rows", 0)
                return
            except Exception:
                pass
        self.train()

    def train(self) -> Dict[str, Any]:
        df = pd.read_csv(DATASET)
        missing = [c for c in FEATURES + ["difficulty"] if c not in df.columns]
        if missing:
            raise ValueError(f"Faltan columnas en dataset.csv: {missing}")

        X = df[FEATURES]
        y = df["difficulty"]
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.25, random_state=42, stratify=y
        )
        model = RandomForestClassifier(
            n_estimators=160,
            max_depth=6,
            min_samples_leaf=1,
            random_state=42,
            class_weight="balanced",
        )
        model.fit(X_train, y_train)
        predicted = model.predict(X_test)
        self.model = model
        self.validation_accuracy = float(accuracy_score(y_test, predicted))
        self.train_rows = len(df)
        joblib.dump({
            "model": model,
            "validation_accuracy": self.validation_accuracy,
            "train_rows": self.train_rows,
        }, MODEL_FILE)
        return self.info()

    def predict(self, payload: Dict[str, Any]) -> Dict[str, Any]:
        if self.model is None:
            self.train()

        accuracy = self._clamp(float(payload.get("accuracy", 0.5)), 0.0, 1.0)
        avg_time = self._clamp(float(payload.get("avg_response_time", 8.0)), 0.2, 60.0)
        streak = max(0, int(payload.get("streak", 0)))
        rounds = max(1, int(payload.get("rounds", 1)))

        row = pd.DataFrame([[accuracy, avg_time, streak, rounds]], columns=FEATURES)
        difficulty = str(self.model.predict(row)[0])
        probabilities = self.model.predict_proba(row)[0]
        confidence = float(max(probabilities))

        # Protección pedagógica: evita saltos muy agresivos con muy pocas respuestas.
        if rounds < 3:
            current = str(payload.get("difficulty", "medium"))
            if current in {"easy", "medium", "hard"}:
                difficulty = current
                confidence = min(confidence, 0.70)

        return {
            "difficulty": difficulty,
            "confidence": round(confidence, 4),
            "source": "random_forest",
            "features": {
                "accuracy": accuracy,
                "avg_response_time": avg_time,
                "streak": streak,
                "rounds": rounds,
            },
        }

    def info(self) -> Dict[str, Any]:
        return {
            "algorithm": "RandomForestClassifier",
            "features": FEATURES,
            "train_rows": self.train_rows,
            "validation_accuracy": None if self.validation_accuracy is None else round(self.validation_accuracy, 4),
            "labels": ["easy", "medium", "hard"],
        }

    @staticmethod
    def _clamp(value: float, low: float, high: float) -> float:
        return max(low, min(high, value))
