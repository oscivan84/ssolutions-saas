#!/bin/bash
echo "============================================"
echo " INSTALADOR - Agente de Soporte Tecnico"
echo "============================================"
echo

# Verificar Python
if ! command -v python3 &> /dev/null; then
    echo "[ERROR] Python3 no esta instalado."
    echo "Instala con: sudo apt install python3 python3-pip"
    exit 1
fi

echo "[OK] Python3 encontrado"
echo

# Instalar dependencias
echo "Instalando dependencias..."
pip3 install -r requirements.txt
if [ $? -ne 0 ]; then
    echo "[ERROR] No se pudieron instalar las dependencias."
    exit 1
fi

echo
echo "[OK] Dependencias instaladas"
echo

# Configurar
echo "Iniciando configuracion..."
python3 agente.py --configurar

echo
echo "============================================"
echo " Instalacion completada!"
echo " Para ejecutar: python3 agente.py"
echo "============================================"
