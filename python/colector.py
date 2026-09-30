"""
Colector de métricas del sistema (CPU, RAM, Disco).

Qué hace: lee el uso de CPU, RAM y disco del equipo donde corre, y
envía una lectura periódica al Backend.

Cómo se comunica con el Backend: POST a {BACKEND_URL} con JSON
{ id_host, mac, cpu, ram, disco }. El backend verifica que id_host +
mac coincidan con un host ya registrado (tabla `hosts`) antes de
guardar la lectura; por eso el propio colector calcula su MAC real
con uuid.getnode() en vez de recibirla como variable de entorno.

Uso en un equipo REAL (sin Docker), para monitorear la propia máquina:
  1. pip install -r requirements.txt
  2. python colector.py --info      (muestra el nombre, IP y MAC que hay
                                     que capturar en la pantalla "Equipos")
  3. Registrar el equipo en el sitio web; ahí aparece su ID_HOST.
  4. Correr el colector con ese ID (ver README):
       ID_HOST=4 BACKEND_URL=http://IP-DEL-SERVIDOR:8080/metricas/ingresar.php python colector.py
     (en PowerShell:  $env:ID_HOST="4"; $env:BACKEND_URL="http://IP:8080/metricas/ingresar.php"; python colector.py)

Variables de entorno usadas (definidas en docker-compose.yml para los
colectores simulados; en un equipo real se pasan a mano):
  BACKEND_URL   -> ej. http://backend/metricas/ingresar.php
  ID_HOST       -> id_host que este colector reporta (debe existir en `hosts`)
  NOMBRE_EQUIPO -> solo para los mensajes de consola, informativo
  INTERVALO_SEG -> segundos entre cada lectura (por defecto 5)
"""
import os
import socket
import sys
import time
import uuid

import psutil
import requests

BACKEND_URL = os.environ.get('BACKEND_URL', 'http://localhost:8080/metricas/ingresar.php')
ID_HOST = os.environ.get('ID_HOST')
NOMBRE_EQUIPO = os.environ.get('NOMBRE_EQUIPO', 'equipo')
INTERVALO_SEG = float(os.environ.get('INTERVALO_SEG', '5'))


def obtener_mac() -> str:
    """Devuelve la MAC real de este contenedor, formateada como aa:bb:cc:dd:ee:ff."""
    nodo = uuid.getnode()
    return ':'.join(f'{(nodo >> despl) & 0xff:02x}' for despl in range(40, -8, -8))


def obtener_ip() -> str:
    """IP local de esta máquina en la red (la que se usa para salir a internet/LAN)."""
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as sock:
            sock.connect(('10.255.255.255', 1))  # no envía nada, solo elige la interfaz
            return sock.getsockname()[0]
    except OSError:
        return '127.0.0.1'


def mostrar_info() -> None:
    """Imprime los datos que se piden al registrar el equipo en el sitio web."""
    print('Datos para registrar este equipo en la pantalla "Equipos":')
    print(f'  Nombre : {socket.gethostname()}')
    print(f'  IP     : {obtener_ip()}')
    print(f'  MAC    : {obtener_mac()}')


def leer_metricas():
    cpu = psutil.cpu_percent(interval=1)
    ram = psutil.virtual_memory().percent
    disco = psutil.disk_usage('/').percent
    return round(cpu, 2), round(ram, 2), round(disco, 2)


def enviar_lectura(mac, cpu, ram, disco):
    body = {'id_host': int(ID_HOST), 'mac': mac, 'cpu': cpu, 'ram': ram, 'disco': disco}
    try:
        resp = requests.post(BACKEND_URL, json=body, timeout=5)
        data = resp.json()
        if data.get('estado') == 'bien':
            print(f'[{NOMBRE_EQUIPO}] OK  cpu={cpu}% ram={ram}% disco={disco}%')
        else:
            print(f'[{NOMBRE_EQUIPO}] Backend respondio error: {data.get("error")}')
    except requests.RequestException as e:
        print(f'[{NOMBRE_EQUIPO}] No se pudo contactar al backend: {e}', file=sys.stderr)


def main():
    if '--info' in sys.argv:
        mostrar_info()
        return

    if not ID_HOST:
        print('[colector] ERROR: falta la variable de entorno ID_HOST. '
              'Registra el equipo en el sitio web y usa el ID que ahí aparece '
              '(python colector.py --info muestra los datos a capturar).', file=sys.stderr)
        sys.exit(1)

    mac = obtener_mac()
    print(f'[{NOMBRE_EQUIPO}] Colector iniciado (id_host={ID_HOST}, mac={mac}). '
          f'Enviando a {BACKEND_URL} cada {INTERVALO_SEG}s')
    while True:
        cpu, ram, disco = leer_metricas()
        enviar_lectura(mac, cpu, ram, disco)
        time.sleep(INTERVALO_SEG)


if __name__ == '__main__':
    main()
