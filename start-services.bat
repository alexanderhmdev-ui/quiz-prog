@echo off
cd /d "%~dp0"
echo Iniciando Battle Quiz...
start "Battle Quiz - Java" cmd /k "cd /d %~dp0battle-quiz-java && mvnw.cmd spring-boot:run"
start "Battle Quiz - Python ML" cmd /k "cd /d %~dp0battle-quiz-python && py -m pip install -r requirements.txt && py app.py"
echo.
echo Servicios lanzados en dos terminales.
echo Java:   http://localhost:8081/api/battle/health
echo Python: http://localhost:5000/api/ml/health
echo.
echo Recuerda iniciar Apache y MySQL en Laragon.
pause
