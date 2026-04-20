@echo off

cd /d "%~dp0"

:: this will run the bat only once per day
:: Use PowerShell to get day reliably (always returns 01-31 format)
for /f %%a in ('powershell -command "Get-Date -Format dd"') do set day=%%a
set /P lastRun=< scheduler.txt 2>nul
if not defined lastRun set lastRun=00
echo Current day: %day%
echo Last run: %lastRun%
if "%day%" EQU "%lastRun%" (
	echo Already updated today, exiting...
	exit /b 0
)
:: Record current day in scheduler.txt
echo %day%> scheduler.txt


setlocal

:: Set Git path
set GIT_PATH=%~dp0PortableGit\cmd\git.exe
set REPO_URL=https://github.com/codejq/saudiakar-erp-update.git
set BRANCH=main
set TARGET_FOLDER=%~dp0..\

:: Check if git exists
if not exist "%GIT_PATH%" (
    echo Error: Git not found!
	echo Error: Git not found! >>log.txt
    exit /b 1
)

echo OK Git found;

:: Navigate to target folder
cd /d "%TARGET_FOLDER%"


dir

:: Check if .git exists
if not exist ".git" (
    echo Initializing repository...
    "%GIT_PATH%" init -b main
    "%GIT_PATH%" remote add origin %REPO_URL%
)



:: Fetch and reset
echo Updating files...

copy "C:\saudiakar-erp\aso\frp\frpc.toml" "C:\saudiakar-erp\aso\frp\frpc.toml.backup" /y

timeout 3

"%GIT_PATH%" fetch origin
"%GIT_PATH%" reset --hard origin/%BRANCH%


copy "C:\saudiakar-erp\aso\frp\frpc.toml.backup" "C:\saudiakar-erp\aso\frp\frpc.toml" /y
 

del "C:\saudiakar-erp\aso\frp\frpc.toml.backup"

echo Update complete!

echo Update complete >>log.txt
