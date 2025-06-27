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

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

  /* Fonction permettant l'envoi de l'entête 'Content-Type: application/json'
    En V3 : indiquer l'argument 'true' pour contrôler le token d'accès Jeedom
    En V4 : autoriser l'exécution d'une méthode 'action' en GET en indiquant le(s) nom(s) de(s) action(s) dans un tableau en argument
  */
    ajax::init();

 if (init('action') == 'syncWithNiko') {
        // Synchronisation avec Niko Home Control
        try {
            nikohomecontrol2::syncWithNiko();
            ajax::success('Synchronisation terminée');
        } catch (Exception $e) {
            ajax::error($e->getMessage());
        }
    }

    if (init('action') == 'testConnection') {
      // Test de connexion
      try {
        $host = config::byKey('host', 'nikohomecontrol2');
        $port = config::byKey('port', 'nikohomecontrol2', '8884');
        
        if (empty($host)) {
            throw new Exception(__('Adresse IP non configurée', __FILE__));
        }

        // Test de connexion simple
        $connection = @fsockopen($host, $port, $errno, $errstr, 10);
        if (!$connection) {
            throw new Exception(__('Impossible de se connecter à ', __FILE__) . $host . ':' . $port . ' - ' . $errstr);
        }
        fclose($connection);
        
        ajax::success('Connexion réussie');
      } catch (Exception $e) {
          ajax::error($e->getMessage());
      }
  }

  if (init('action') == 'getDeviceInfo') {
    // Récupération des informations d'un équipement
    try {
      $eqLogic = eqLogic::byId(init('id'));
      if (!is_object($eqLogic)) {
        throw new Exception(__('Équipement introuvable', __FILE__));
      }

      $info = array(
        'name' => $eqLogic->getName(),
        'niko_id' => $eqLogic->getConfiguration('niko_id'),
        'device_type' => $eqLogic->getConfiguration('device_type'),
        'commands' => array()
      );

      foreach ($eqLogic->getCmd() as $cmd) {
        $info['commands'][] = array(
          'name' => $cmd->getName(),
          'type' => $cmd->getType(),
          'subType' => $cmd->getSubType(),
          'logicalId' => $cmd->getLogicalId(),
          'value' => $cmd->execCmd()
        );
      }

      ajax::success($info);
    } catch (Exception $e) {
      ajax::error($e->getMessage());
    }
  }

  if (init('action') == 'getDaemonStatus') {
    // Statut du démon
    try {
      $deamon_info = nikohomecontrol2::deamon_info();
      ajax::success($deamon_info);
    } catch (Exception $e) {
      ajax::error($e->getMessage());
    }
  }

  if (init('action') == 'startDaemon') {
    // Démarrage du démon
    try {
      $result = nikohomecontrol2::deamon_start();
      if ($result) {
          ajax::success('Démon démarré');
      } else {
          ajax::error('Erreur lors du démarrage du démon');
      }
    } catch (Exception $e) {
      ajax::error($e->getMessage());
    }
  }

  if (init('action') == 'stopDaemon') {
    // Arrêt du démon
    try {
      nikohomecontrol2::deamon_stop();
      ajax::success('Démon arrêté');
    } catch (Exception $e) {
      ajax::error($e->getMessage());
    }
  }

  if (init('action') == 'restartDaemon') {
    // Redémarrage du démon
    try {
      nikohomecontrol2::deamon_stop();
      sleep(2);
      $result = nikohomecontrol2::deamon_start();
      if ($result) {
        ajax::success('Démon redémarré');
      } else {
        ajax::error('Erreur lors du redémarrage du démon');
      }
    } catch (Exception $e) {
        ajax::error($e->getMessage());
    }
  }

  throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . init('action'));
  /*     * *********Catch exeption*************** */
}
catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
