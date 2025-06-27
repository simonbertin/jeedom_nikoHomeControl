# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.

import logging
import sys
import os
import time
import traceback
import signal
import json
import argparse
import asyncio
import threading
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse, parse_qs
import requests

from jeedom.jeedom import jeedom_socket, jeedom_utils, jeedom_com, JEEDOM_SOCKET_MESSAGE  # jeedom_serial

# Import des dépendances Niko
try:
    from nhc2_coco import CocoClient
except ImportError:
    print("ERREUR: nhc2-coco n'est pas installé. Exécutez: pip3 install nhc2-coco")
    sys.exit(1)

# Configuration du logging
logging.basicConfig()
logger = logging.getLogger('nikohomecontrol2')


class NikoHomeControl2Daemon:
    def __init__(self, socketport, callback, apikey):
        self.socketport = socketport
        self.callback = callback
        self.apikey = apikey
        self.coco_client = None
        self.devices = {}
        self.running = False
        
        # Configuration Niko (sera récupérée depuis Jeedom)
        self.niko_host = None
        self.niko_port = 8884
        self.niko_username = "hobby"
        self.niko_password = ""
        
    def load_config_from_jeedom(self):
        """Récupère la configuration depuis Jeedom"""
        try:
            # Appel à l'API Jeedom pour récupérer la config
            config_url = f"{self.callback}?apikey={self.apikey}&action=getConfig"
            response = requests.get(config_url, timeout=10)
            
            if response.status_code == 200:
                config = response.json()
                self.niko_host = config.get('host', '')
                self.niko_port = int(config.get('port', 8884))
                self.niko_username = config.get('username', 'hobby')
                self.niko_password = config.get('password', '')
                
                logger.info(f"Configuration chargée: {self.niko_host}:{self.niko_port}")
                return True
            else:
                logger.error(f"Erreur lors du chargement de la config: {response.status_code}")
                return False
                
        except Exception as e:
            logger.error(f"Erreur lors du chargement de la configuration: {e}")
            return False
    
    async def connect_to_niko(self):
        """Connexion au contrôleur Niko"""
        try:
            if not self.niko_host:
                logger.error("Adresse IP du contrôleur non configurée")
                return False
                
            logger.info(f"Connexion à Niko Home Control II sur {self.niko_host}:{self.niko_port}")
            
            # Initialisation du client CocoClient
            self.coco_client = CocoClient(
                ip=self.niko_host,
                port=self.niko_port,
                username=self.niko_username,
                password=self.niko_password
            )
            
            # Connexion
            await self.coco_client.connect()
            
            # Récupération des équipements
            await self.discover_devices()
            
            # Démarrage de l'écoute des événements
            self.coco_client.on_device_update = self.on_device_update
            
            logger.info("Connexion établie avec succès")
            return True
            
        except Exception as e:
            logger.error(f"Erreur lors de la connexion à Niko: {e}")
            return False
    
    async def discover_devices(self):
        """Découverte des équipements"""
        try:
            devices = await self.coco_client.list_devices()
            
            for device in devices:
                if device.type == 'rollershutter':  # Volets
                    self.devices[device.uuid] = {
                        'id': device.uuid,
                        'name': device.name,
                        'type': 'cover',
                        'position': device.position,
                        'state': device.state
                    }
                    
                    # Notification à Jeedom
                    await self.notify_jeedom_device_discovered(device)
            
            logger.info(f"Découverte terminée: {len(self.devices)} équipements trouvés")
            
        except Exception as e:
            logger.error(f"Erreur lors de la découverte: {e}")
    
    async def notify_jeedom_device_discovered(self, device):
        """Notifie Jeedom qu'un nouvel équipement a été découvert"""
        try:
            data = {
                'apikey': self.apikey,
                'action': 'deviceDiscovered',
                'device': {
                    'niko_id': device.uuid,
                    'name': device.name,
                    'type': 'cover'
                }
            }
            
            response = requests.post(self.callback, json=data, timeout=10)
            if response.status_code != 200:
                logger.warning(f"Erreur notification Jeedom: {response.status_code}")
                
        except Exception as e:
            logger.error(f"Erreur notification Jeedom: {e}")
    
    def on_device_update(self, device):
        """Callback appelé lors d'une mise à jour d'équipement"""
        try:
            if device.uuid in self.devices:
                self.devices[device.uuid]['position'] = device.position
                self.devices[device.uuid]['state'] = device.state
                
                # Notification à Jeedom
                asyncio.create_task(self.notify_jeedom_device_update(device))
                
        except Exception as e:
            logger.error(f"Erreur lors de la mise à jour: {e}")
    
    async def notify_jeedom_device_update(self, device):
        """Notifie Jeedom d'une mise à jour d'équipement"""
        try:
            data = {
                'apikey': self.apikey,
                'action': 'deviceUpdate',
                'niko_id': device.uuid,
                'position': device.position,
                'state': device.state
            }
            
            response = requests.post(self.callback, json=data, timeout=10)
            if response.status_code != 200:
                logger.warning(f"Erreur notification update Jeedom: {response.status_code}")
                
        except Exception as e:
            logger.error(f"Erreur notification update Jeedom: {e}")
    
    async def execute_command(self, niko_id, command, value=None):
        """Exécute une commande sur un équipement Niko"""
        try:
            if not self.coco_client:
                raise Exception("Client Niko non connecté")
            
            device = await self.coco_client.get_device(niko_id)
            if not device:
                raise Exception(f"Équipement {niko_id} non trouvé")
            
            if command == 'open':
                await device.open()
            elif command == 'close':
                await device.close()
            elif command == 'stop':
                await device.stop()
            elif command == 'setPosition':
                if value is not None:
                    await device.set_position(int(value))
                else:
                    raise Exception("Valeur de position manquante")
            else:
                raise Exception(f"Commande inconnue: {command}")
            
            logger.info(f"Commande {command} exécutée sur {niko_id}")
            return True
            
        except Exception as e:
            logger.error(f"Erreur exécution commande: {e}")
            raise e

