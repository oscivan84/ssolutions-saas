#!/usr/bin/env python3
"""
Agente de Soporte Tecnico - Diagnostico y Optimizacion de PC
=============================================================
Analiza el estado del equipo, realiza optimizaciones basicas
y envia el reporte al servidor PHP para crear tickets automaticos.

Uso:
    python agente.py                  # Ejecutar diagnostico completo
    python agente.py --solo-diagnostico  # Solo diagnostico sin optimizar
    python agente.py --solo-reporte      # Solo generar reporte local
    python agente.py --configurar        # Asistente de configuracion
"""

import sys
import os
import json
import platform
import socket
import subprocess
import tempfile
import shutil
import time
from datetime import datetime
from pathlib import Path

try:
    import psutil
except ImportError:
    print("Error: psutil no esta instalado.")
    print("Ejecuta: pip install psutil")
    sys.exit(1)

try:
    import requests
except ImportError:
    print("Error: requests no esta instalado.")
    print("Ejecuta: pip install requests")
    sys.exit(1)

VERSION = "1.0.0"
CONFIG_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "config.json")


def cargar_configuracion():
    """Cargar configuracion desde config.json"""
    if not os.path.exists(CONFIG_FILE):
        print(f"Archivo de configuracion no encontrado: {CONFIG_FILE}")
        print("Ejecuta: python agente.py --configurar")
        return None

    with open(CONFIG_FILE, "r", encoding="utf-8") as f:
        return json.load(f)


def guardar_configuracion(config):
    """Guardar configuracion en config.json"""
    with open(CONFIG_FILE, "w", encoding="utf-8") as f:
        json.dump(config, f, indent=4, ensure_ascii=False)


# ============================================================
# MODULO DE DIAGNOSTICO
# ============================================================

def diagnosticar_cpu():
    """Obtener informacion y uso del CPU"""
    try:
        cpu_percent = psutil.cpu_percent(interval=2)
        cpu_freq = psutil.cpu_freq()
        cpu_count = psutil.cpu_count(logical=True)
        cpu_count_physical = psutil.cpu_count(logical=False)

        # Modelo del CPU
        modelo = platform.processor() or "Desconocido"
        if modelo == "Desconocido" and platform.system() == "Windows":
            try:
                import winreg
                key = winreg.OpenKey(winreg.HKEY_LOCAL_MACHINE,
                    r"HARDWARE\DESCRIPTION\System\CentralProcessor\0")
                modelo = winreg.QueryValueEx(key, "ProcessorNameString")[0].strip()
                winreg.CloseKey(key)
            except Exception:
                pass

        return {
            "modelo": modelo,
            "uso_porcentaje": round(cpu_percent, 1),
            "nucleos": cpu_count,
            "nucleos_fisicos": cpu_count_physical,
            "frecuencia_mhz": round(cpu_freq.current, 0) if cpu_freq else None,
            "frecuencia_max_mhz": round(cpu_freq.max, 0) if cpu_freq and cpu_freq.max else None
        }
    except Exception as e:
        return {"modelo": "Error", "uso_porcentaje": 0, "nucleos": 0, "error": str(e)}


def diagnosticar_ram():
    """Obtener informacion de memoria RAM"""
    try:
        mem = psutil.virtual_memory()
        return {
            "total_gb": round(mem.total / (1024**3), 2),
            "usada_gb": round(mem.used / (1024**3), 2),
            "disponible_gb": round(mem.available / (1024**3), 2),
            "uso_porcentaje": round(mem.percent, 1)
        }
    except Exception as e:
        return {"total_gb": 0, "usada_gb": 0, "uso_porcentaje": 0, "error": str(e)}


def diagnosticar_disco():
    """Obtener informacion de disco principal"""
    try:
        if platform.system() == "Windows":
            disco = psutil.disk_usage("C:\\")
        else:
            disco = psutil.disk_usage("/")

        return {
            "total_gb": round(disco.total / (1024**3), 2),
            "usado_gb": round(disco.used / (1024**3), 2),
            "libre_gb": round(disco.free / (1024**3), 2),
            "uso_porcentaje": round(disco.percent, 1)
        }
    except Exception as e:
        return {"total_gb": 0, "usado_gb": 0, "uso_porcentaje": 0, "error": str(e)}


