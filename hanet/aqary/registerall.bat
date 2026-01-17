cd /d %0\..

rem run every time




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




#md5chekupdater.exe

start /B tools\curl\curl.exe  "http://www.saudiakar.net/heartbeat.php"
start /B tools\curl\curl.exe  "http://www.saudiakar.net/heartbeat.php"


cd /d %0\..

:: variables

set drive=%1

mkdir 'C:\saduiakar.backup-erp
mkdir 'C:\saduiakar.backup-erp
mkdir d:\saduiakar.backup-erp
mkdir d:\saduiakar.backup-erp
mkdir e:\saduiakar.backup-erp
mkdir e:\saduiakar.backup-erp


IF (%1)==()  set drive='C:\saduiakar.backup-erp

cd ..
cd ..
set backupcmd=xcopy /s /c /d /e /h /i /r /k /y
set robocopyxp=./hanet/aqary/robocopy.exe
set robooption =/FFT /E /zb /r:5 /w:5

  %backupcmd% "."  %drive%
  ./hanet/aqary/robocopy.exe "."  %drive% /FFT /E /zb /r:5 /w:5
  Robocopy "." "%drive%" /FFT /E /zb /r:5 /w:5


IF NOT EXIST d:\saduiakar.backup-erp GOTO NODDIR1
   set drive=d:\saduiakar.backup-erp
   %backupcmd% "."  %drive%
   ./hanet/aqary/robocopy.exe "."  %drive%  /FFT /E /zb /r:5 /w:5
   Robocopy "." "%drive%"  /FFT /E /zb /r:5 /w:5
:NODDIR1

IF NOT EXIST E:\saduiakar.backup-erp GOTO NOEDIR2
   set drive=E:\saduiakar.backup-erp
   %backupcmd% "."  %drive%
   ./hanet/aqary/robocopy.exe "."  %drive%  /FFT /E /zb /r:5 /w:5
   Robocopy "." "%drive%"  /FFT /E /zb /r:5 /w:5
:NOEDIR2



cd /d %0\..
cd hanet
cd aqary

timeout 10

start  /B  mysqldump.bat



for /f "tokens=1-4 delims=/ " %%i in ("%date%") do (
  set dow=%%i
  set month=%%j
  set day=%%k
  set year=%%l

)

set /a year=year + 1

IF NOT EXIST saudiakar.exe GOTO noshort
set drive="C:\saudiakar-erp"

../../aso/hanetmakelink.exe C:\saudiakar-erp\app\saudiakar.exe*saudiakar.net

:noshort

cd /d %0\..


 

timeout 7
rem run every time
cd /d %0\..



cd /d %0\..


for /f "tokens=1-4 delims=/ " %%i in ("%date%") do (
     set dow=%%i
     set month=%%j
     set day=%%k
     set year=%%l
)
set datestr=%day%-%month%-%year%
echo datestr is %datestr%

set drive=../../aso/Apache24/mariadb/data/aqary

mkdir C:\aqary
mkdir d:\aqary
mkdir e:\aqary

IF NOT EXIST "c:\aqary" GOTO NOEDIR1
	%backupcmd% "%drive%"  c:\aqary\%datestr%
	./hanet/aqary/robocopy.exe "%drive%" c:\aqary\%datestr%  /FFT /E /zb /r:5 /w:5
	Robocopy "%drive%" c:\aqary\%datestr%  /FFT /E /zb /r:5 /w:5
:NOEDIR1
IF NOT EXIST "d:\aqary" GOTO NOEDIR2
	%backupcmd% "%drive%" d:\aqary\%datestr%
	./hanet/aqary/robocopy.exe "%drive%"  d:\aqary\%datestr%  /FFT /E /zb /r:5 /w:5
	Robocopy "%drive%" d:\aqary\%datestr%  /FFT /E /zb /r:5 /w:5
:NOEDIR2

IF NOT EXIST "e:\aqary" GOTO NOEDIR3
	%backupcmd% "%drive%"  e:\aqary\%datestr%
	./hanet/aqary/robocopy.exe "%drive%"  e:\aqary\%datestr%  /FFT /E /zb /r:5 /w:5
	Robocopy "%drive%"  e:\aqary\%datestr%  /FFT /E /zb /r:5 /w:5
:NOEDIR3

cd /d %0\..


 
