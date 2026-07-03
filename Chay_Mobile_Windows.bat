@echo off
title StayHub Mobile Frontend Runner - Chrome
echo ====================================================================
echo   StayHub Mobile Frontend - Auto Runner (Chrome Target)
echo ====================================================================

set "CURRENT_PATH=%~dp0"
echo Local path of this script: %CURRENT_PATH%

:: Check if the path is a UNC network path (starts with \\)
if "%CURRENT_PATH:~0,2%"=="\\" (
    echo [Info] Running from WSL UNC network path.
    echo [Info] Attempting to map to drive letter W: to bypass Flutter SDK crashes...
    
    :: Remove trailing backslash for net use
    set "SHARE_PATH=%CURRENT_PATH:~0,-1%"
    
    :: Map the drive W:
    net use W: "%SHARE_PATH%" /y >nul 2>&1
    if errorlevel 1 (
        echo [Warning] Drive W: might be in use. Trying alternative drive V:...
        net use V: "%SHARE_PATH%" /y >nul 2>&1
        if errorlevel 1 (
            echo [Error] Could not map network drive automatically. 
            echo Please map your WSL folder manually (e.g. Map Network Drive in Windows Explorer)
            echo and run 'flutter run -d chrome' from there.
            pause
            exit /b 1
        ) else (
            set "RUN_DRIVE=V:"
        )
    ) else (
        set "RUN_DRIVE=W:"
    )
    
    echo [Success] Mapped network drive to %RUN_DRIVE%
    echo [Info] Starting Flutter from the mapped drive %RUN_DRIVE%...
    
    %RUN_DRIVE%
    cd FE_StayHub_Mobile
    echo [Info] Running: flutter run -d chrome
    D:\Softwares\Google\Flutter\bin\flutter.bat run -d chrome
) else (
    echo [Info] Running on local drive letter.
    cd FE_StayHub_Mobile
    echo [Info] Running: flutter run -d chrome
    D:\Softwares\Google\Flutter\bin\flutter.bat run -d chrome
)

pause
