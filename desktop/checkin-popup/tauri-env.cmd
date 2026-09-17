@echo off
setlocal
set "PATH=%USERPROFILE%\.cargo\bin;%PATH%"
call "C:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\VC\Auxiliary\Build\vcvars64.bat"
if errorlevel 1 (
  echo Visual C++ Build Tools not ready yet. Finish installing Build Tools, then retry.
  exit /b 1
)
cd /d "%~dp0"
npm run tauri %*
