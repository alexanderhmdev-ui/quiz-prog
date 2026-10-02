@echo off
cd /d "%~dp0battle-quiz-python"
echo ========================================
echo   BATTLE QUIZ - PYTHON ML :5000
echo ========================================

set "LOCALPY=%LOCALAPPDATA%\Python\pythoncore-3.14-64\python.exe"

if exist "%LOCALPY%" (
  "%LOCALPY%" -m pip install -r requirements.txt
  "%LOCALPY%" app.py
  goto :end
)

where py >nul 2>nul
if %errorlevel%==0 (
  py -m pip install -r requirements.txt
  py app.py
  goto :end
)

where python >nul 2>nul
if %errorlevel%==0 (
  python -m pip install -r requirements.txt
  python app.py
  goto :end
)

echo No se encontro Python. Instala Python o edita LOCALPY con la ruta de python.exe.

:end
pause
