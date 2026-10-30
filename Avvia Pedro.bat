@echo off
cd /d "C:\Users\ermeg\Desktop\Pedro"
docker compose up -d
if errorlevel 1 (
    pause
    exit /b 1
)
start "" "http://localhost/admin"