def diagnosticar_temperatura():
    """Obtener temperatura del CPU (si esta disponible)"""
    try:
        temps = psutil.sensors_temperatures()
        if temps:
            for name, entries in temps.items():
                for entry in entries:
                    if entry.current > 0:
                        return round(entry.current, 1)
    except (AttributeError, Exception):
        pass
    return None


def diagnosticar_procesos():
    """Analizar procesos activos y detectar alto consumo"""
    try:
        procesos = []
        alto_consumo = []

        for proc in psutil.process_iter(["pid", "name", "cpu_percent", "memory_percent"]):
            try:
                info = proc.info
                if info["cpu_percent"] and info["cpu_percent"] > 20:
                    alto_consumo.append({
                        "nombre": info["name"],
                        "cpu": round(info["cpu_percent"], 1),
                        "ram": round(info["memory_percent"], 1)
                    })
            except (psutil.NoSuchProcess, psutil.AccessDenied):
                pass

        return {
            "total_activos": len(list(psutil.process_iter())),
            "alto_consumo": sorted(alto_consumo, key=lambda x: x["cpu"], reverse=True)[:5]
        }
    except Exception as e:
        return {"total_activos": 0, "alto_consumo": [], "error": str(e)}


def diagnosticar_red():
    """Diagnosticar conectividad de red"""
    try:
        # Verificar conectividad
        conectado = False
        try:
            socket.create_connection(("8.8.8.8", 53), timeout=3)
            conectado = True
        except OSError:
            pass

        # Info de red
        net_io = psutil.net_io_counters()

        return {
            "conectado": conectado,
            "bytes_enviados_mb": round(net_io.bytes_sent / (1024**2), 1),
            "bytes_recibidos_mb": round(net_io.bytes_recv / (1024**2), 1),
            "hostname": socket.gethostname()
        }
    except Exception as e:
        return {"conectado": False, "error": str(e)}


def diagnosticar_sistema():
    """Informacion general del sistema operativo"""
    return {
        "hostname": socket.gethostname(),
        "os": f"{platform.system()} {platform.release()}",
        "os_version": platform.version(),
        "arquitectura": platform.machine(),
        "usuario": os.getlogin() if hasattr(os, "getlogin") else "N/A",
        "boot_time": datetime.fromtimestamp(psutil.boot_time()).isoformat()
    }


# ============================================================
# DETECCION DE PROBLEMAS
# ============================================================

def detectar_problemas(cpu, ram, disco, procesos, temperatura, red):
    """Analizar datos y detectar problemas"""
    problemas = []
    recomendaciones = []

    # CPU
    if cpu["uso_porcentaje"] > 90:
        problemas.append("CPU al limite: uso al " + str(cpu["uso_porcentaje"]) + "%")
        recomendaciones.append("Cerrar programas innecesarios o revisar procesos en segundo plano")
    elif cpu["uso_porcentaje"] > 75:
        problemas.append("CPU con uso elevado: " + str(cpu["uso_porcentaje"]) + "%")
        recomendaciones.append("Revisar programas de inicio automatico")

    # RAM
    if ram["uso_porcentaje"] > 90:
        problemas.append("Memoria RAM critica: " + str(ram["uso_porcentaje"]) + "% en uso")
        recomendaciones.append("Cerrar aplicaciones pesadas o considerar ampliar la RAM")
    elif ram["uso_porcentaje"] > 80:
        problemas.append("Memoria RAM elevada: " + str(ram["uso_porcentaje"]) + "% en uso")
        recomendaciones.append("Cerrar pestanas del navegador y aplicaciones no necesarias")

    # Disco
    if disco["uso_porcentaje"] > 95:
        problemas.append("Disco casi lleno: " + str(disco["uso_porcentaje"]) + "% ocupado")
        recomendaciones.append("URGENTE: Liberar espacio eliminando archivos temporales y programas no usados")
    elif disco["uso_porcentaje"] > 85:
        problemas.append("Espacio en disco bajo: " + str(disco["uso_porcentaje"]) + "% ocupado")
        recomendaciones.append("Limpiar archivos temporales y descargas innecesarias")

    # Temperatura
    if temperatura and temperatura > 85:
        problemas.append("Temperatura CPU alta: " + str(temperatura) + " C")
        recomendaciones.append("Verificar ventilacion y limpieza de ventiladores")
    elif temperatura and temperatura > 75:
        problemas.append("Temperatura CPU elevada: " + str(temperatura) + " C")
        recomendaciones.append("Asegurar buena ventilacion del equipo")

    # Procesos
    if procesos.get("alto_consumo"):
        nombres = [p["nombre"] for p in procesos["alto_consumo"][:3]]
        problemas.append("Procesos con alto consumo: " + ", ".join(nombres))
        recomendaciones.append("Revisar si estos programas son necesarios: " + ", ".join(nombres))

    if procesos.get("total_activos", 0) > 200:
        problemas.append("Demasiados procesos activos: " + str(procesos["total_activos"]))
        recomendaciones.append("Desinstalar programas innecesarios que se ejecutan al inicio")

    # Red
    if not red.get("conectado", True):
        problemas.append("Sin conexion a internet")
        recomendaciones.append("Verificar cable de red o conexion WiFi")

    return problemas, recomendaciones


