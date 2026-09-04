@echo off
cd /d "D:\Swapnil Onedrive\OneDrive - EduRiser Learning Solutions (P) Ltd\Swapnil PC\Internal\Clientele\Clientele-online"
echo Starting Clientele preview server...
echo.
echo Public site: http://127.0.0.1:8091/index.php
echo Admin:       http://127.0.0.1:8091/admin/login.php
echo.
echo Keep this window open while previewing.
echo Press Ctrl+C to stop the server.
echo.
"C:\xampp\php\php.exe" -S 127.0.0.1:8091
pause
