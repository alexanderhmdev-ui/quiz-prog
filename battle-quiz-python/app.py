from flask import Flask, jsonify, request
from flask_cors import CORS
from ml_model import DifficultyModel

app = Flask(__name__)
CORS(app)
model = DifficultyModel()

@app.get("/")
def root():
    return jsonify({"service": "battle-quiz-python", "status": "ok", "message": "ML service running"})

@app.get("/api/ml/health")
def health():
    return jsonify({"status": "ok", "service": "battle-quiz-python", "model": model.info()})

@app.get("/api/ml/model-info")
def model_info():
    return jsonify({"status": "ok", **model.info()})

@app.post("/api/ml/recommend")
def recommend():
    payload = request.get_json(silent=True) or {}
    try:
        result = model.predict(payload)
        return jsonify({"status": "ok", **result})
    except (TypeError, ValueError) as exc:
        return jsonify({"status": "error", "message": str(exc), "difficulty": "medium"}), 400

@app.post("/api/ml/retrain")
def retrain():
    try:
        info = model.train()
        return jsonify({"status": "ok", "message": "Modelo reentrenado", "model": info})
    except Exception as exc:
        return jsonify({"status": "error", "message": str(exc)}), 500

if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000, debug=False)
