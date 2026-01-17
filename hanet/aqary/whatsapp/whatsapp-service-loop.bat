@echo off
REM Self-looping script to manage WhatsApp service
REM This script runs continuously and executes the service setup every minute

REM Generate a unique process ID using timestamp
set process_id=%time:~0,2%%time:~3,2%%time:~6,2%%random%
set process_id=%process_id: =0%

echo ========================================
echo Starting WhatsApp Service Loop
echo Process ID: %process_id%
echo ========================================
echo.
timeout /t 5

:loop
cd /d "%~dp0"

echo.
echo === Starting Loop Iteration ===
echo Current directory: %CD%
echo Process ID: %process_id%
echo.

echo Checking if lock file exists...
if exist running.lock.txt (
    echo YES - Lock file found
) else (
    echo NO - Lock file not found
)

REM Check if lock file exists and is from ANOTHER process
if exist running.lock.txt (
    echo Lock file found, checking...
    goto check_lock
) else (
    echo No lock file found, proceeding...
    goto run_curl
)

:check_lock
REM Read the process ID and timestamp from the lock file
set /p lock_data=<running.lock.txt
echo Lock data read: [%lock_data%]

REM Check if lock_data is empty
if not defined lock_data (
    echo Lock file is empty, deleting it
    del running.lock.txt 2>nul
    goto run_curl
)

REM Extract process_id and time (format: PROCESS_ID HH:MM:SS)
for /f "tokens=1,2" %%a in ("%lock_data%") do (
    set lock_process_id=%%a
    set lock_time=%%b
)

REM Check if we got valid data
if not defined lock_process_id (
    echo Lock file has invalid format, deleting it
    del running.lock.txt 2>nul
    goto run_curl
)

if not defined lock_time (
    echo Lock file has invalid format, deleting it
    del running.lock.txt 2>nul
    goto run_curl
)

echo Lock Process ID: [%lock_process_id%]
echo Our Process ID: [%process_id%]
echo Lock Time: [%lock_time%]

REM If this is our own lock file, skip the check
if "%lock_process_id%"=="%process_id%" (
    echo This is our own lock file, continuing...
    goto run_curl
)

echo This is NOT our lock file, checking age...

REM Get current time
set current_time=%time%

REM Parse lock_time to extract hour and minute (handle both H:MM:SS and HH:MM:SS)
for /f "tokens=1,2,3 delims=:" %%a in ("%lock_time%") do (
    set lock_hour=%%a
    set lock_min=%%b
)

REM Parse current_time to extract hour and minute
for /f "tokens=1,2,3 delims=:" %%a in ("%current_time%") do (
    set current_hour=%%a
    set current_min=%%b
)

echo Debug - lock_hour: [%lock_hour%]
echo Debug - lock_min: [%lock_min%]
echo Debug - current_hour: [%current_hour%]
echo Debug - current_min: [%current_min%]

REM Remove leading spaces
set lock_hour=%lock_hour: =%
set current_hour=%current_hour: =%
set lock_min=%lock_min: =%
set current_min=%current_min: =%

echo Debug after space removal - lock_hour: [%lock_hour%]
echo Debug after space removal - lock_min: [%lock_min%]
echo Debug after space removal - current_hour: [%current_hour%]
echo Debug after space removal - current_min: [%current_min%]

REM Remove leading zeros by adding 100 then subtracting 100
set /a lock_hour=1%lock_hour%-100 2>nul
if errorlevel 1 set lock_hour=0

set /a lock_min=1%lock_min%-100 2>nul
if errorlevel 1 set lock_min=0

set /a current_hour=1%current_hour%-100 2>nul
if errorlevel 1 set current_hour=0

set /a current_min=1%current_min%-100 2>nul
if errorlevel 1 set current_min=0

echo Debug after conversion - lock_hour: %lock_hour%
echo Debug after conversion - lock_min: %lock_min%
echo Debug after conversion - current_hour: %current_hour%
echo Debug after conversion - current_min: %current_min%

REM Calculate total minutes since midnight for both times
set /a lock_total_min=%lock_hour%*60 + %lock_min%
set /a current_total_min=%current_hour%*60 + %current_min%

REM Calculate difference in minutes
set /a time_diff=%current_total_min% - %lock_total_min%

REM Handle negative differences (day rollover)
if %time_diff% LSS 0 set /a time_diff=%time_diff% + 1440

echo Time difference: %time_diff% minutes

REM If less than 5 minutes, another instance is running
if %time_diff% LSS 5 (
    echo Lock file detected - another instance is running
    echo Lock time: %lock_time%
    echo Current time: %current_time%
    echo Time difference: %time_diff% minutes
    echo Exiting to prevent duplicate execution
    timeout /t 5
    exit /b 0
) else (
    REM File is old (more than 5 minutes), delete it
    echo Lock file is stale ^(%time_diff% minutes old^), removing it
    del running.lock.txt 2>nul
    goto run_curl
)

:run_curl
REM Create/update the lock file with our process ID and current timestamp
echo %process_id% %time% > running.lock.txt
echo Lock file created: %process_id% %time%

REM Check if curl exists
if not exist "%~dp0../tools/curl/curl.exe" (
    echo ERROR: curl.exe not found in current directory
    echo Current directory: %CD%
    timeout /t 5
    exit /b 1
)

REM Execute curl to trigger the service
echo Calling WhatsApp queue processor...
"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/admin/whatsapp/queue_processor.php"

echo Calling mail queue processor...

"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/admin/todo/process_emails.hnt"

echo Calling update contract statuses ...

"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/admin/include/amlak/update_contract_statuses.hnt?run=1"

echo Calling auto_add_installments.api.hnt ...

"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/admin/include/amlak/auto_add_installments.api.hnt?run=1"

"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/admin/reports/weekly_financial_report.hnt?auto=1"

"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/excuteupdate.hnt"


REM Return to script directory

cd /d "%~dp0"


echo.
echo Waiting 60 seconds before next run...
REM Wait 60 seconds before next iteration
timeout /t 10 /nobreak

REM Loop back to start
goto loop
