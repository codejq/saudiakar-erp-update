#cd /d %0\..
cd /d "%~dp0"
net stop 0-whatsappClient 
set "nssm=%~dp0..\nssm.exe"
"%nssm%" remove 0-whatsappClient confirm

"%nssm%" install 0-whatsappClient "%~dp0windows-amd64.exe" "rest --port 8010 --auto-download-media=false  --os=Chrome"
#"%nssm%" set 0-whatsappClient AppParameters "rest --port 8010 --auto-download-media=false --os=Chrome"
"%nssm%" set 0-whatsappClient AppDirectory "%~dp0"

net start  0-whatsappClient

# we are using nssm version 2.21.1.0 as the new one have a lot of problems 
#for code genrator make use of teh follwoign informations
#source https://github.com/aldinokemal/go-whatsapp-web-multidevice?tab=readme-ov-file
#Windows: .\whatsapp.exe rest (for REST API mode)
#run .\whatsapp.exe --help for more detail flags
#open http://localhost:3000 in browser
