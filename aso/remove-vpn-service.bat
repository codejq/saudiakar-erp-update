#cd /d %0\..
cd /d "%~dp0"

net stop 0-vpnFrpClient 
set "nssm=%~dp0..\hanet\aqary\nssm.exe"
"%nssm%" remove 0-vpnFrpClient confirm

 
