@echo off
cd /d "%~dp0"
echo Stopping server on port 43127...
for /f "tokens=5" %%P in ('netstat -aon ^| findstr ":43127" ^| findstr "LISTENING"') do (
  taskkill /PID %%P /F >nul 2>&1
)
echo Done.
pause
