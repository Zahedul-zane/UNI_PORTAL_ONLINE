@echo off
setlocal enabledelayedexpansion
title East West University Portal
echo ======================================================
echo   East West University Academic Management Portal
echo   Node.js Backend ^& Excel Database Engine
echo ======================================================
echo.

:: Check if port 8000 is occupied, and free it
for /f "tokens=5" %%a in ('netstat -aon 2^>nul ^| findstr ":8000" ^| findstr "LISTENING"') do (
    echo [INFO] Port 8000 is in use by PID %%a. Terminating previous instance...
    taskkill /F /PID %%a >nul 2>&1
)

:: Wait 1 second
timeout /t 1 /nobreak >nul

echo [INFO] Launching portal in default browser...
start http://localhost:8000

echo [INFO] Starting Node.js server...
node server.js
pause