# ============================================================
# MODULO DE OPTIMIZACION
# ============================================================

def optimizar_equipo():
    """Realizar optimizaciones basicas del sistema"""
    optimizaciones = []

    if platform.system() == "Windows":
        optimizaciones.extend(optimizar_windows())
    else:
        optimizaciones.extend(optimizar_linux())

    return optimizaciones


def optimizar_windows():
    """Optimizaciones para Windows"""
    resultados = []

    # 1. Limpiar archivos temporales
    try:
        temp_dirs = [
            tempfile.gettempdir(),
            os.path.join(os.environ.get("LOCALAPPDATA", ""), "Temp"),
        ]

        archivos_eliminados = 0
        for temp_dir in temp_dirs:
            if os.path.exists(temp_dir):
                for item in os.listdir(temp_dir):
                    ruta = os.path.join(temp_dir, item)
                    try:
                        if os.path.isfile(ruta):
                            edad = time.time() - os.path.getmtime(ruta)
                            if edad > 86400:  # Mas de 1 dia
                                os.remove(ruta)
                                archivos_eliminados += 1
                        elif os.path.isdir(ruta):
                            edad = time.time() - os.path.getmtime(ruta)
                            if edad > 86400:
                                shutil.rmtree(ruta, ignore_errors=True)
                                archivos_eliminados += 1
                    except (PermissionError, OSError):
                        pass

        if archivos_eliminados > 0:
            resultados.append(f"Archivos temporales eliminados: {archivos_eliminados}")
    except Exception as e:
        resultados.append(f"Error al limpiar temporales: {str(e)}")

    # 2. Vaciar papelera de reciclaje (solo notificar)
    try:
        papelera = os.path.join(os.environ.get("SYSTEMDRIVE", "C:"), "$Recycle.Bin")
        if os.path.exists(papelera):
            resultados.append("Papelera de reciclaje: se recomienda vaciar manualmente")
    except Exception:
        pass

    # 3. Limpiar cache de DNS
    try:
        subprocess.run(["ipconfig", "/flushdns"], capture_output=True, timeout=10)
        resultados.append("Cache de DNS limpiado")
    except Exception:
        pass

    # 4. Verificar disco
    try:
        disco = psutil.disk_usage("C:\\")
        if disco.percent > 90:
            resultados.append("ALERTA: Disco C: casi lleno, se recomienda liberar espacio")
    except Exception:
        pass

    if not resultados:
        resultados.append("No se encontraron optimizaciones pendientes")

    return resultados


