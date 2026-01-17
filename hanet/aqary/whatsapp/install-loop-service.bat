@echo off
REM This script installs the looping batch script as a Windows service using NSSM
REM Run this as Administrator

cd /d "%~dp0"

REM Set NSSM path
set "nssm=%~dp0..\nssm.exe"

REM Stop and remove existing service if it exists
net stop 0-cron-whatsapp-job 2>nul
"%nssm%" remove 0-cron-whatsapp-job confirm

REM Install the looping batch script as a service
"%nssm%" install 0-cron-whatsapp-job "%~dp0whatsapp-service-loop.bat"

REM Set service properties
"%nssm%" set 0-cron-whatsapp-job AppDirectory "%~dp0"
"%nssm%" set 0-cron-whatsapp-job DisplayName "0-WhatsApp Service Loop Manager"
"%nssm%" set 0-cron-whatsapp-job Description "0-Manages WhatsApp queue processor service with auto-restart"

REM Configure auto-restart on failure
"%nssm%" set 0-cron-whatsapp-job AppExit Default Restart
"%nssm%" set 0-cron-whatsapp-job AppRestartDelay 5000

REM Set startup type to automatic
"%nssm%" set 0-cron-whatsapp-job Start SERVICE_AUTO_START

REM Configure I/O redirection for logging (optional)
"%nssm%" set 0-cron-whatsapp-job AppStdout "%~dp0logs\service-output.log"
"%nssm%" set 0-cron-whatsapp-job AppStderr "%~dp0logs\service-error.log"

REM Start the service
net start 0-cron-whatsapp-job

echo.
echo Service installed and started successfully!
echo Service Name: 0-cron-whatsapp-job
echo.
echo To stop: net stop 0-cron-whatsapp-job
echo To start: net start 0-cron-whatsapp-job
echo To remove: nssm remove 0-cron-whatsapp-job confirm

timeout /t 5