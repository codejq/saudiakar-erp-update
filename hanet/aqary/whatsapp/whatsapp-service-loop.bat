@echo off
REM WhatsApp Service Task - Managed by NSSM
REM NSSM handles process management, restart delays, and prevents multiple instances
REM This script just executes tasks once and exits cleanly

cd /d "%~dp0"

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
    exit /b 1
)

REM Execute all scheduled tasks
echo [1/6] Calling WhatsApp queue processor...
"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/admin/whatsapp/queue_processor.php"

echo [2/6] Calling mail queue processor...
"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/admin/todo/process_emails.hnt"

echo [3/6] Calling update contract statuses...
"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/admin/include/amlak/update_contract_statuses.hnt?run=1"

echo [4/6] Calling auto add installments...
"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/admin/include/amlak/auto_add_installments.api.hnt?run=1"

echo [5/6] Calling weekly financial report...
"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/admin/reports/weekly_financial_report.hnt?auto=1"

echo [6/6] Calling system update...
"%~dp0../tools/curl/curl.exe" "http://127.0.0.1:9009/aqary/excuteupdate.hnt"

echo.
echo ========================================
echo All tasks completed successfully
echo Time: %date% %time%
echo ========================================

REM Exit cleanly - NSSM will restart this script after configured delay
exit /b 0
