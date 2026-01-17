@echo off
REM This script installs the looping batch script as a Windows service using NSSM
REM Run this as Administrator

cd /d "%~dp0"

REM Set NSSM path
set "nssm=%~dp0..\nssm.exe"

REM Stop and remove existing service if it exists
net stop 0-cron-whatsapp-job 2>nul
"%nssm%" remove 0-cron-whatsapp-job confirm
 