def optimizar_linux():
    """Optimizaciones para Linux"""
    resultados = []

    # Limpiar archivos temporales viejos
    try:
        temp_dir = "/tmp"
        archivos_eliminados = 0
        for item in os.listdir(temp_dir):
            ruta = os.path.join(temp_dir, item)
            try:
                if os.path.isfile(ruta):
                    edad = time.time() - os.path.getmtime(ruta)
                    if edad > 86400 * 7:  # Mas de 7 dias
                        os.remove(ruta)
                        archivos_eliminados += 1
            except (PermissionError, OSError):
                pass

        if archivos_eliminados > 0:
            resultados.append(f"Archivos temporales eliminados: {archivos_eliminados}")
    except Exception:
        pass

    # Limpiar cache del sistema
    try:
        subprocess.run(["sync"], capture_output=True, timeout=5)
        resultados.append("Cache del sistema sincronizado")
    except Exception:
        pass

    if not resultados:
        resultados.append("No se encontraron optimizaciones pendientes")

    return resultados


# ============================================================
# ENVIO AL SERVIDOR
# ============================================================

def enviar_al_servidor(config, reporte):
    """Enviar reporte al servidor PHP"""
    url = config["servidor"]["url"]
    api_key = config["servidor"]["api_key"]

    headers = {
        "Content-Type": "application/json",
        "X-API-Key": api_key
    }

    try:
        print(f"\nEnviando reporte a: {url}")
        response = requests.post(url, json=reporte, headers=headers, timeout=30)

        if response.status_code == 200:
            data = response.json()
            print("Reporte enviado exitosamente!")

            if data.get("data", {}).get("ticket"):
                ticket = data["data"]["ticket"]
                print(f"  Ticket creado: {ticket.get('codigo', 'N/A')}")

            if data.get("data", {}).get("nivel_urgencia"):
                print(f"  Nivel de urgencia: {data['data']['nivel_urgencia']}")

            if data.get("data", {}).get("resumen_ia"):
                print(f"\n  Resumen IA: {data['data']['resumen_ia'][:200]}...")

            return data
        else:
            print(f"Error del servidor: HTTP {response.status_code}")
            try:
                error_data = response.json()
                print(f"  Detalle: {error_data.get('message', 'Sin detalle')}")
            except Exception:
                print(f"  Respuesta: {response.text[:200]}")
            return None

    except requests.exceptions.ConnectionError:
        print(f"Error: No se puede conectar al servidor ({url})")
        print("  Verifica que el servidor este funcionando.")
        return None
    except requests.exceptions.Timeout:
        print("Error: El servidor no respondio a tiempo.")
        return None
    except Exception as e:
        print(f"Error al enviar: {str(e)}")
        return None


def guardar_reporte_local(config, reporte):
    """Guardar reporte como archivo JSON local"""
    directorio = config["opciones"].get("directorio_reportes", "./reportes")
    os.makedirs(directorio, exist_ok=True)

    nombre = f"reporte_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
    ruta = os.path.join(directorio, nombre)

    with open(ruta, "w", encoding="utf-8") as f:
        json.dump(reporte, f, indent=2, ensure_ascii=False)

    print(f"Reporte guardado en: {ruta}")
    return ruta


# ============================================================
# ASISTENTE DE CONFIGURACION
# ============================================================

def asistente_configuracion():
    """Guiar al usuario para configurar el agente"""
    print("\n=== CONFIGURACION DEL AGENTE DE SOPORTE ===\n")

    config = cargar_configuracion() or {
        "servidor": {"url": "", "api_key": ""},
        "cliente": {"nombre": "", "telefono": "", "email": ""},
        "opciones": {
            "optimizar_automaticamente": True,
            "intervalo_minutos": 0,
            "enviar_al_servidor": True,
            "guardar_reporte_local": True,
            "directorio_reportes": "./reportes"
        }
    }

    print("1. Configuracion del servidor")
    url = input(f"   URL del servidor [{config['servidor']['url']}]: ").strip()
    if url:
        config["servidor"]["url"] = url

    api_key = input(f"   API Key [{config['servidor']['api_key'][:5]}...]: ").strip()
    if api_key:
        config["servidor"]["api_key"] = api_key

    print("\n2. Datos del cliente")
    nombre = input(f"   Nombre [{config['cliente']['nombre']}]: ").strip()
    if nombre:
        config["cliente"]["nombre"] = nombre

    telefono = input(f"   Telefono [{config['cliente']['telefono']}]: ").strip()
    if telefono:
        config["cliente"]["telefono"] = telefono

    email = input(f"   Email [{config['cliente']['email']}]: ").strip()
    if email:
        config["cliente"]["email"] = email

    print("\n3. Opciones")
    opt = input("   Optimizar automaticamente? (s/n) [s]: ").strip().lower()
    if opt == "n":
        config["opciones"]["optimizar_automaticamente"] = False

    guardar_configuracion(config)
    print("\nConfiguracion guardada exitosamente!")
    return config


