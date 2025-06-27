<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

require_once __DIR__  . '/../../../../core/php/core.inc.php';
/*
*
* Fichier d’inclusion si vous avez plusieurs fichiers de class ou 3rdParty à inclure
*
*/


if (!jeedom::apiAccess(init('apikey'), 'nikohomecontrol2')) {
    echo __('Vous n\'etes pas autorisé à effectuer cette action', __FILE__);
    die();
}

$action = init('action');

switch ($action) {
    case 'getConfig':
        // Retourne la configuration du plugin
        $config = array(
            'host' => config::byKey('host', 'nikohomecontrol2'),
            'port' => config::byKey('port', 'nikohomecontrol2', '8884'),
            'username' => config::byKey('username', 'nikohomecontrol2', 'hobby'),
            'password' => config::byKey('password', 'nikohomecontrol2')
        );
        echo json_encode($config);
        break;
        
    case 'deviceDiscovered':
        // Traitement de la découverte d'un nouvel équipement
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['device'])) {
            $device = $input['device'];
            $niko_id = $device['niko_id'];
            $name = $device['name'];
            $type = $device['type'];
            
            // Vérifier si l'équipement existe déjà
            $existing = nikohomecontrol2::byLogicalId($niko_id, 'nikohomecontrol2');
            
            if (!is_object($existing)) {
                // Créer un nouvel équipement
                $eqLogic = new nikohomecontrol2();
                $eqLogic->setLogicalId($niko_id);
                $eqLogic->setName($name);
                $eqLogic->setConfiguration('niko_id', $niko_id);
                $eqLogic->setConfiguration('device_type', $type);
                $eqLogic->setIsEnable(1);
                $eqLogic->setIsVisible(1);
                $eqLogic->save();
                
                log::add('nikohomecontrol2', 'info', 'Nouvel équipement découvert : ' . $name . ' (' . $niko_id . ')');
            }
        }
        
        echo json_encode(array('success' => true));
        break;
        
    case 'deviceUpdate':
        // Mise à jour des valeurs d'un équipement
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['niko_id'])) {
            $niko_id = $input['niko_id'];
            $position = $input['position'] ?? null;
            $state = $input['state'] ?? null;
            
            // Trouver l'équipement
            $eqLogic = nikohomecontrol2::byLogicalId($niko_id, 'nikohomecontrol2');
            
            if (is_object($eqLogic)) {
                // Mise à jour de la position
                if ($position !== null) {
                    $cmd = $eqLogic->getCmd(null, 'position');
                    if (is_object($cmd)) {
                        $cmd->execCmd(null, $position);
                    }
                }
                
                // Mise à jour de l'état
                if ($state !== null) {
                    $cmd = $eqLogic->getCmd(null, 'state');
                    if (is_object($cmd)) {
                        $cmd->execCmd(null, $state);
                    }
                }
                
                log::add('nikohomecontrol2', 'debug', 'Mise à jour équipement ' . $niko_id . ' : position=' . $position . ', état=' . $state);
            }
        }
        
        echo json_encode(array('success' => true));
        break;
        
    default:
        echo json_encode(array('success' => false, 'error' => 'Action inconnue'));
        break;
}
?>