class HTTPRequestHandler(BaseHTTPRequestHandler):
    def __init__(self, daemon, *args, **kwargs):
        self.daemon = daemon
        super().__init__(*args, **kwargs)
    
    def do_POST(self):
        try:
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            data = json.loads(post_data.decode('utf-8'))
            
            action = data.get('action')
            
            if action == 'executeCommand':
                niko_id = data.get('niko_id')
                command = data.get('command')
                value = data.get('value')
                
                # Exécution asynchrone de la commande
                loop = asyncio.new_event_loop()
                asyncio.set_event_loop(loop)
                result = loop.run_until_complete(
                    self.daemon.execute_command(niko_id, command, value)
                )
                loop.close()
                
                response = {'success': True, 'result': result}
            else:
                response = {'success': False, 'error': 'Action inconnue'}
            
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(response).encode())
            
        except Exception as e:
            logger.error(f"Erreur HTTP: {e}")
            response = {'success': False, 'error': str(e)}
            self.send_response(500)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(response).encode())
    
    def log_message(self, format, *args):
        # Désactiver les logs HTTP par défaut
        pass

def signal_handler(signum, frame):
    logger.info("Signal reçu, arrêt du démon")
    sys.exit(0)

def main():
    parser = argparse.ArgumentParser(description='Démon Niko Home Control II pour Jeedom')
    parser.add_argument('--loglevel', default='INFO', help='Niveau de log')
    parser.add_argument('--socketport', type=int, default=55002, help='Port du socket')
    parser.add_argument('--callback', required=True, help='URL de callback Jeedom')
    parser.add_argument('--apikey', required=True, help='Clé API Jeedom')
    parser.add_argument('--pid', help='Fichier PID')
    
    args = parser.parse_args()
    
    # Configuration du logging
    numeric_level = getattr(logging, args.loglevel.upper(), None)
    if not isinstance(numeric_level, int):
        raise ValueError(f'Niveau de log invalide: {args.loglevel}')
    logger.setLevel(numeric_level)
    
    # Écriture du PID
    if args.pid:
        with open(args.pid, 'w') as f:
            f.write(str(os.getpid()))
    
    # Gestion des signaux
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)
    
    # Initialisation du démon
    daemon = NikoHomeControl2Daemon(args.socketport, args.callback, args.apikey)
    
    # Chargement de la configuration
    if not daemon.load_config_from_jeedom():
        logger.error("Impossible de charger la configuration")
        sys.exit(1)
    
    # Démarrage du serveur HTTP
    handler = lambda *args, **kwargs: HTTPRequestHandler(daemon, *args, **kwargs)
    httpd = HTTPServer(('127.0.0.1', args.socketport), handler)
    
    logger.info(f"Serveur HTTP démarré sur le port {args.socketport}")
    
    # Connexion à Niko en arrière-plan
    def connect_niko():
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        loop.run_until_complete(daemon.connect_to_niko())
        loop.run_forever()
    
    niko_thread = threading.Thread(target=connect_niko)
    niko_thread.daemon = True
    niko_thread.start()
    
    # Démarrage du serveur
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        logger.info("Arrêt du démon")
    finally:
        httpd.server_close()