# ============================================================
# PROGRAMA PRINCIPAL
# ============================================================

def ejecutar_diagnostico(solo_diagnostico=False, solo_reporte=False):
    """Ejecutar el flujo completo de diagnostico"""
    config = cargar_configuracion()
    if not config:
        return

    print("=" * 50)
    print(f"  AGENTE DE SOPORTE TECNICO v{VERSION}")
    print(f"  {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("=" * 50)

    # Diagnostico
    print("\n[1/5] Analizando sistema...")
    sistema = diagnosticar_sistema()

    print("[2/5] Analizando CPU...")
    cpu = diagnosticar_cpu()
    print(f"       CPU: {cpu['modelo']} - {cpu['uso_porcentaje']}% uso")

    print("[3/5] Analizando RAM...")
    ram = diagnosticar_ram()
    print(f"       RAM: {ram['usada_gb']}GB / {ram['total_gb']}GB ({ram['uso_porcentaje']}%)")

    print("[4/5] Analizando Disco...")
    disco = diagnosticar_disco()
    print(f"       Disco: {disco['usado_gb']}GB / {disco['total_gb']}GB ({disco['uso_porcentaje']}%)")

    print("[5/5] Analizando procesos y red...")
    procesos = diagnosticar_procesos()
    red = diagnosticar_red()
    temperatura = diagnosticar_temperatura()

    # Detectar problemas
    problemas, recomendaciones = detectar_problemas(cpu, ram, disco, procesos, temperatura, red)

    print(f"\n--- Problemas detectados: {len(problemas)} ---")
    for p in problemas:
        print(f"  [!] {p}")

    if not problemas:
        print("  [OK] No se detectaron problemas criticos")

    # Optimizacion
    optimizaciones = []
    if not solo_diagnostico and config["opciones"].get("optimizar_automaticamente", True):
        print("\n--- Ejecutando optimizaciones ---")
        optimizaciones = optimizar_equipo()
        for o in optimizaciones:
            print(f"  > {o}")

    # Construir reporte JSON
    reporte = {
        "cliente": config["cliente"],
        "sistema": sistema,
        "cpu": cpu,
        "ram": ram,
        "disco": disco,
        "temperatura_cpu": temperatura,
        "procesos_activos": procesos.get("total_activos", 0),
        "procesos_alto_consumo": procesos.get("alto_consumo", []),
        "red": red,
        "problemas": problemas,
        "recomendaciones": recomendaciones,
        "optimizaciones": optimizaciones,
        "agente_version": VERSION,
        "fecha": datetime.now().isoformat()
    }

    # Guardar localmente
    if config["opciones"].get("guardar_reporte_local", True):
        print("\n--- Guardando reporte local ---")
        guardar_reporte_local(config, reporte)

    # Enviar al servidor
    if not solo_reporte and config["opciones"].get("enviar_al_servidor", True):
        print("\n--- Enviando al servidor ---")
        resultado = enviar_al_servidor(config, reporte)
        if resultado:
            print("\nProceso completado exitosamente!")
        else:
            print("\nDiagnostico completado, pero no se pudo enviar al servidor.")
            print("El reporte fue guardado localmente.")
    else:
        print("\nDiagnostico completado (modo local).")

    return reporte


def main():
    args = sys.argv[1:]

    if "--configurar" in args:
        asistente_configuracion()
    elif "--solo-diagnostico" in args:
        ejecutar_diagnostico(solo_diagnostico=True)
    elif "--solo-reporte" in args:
        ejecutar_diagnostico(solo_reporte=True)
    elif "--version" in args:
        print(f"Agente de Soporte Tecnico v{VERSION}")
    elif "--ayuda" in args or "--help" in args:
        print(__doc__)
    else:
        ejecutar_diagnostico()


if __name__ == "__main__":
    main()
