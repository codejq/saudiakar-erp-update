@echo off
REM WhatsApp Service Task - Managed by NSSM
REM NSSM handles process management, restart delays, and prevents multiple instances
REM This script just executes tasks once and exits cleanly

cd /d "%~dp0"

REM Set log file path
set LOG_FILE=%~dp0service-execution.log

REM Log script start
echo ======================================== >> "%LOG_FILE%"
echo [START] %date% %time% >> "%LOG_FILE%"
echo Directory: %CD% >> "%LOG_FILE%"
echo ======================================== >> "%LOG_FILE%"

echo ========================================
echo WhatsApp Service Task Execution
echo Time: %date% %time%
echo Directory: %CD%
echo ========================================
echo.

REM Check if curl exists
if not exist "%~dp0../tools/curl/curl.exe" (
    echo ERROR: curl.exe not found
    echo Expected path: %~dp0../tools/curl/curl.exe
    echo [ERROR] %date% %time% - curl.exe not found >> "%LOG_FILE%"
    echo. >> "%LOG_FILE%"
    exit /b 1
)

REM Execute all scheduled tasks
echo [1/6] Calling WhatsApp queue processor...
echo [1/6] %time% - WhatsApp queue processor >> "%LOG_FILE%"
"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/admin/whatsapp/queue_processor.php"

echo [2/6] Calling mail queue processor...
echo [2/6] %time% - Mail queue processor >> "%LOG_FILE%"
"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/admin/todo/process_emails.hnt"

echo [3/6] Calling update contract statuses...
echo [3/6] %time% - Update contract statuses >> "%LOG_FILE%"
"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/admin/include/amlak/update_contract_statuses.hnt?run=1"

echo [4/6] Calling auto add installments...
echo [4/6] %time% - Auto add installments >> "%LOG_FILE%"
"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/admin/include/amlak/auto_add_installments.api.hnt?run=1"

echo [5/6] Calling weekly financial report...
echo [5/6] %time% - Weekly financial report >> "%LOG_FILE%"
"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/admin/reports/weekly_financial_report.hnt?auto=1"

echo [6/6] Calling system update...
echo [6/6] %time% - System update >> "%LOG_FILE%"
"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/excuteupdate.hnt"

echo.
echo ========================================
echo All tasks completed successfully
echo Time: %date% %time%
echo ========================================

REM Log script completion
echo [END] %date% %time% - All tasks completed >> "%LOG_FILE%"
echo. >> "%LOG_FILE%"

REM Check log file size and delete if exceeds 5 MB
for /f %%A in ('powershell -command "if (Test-Path '%LOG_FILE%') { (Get-Item '%LOG_FILE%').Length } else { 0 }"') do set LOG_SIZE=%%A
if %LOG_SIZE% GTR 5242880 (
    echo Log file size: %LOG_SIZE% bytes ^(exceeds 5 MB^), deleting...
    del "%LOG_FILE%"
    echo Log file deleted and will be recreated on next run
)

timeout 60

REM Exit cleanly - NSSM will restart this script after configured delay
exit /b 0
