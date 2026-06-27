@echo off
title Khoi dong ADB Server cho WSL - StayHub
echo [StayHub] Dang khoi dong ADB Server tren Windows de lien ket voi WSL...
cd /d "C:\Users\Martyr\AppData\Local\Packages\32533HUXSoft.APKInstallerandManagerforWindows_aep6pg3hkma3e\LocalState\platform-tools"
adb.exe kill-server
adb.exe -a nodaemon server start
pause
