@echo off
setlocal
title SKalinga Cloudflare Quick Tunnel

echo Checking Apache on port 80...
netstat -ano -p tcp | findstr /R /C:":80 .*LISTENING" >nul
if errorlevel 1 (
    echo ERROR: Nothing is listening on port 80.
    echo Start Apache in the XAMPP Control Panel, then run this script again.
    exit /b 1
)

where.exe cloudflared >nul 2>nul
if errorlevel 1 (
    echo ERROR: cloudflared is not installed or is not on PATH.
    echo Install it from an elevated terminal with:
    echo   winget install --id Cloudflare.cloudflared
    exit /b 1
)

echo Starting a temporary public tunnel to http://127.0.0.1:80
echo Wait for cloudflared to print a https://*.trycloudflare.com URL.
echo Open that URL followed by /SKalinga/ in your browser.
echo Example: https://random-name.trycloudflare.com/SKalinga/
echo Press Ctrl+C here to stop the tunnel.
echo.
cloudflared tunnel --url http://127.0.0.1:80

echo.
echo The Quick Tunnel has stopped. Its public URL may change the next time it runs.
pause
