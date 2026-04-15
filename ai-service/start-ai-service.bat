@echo off
echo ========================================
echo Starting Fitrova AI Workout Service
echo ========================================
echo.

cd /d "%~dp0"

echo Checking Python installation...
python --version
if errorlevel 1 (
    echo ERROR: Python is not installed or not in PATH
    echo Please install Python 3.8 or higher
    pause
    exit /b 1
)

echo.
echo Installing dependencies...
pip install -r requirements.txt

echo.
echo Starting AI service on http://localhost:5001
echo Press Ctrl+C to stop the service
echo.

cd api
python recommend-workout.py

pause