if __name__ == '__main__':
    main()

# def read_socket():
#     if not JEEDOM_SOCKET_MESSAGE.empty():
#         logging.debug("Message received in socket JEEDOM_SOCKET_MESSAGE")
#         message = json.loads(jeedom_utils.stripped(JEEDOM_SOCKET_MESSAGE.get()))
#         if message['apikey'] != _apikey:
#             logging.error("Invalid apikey from socket: %s", message)
#             return
#         try:
#             print('read')
#         except Exception as e:
#             logging.error('Send command to demon error: %s', e)


# def listen():
#     my_jeedom_socket.open()
#     try:
#         while 1:
#             time.sleep(0.5)
#             read_socket()
#     except KeyboardInterrupt:
#         shutdown()


# def handler(signum=None, frame=None):
#     logging.debug("Signal %i caught, exiting...", int(signum))
#     shutdown()


# def shutdown():
#     logging.debug("Shutdown")
#     logging.debug("Removing PID file %s", _pidfile)
#     try:
#         os.remove(_pidfile)
#     except Exception as e:
#         logging.warning('Error removing PID file: %s', e)
#     try:
#         my_jeedom_socket.close()
#     except Exception as e:
#         logging.warning('Error closing socket: %s', e)
#     # try:  # if you need jeedom_serial
#     #     my_jeedom_serial.close()
#     # except Exception as e:
#     #     logging.warning('Error closing serial: %s', e)
#     logging.debug("Exit 0")
#     sys.stdout.flush()
#     os._exit(0)


# _log_level = "error"
# _socket_port = 55009
# _socket_host = 'localhost'
# _device = 'auto'
# _pidfile = '/tmp/demond.pid'
# _apikey = ''
# _callback = ''
# _cycle = 0.3

# parser = argparse.ArgumentParser(description='Desmond Daemon for Jeedom plugin')
# parser.add_argument("--device", help="Device", type=str)
# parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
# parser.add_argument("--callback", help="Callback", type=str)
# parser.add_argument("--apikey", help="Apikey", type=str)
# parser.add_argument("--cycle", help="Cycle to send event", type=float)
# parser.add_argument("--pid", help="Pid file", type=str)
# parser.add_argument("--socketport", help="Port for socket server", type=int)
# args = parser.parse_args()

# if args.device:
#     _device = args.device
# if args.loglevel:
#     _log_level = args.loglevel
# if args.callback:
#     _callback = args.callback
# if args.apikey:
#     _apikey = args.apikey
# if args.pid:
#     _pidfile = args.pid
# if args.cycle:
#     _cycle = float(args.cycle)
# if args.socketport:
#     _socket_port = args.socketport

# _socket_port = int(_socket_port)

# jeedom_utils.set_log_level(_log_level)

# logging.info('Start demond')
# logging.info('Log level: %s', _log_level)
# logging.info('Socket port: %s', _socket_port)
# logging.info('Socket host: %s', _socket_host)
# logging.info('PID file: %s', _pidfile)
# logging.info('Apikey: %s', _apikey)
# logging.info('Device: %s', _device)

# signal.signal(signal.SIGINT, handler)
# signal.signal(signal.SIGTERM, handler)

# try:
#     jeedom_utils.write_pid(str(_pidfile))
#     my_jeedom_com = jeedom_com(apikey=_apikey, url=_callback, cycle=_cycle)
#     if not my_jeedom_com.test():
#         logging.error('Network communication issues. Please fixe your Jeedom network configuration.')
#         shutdown()
#     # my_jeedom_serial = jeedom_serial(device=_device)  # if you need jeedom_serial
#     my_jeedom_socket = jeedom_socket(port=_socket_port, address=_socket_host)
#     listen()
# except Exception as e:
#     logging.error('Fatal error: %s', e)
#     logging.info(traceback.format_exc())
#     shutdown()
