@echo off
cd /d "%~dp0"
where python >nul 2>&1
if not errorlevel 1 (
    python scanner.py
    goto FIM
)
where py >nul 2>&1
if not errorlevel 1 (
    py -3 scanner.py
    goto FIM
)
echo Python nao encontrado. Instale em python.org marcando "Add Python to PATH".
:FIM
pause
