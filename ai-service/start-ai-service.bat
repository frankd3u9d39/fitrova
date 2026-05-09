@echo off
echo ========================================
echo Starting Fitrova AI Workout Service
echo ========================================
echo.

cd /d "%~dp0"

echo Checking Python installation...
set PYTHON_CMD=python
python --version >nul 2>&1
if errorlevel 1 (
    set PYTHON_CMD=py
    py --version >nul 2>&1
    if errorlevel 1 (
        echo ERROR: Python is not installed or not in PATH
        echo Please install Python 3.8 or higher
        pause
        exit /b 1
    )
)
echo Using %PYTHON_CMD%...
%PYTHON_CMD% --version

echo.
echo Installing dependencies...
%PYTHON_CMD% -m pip install -r requirements.txt

echo.
echo Starting AI service on http://localhost:5001
echo Press Ctrl+C to stop the service
echo.

cd api
%PYTHON_CMD% video_analyzer.py

pause
