@echo off
echo ============================================
echo  INSTALADOR - Agente de Soporte Tecnico
echo ============================================
echo.

REM Verificar Python
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Python no esta instalado.
    echo Descarga Python desde: https://www.python.org/downloads/
    pause
    exit /b 1
)

echo [OK] Python encontrado
echo.

REM Instalar dependencias
echo Instalando dependencias...
pip install -r requirements.txt
if %errorlevel% neq 0 (
    echo [ERROR] No se pudieron instalar las dependencias.
    pause
    exit /b 1
)

echo.
echo [OK] Dependencias instaladas correctamente
echo.

REM Configurar agente
echo Iniciando configuracion del agente...
echo.
python agente.py --configurar

echo.
echo ============================================
echo  Instalacion completada!
echo  Para ejecutar: python agente.py
echo ============================================
pause
