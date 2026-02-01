@echo off
cd /d %0\..
if not exist c:\aqary-backup mkdir c:\aqary-backup
if not exist d:\aqary-backup mkdir d:\aqary-backup
if not exist c:\aqary-backup\sqldump mkdir c:\aqary-backup\sqldump
if not exist d:\aqary-backup\sqldump mkdir d:\aqary-backup\sqldump
if not exist sqldump mkdir sqldump

REM Use wmic for locale-independent date format (returns YYYYMMDDHHMMSS)
for /f "tokens=2 delims==" %%a in ('wmic OS Get localdatetime /value') do set "dt=%%a"
set "year=%dt:~0,4%"
set "month=%dt:~4,2%"
set "day=%dt:~6,2%"

IF EXIST c:\aqary-backup\sqldump\%day%-%month%-%year%.sql.zip GOTO Backupexist



mysqldump.exe --skip-lock-tables --user=root --password=1 --port=3329 --host=127.0.0.1 aqary_utf >c:\aqary-backup\sqldump\%day%-%month%-%year%.sql 2>&1

PING 1.1.1.1 -n 1 -w 60000 >NUL

IF NOT EXIST c:\aqary-backup\sqldump\%day%-%month%-%year%.sql GOTO ErrorBackup

zip -j c:\aqary-backup\sqldump\%day%-%month%-%year%.sql.zip -j c:\aqary-backup\sqldump\%day%-%month%-%year%.sql

IF NOT EXIST c:\aqary-backup\sqldump\%day%-%month%-%year%.sql.zip GOTO ErrorBackup

copy c:\aqary-backup\sqldump\%day%-%month%-%year%.sql.zip sqldump\%day%-%month%-%year%.sql.zip

PING 1.1.1.1 -n 1 -w 60000 >NUL

del c:\aqary-backup\sqldump\%day%-%month%-%year%.sql
PING 1.1.1.1 -n 1 -w 60000 >NUL

copy c:\aqary-backup\sqldump\%day%-%month%-%year%.sql.zip d:\aqary-backup\sqldump\%day%-%month%-%year%.sql.zip

GOTO CleanupFiles


:ErrorBackup
echo Backup failed
if exist deleteoldfiles.exe (
    deleteoldfiles.exe 10 "sqldump" "yes" 2>nul
    deleteoldfiles.exe 60 "C:\aqary-backup\sqldump" "yes" 2>nul
    deleteoldfiles.exe 360 "d:\aqary-backup\sqldump" "yes" 2>nul
)
exit /b 1


:CleanupFiles
if exist deleteoldfiles.exe (
    deleteoldfiles.exe 10 "sqldump" "yes" 2>nul
    deleteoldfiles.exe 60 "C:\aqary-backup\sqldump" "yes" 2>nul
    deleteoldfiles.exe 360 "d:\aqary-backup\sqldump" "yes" 2>nul
)
GOTO BackupSuccess


:Backupexist
echo Backup already exists for today

:BackupSuccess
exit /b 0
