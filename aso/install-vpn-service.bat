#cd /d %0\..
cd /d "%~dp0"

net stop 0-vpnFrpClient 
set "nssm=%~dp0..\hanet\aqary\nssm.exe"
"%nssm%" remove 0-vpnFrpClient confirm

"%nssm%" install 0-vpnFrpClient "%~dp0\frp\frpc.exe" "-c %~dp0\frp\frpc.toml"

"%nssm%" set 0-vpnFrpClient AppDirectory "%~dp0\frp"

net start  0-vpnFrpClient
