#cd /d %0\..
cd /d "%~dp0"
net stop 0-whatsappClient 
set "nssm=%~dp0..\nssm.exe"
"%nssm%" remove 0-whatsappClient confirm
 