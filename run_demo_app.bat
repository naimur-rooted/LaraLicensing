@echo off
title Lara Protected Studio - Client Demo App
cd /d "%~dp0"
echo ===================================================
echo Starting Lara Protected Studio Client Simulator...
echo ===================================================
python scripts\demo_client_app.py
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo Application exited. Press any key to close this window...
    pause >nul
)
