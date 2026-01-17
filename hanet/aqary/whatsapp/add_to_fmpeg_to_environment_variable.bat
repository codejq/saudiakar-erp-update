@echo off
:: Check for administrator privileges
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: This script requires administrator privileges.
    echo Please right-click and select "Run as administrator"
    pause
    exit /b 1
)

cd /d %~dp0

:: Set FFmpeg path
set "FFMPEG_PATH=%~dp0ffmpeg-master-latest-win64-gpl-shared\bin"

:: Get current system PATH
for /f "skip=2 tokens=3*" %%a in ('reg query "HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /v Path') do set "SYSTEM_PATH=%%a %%b"

:: Check and add FFmpeg path
echo %SYSTEM_PATH% | find /i "%FFMPEG_PATH%" >nul
if errorlevel 1 (
    setx /M PATH "%SYSTEM_PATH%;%FFMPEG_PATH%"
    echo FFmpeg path added to system environment.
) else (
    echo FFmpeg path already exists in system PATH.
)

:: Refresh the system PATH variable for next check
for /f "skip=2 tokens=3*" %%a in ('reg query "HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /v Path') do set "SYSTEM_PATH=%%a %%b"

:: Set libwebp path
set "LIBWEB_PATH=%~dp0libwebp-1.6.0-windows-x64\bin"

:: Check and add libwebp path
echo %SYSTEM_PATH% | find /i "%LIBWEB_PATH%" >nul
if errorlevel 1 (
    setx /M PATH "%SYSTEM_PATH%;%LIBWEB_PATH%"
    echo libwebp path added to system environment.
) else (
    echo libwebp path already exists in system PATH.
)

echo.
echo Done! You may need to restart applications for changes to take effect.
