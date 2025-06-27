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

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class nhc extends eqLogic {
  /*     * *************************Attributs****************************** */

  /*
  * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
  * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
  public static $_widgetPossibility = array();
  */

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration du plugin
  * Exemple : "param1" & "param2" seront cryptés mais pas "param3"
  public static $_encryptConfigKey = array('param1', 'param2');
  */

  /*     * ***********************Methode static*************************** */

  /*
  * Fonction exécutée automatiquement toutes les minutes par Jeedom
  public static function cron() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
  public static function cron5() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
  public static function cron10() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
  public static function cron15() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
  public static function cron30() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les heures par Jeedom
  public static function cronHourly() {}
  */

  /*
  * Fonction exécutée automatiquement tous les jours par Jeedom
  public static function cronDaily() {}
  */
  
  /*
  * Permet de déclencher une action avant modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function preConfig_param3( $value ) {
    // do some checks or modify on $value
    return $value;
  }
  */

  /*
  * Permet de déclencher une action après modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function postConfig_param3($value) {
    // no return value
  }
  */

  /*
   * Permet d'indiquer des éléments supplémentaires à remonter dans les informations de configuration
   * lors de la création semi-automatique d'un post sur le forum community
   public static function getConfigForCommunity() {
      // Cette function doit retourner des infos complémentataires sous la forme d'un
      // string contenant les infos formatées en HTML.
      return "les infos essentiel de mon plugin";
   }
   */


    public static function dependancy_info() {
        $return = array();
        $return['log'] = log::getPathToLog(__CLASS__ . '_update');
        $return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependance';
        
        $pip3 = "pip3";
        if (shell_exec('which pip3')) {
            $pip3 = "pip3";
        } elseif (shell_exec('which pip')) {
            $pip3 = "pip";
        }
        
        // Vérification de la dépendance nhc2-coco
        $nhc2_installed = shell_exec($pip3 . ' list | grep nhc2-coco');
        
        if ($nhc2_installed) {
            $return['state'] = 'ok';
        } else {
            $return['state'] = 'nok';
        }
        
        return $return;
    }

    public static function dependancy_install() {
        log::remove(__CLASS__ . '_update');
        return array('script' => dirname(__FILE__) . '/../../resources/install_apt.sh ' . jeedom::getTmpFolder(__CLASS__) . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
    }

    public static function deamon_info() {
        $return = array();
        $return['log'] = __CLASS__;
        $return['state'] = 'nok';
        
        $pid_file = jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
        if (file_exists($pid_file)) {
            if (@posix_getsid(trim(file_get_contents($pid_file)))) {
                $return['state'] = 'ok';
            } else {
                shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
            }
        }
        
        $return['launchable'] = 'ok';
        return $return;
    }

    public static function deamon_start() {
        self::deamon_stop();
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok') {
            throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
        }

        $path = realpath(dirname(__FILE__) . '/../../resources');
        $cmd = system::getCmdPython3(__CLASS__) . " {$path}/nikohomecontrold.py";
        $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));
        $cmd .= ' --socketport ' . config::byKey('socketport', __CLASS__, 55002);
        $cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/nikohomecontrol2/core/php/jeeNikoHC2.php';
        $cmd .= ' --apikey ' . jeedom::getApiKey(__CLASS__);
        $cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
        
        log::add(__CLASS__, 'info', 'Lancement démon');
        $result = exec($cmd . ' >> ' . log::getPathToLog(__CLASS__ . '_daemon') . ' 2>&1 &');
        $i = 0;
        while ($i < 30) {
            $deamon_info = self::deamon_info();
            if ($deamon_info['state'] == 'ok') {
                break;
            }
            sleep(1);
            $i++;
        }
        if ($i >= 30) {
            log::add(__CLASS__, 'error', __('Impossible de lancer le démon, vérifiez le log', __FILE__), 'unableStartDeamon');
            return false;
        }
        message::removeAll(__CLASS__, 'unableStartDeamon');
        return true;
    }

    public static function deamon_stop() {
        $pid_file = jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
        if (file_exists($pid_file)) {
            $pid = intval(trim(file_get_contents($pid_file)));
            system::kill($pid);
        }
        system::kill('nikohomecontrold.py');
        system::fuserk(config::byKey('socketport', __CLASS__, 55002));
        sleep(1);
    }

    public static function syncWithNikoHC() {
        foreach (eqLogic::byType(__CLASS__, true) as $eqLogic) {
            $eqLogic->getInformationsFromNiko();
        }
    }
  /*     * *********************Méthodes d'instance************************* */

  // Fonction exécutée automatiquement avant la création de l'équipement
    public function preInsert() {
        $this->setCategory('automatism', 1);
    }

  // Fonction exécutée automatiquement après la création de l'équipement
  public function postInsert() {
  }

  // Fonction exécutée automatiquement avant la mise à jour de l'équipement
  public function preUpdate() {
  }

  // Fonction exécutée automatiquement après la mise à jour de l'équipement
  public function postUpdate() {
  }

  // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
  public function preSave() {
  }

  // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
   public function postSave() {
        $this->getInformationsFromNiko();
    }

  // Fonction exécutée automatiquement avant la suppression de l'équipement
  public function preRemove() {
  }

  // Fonction exécutée automatiquement après la suppression de l'équipement
  public function postRemove() {
  }

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration des équipements
  * Exemple avec le champ "Mot de passe" (password)
  public function decrypt() {
    $this->setConfiguration('password', utils::decrypt($this->getConfiguration('password')));
  }
  public function encrypt() {
    $this->setConfiguration('password', utils::encrypt($this->getConfiguration('password')));
  }
  */

  /*
  * Permet de modifier l'affichage du widget (également utilisable par les commandes)
  public function toHtml($_version = 'dashboard') {}
  */

  public function getInformationsFromNiko() {
        if ($this->getConfiguration('niko_id') == '') {
            return;
        }
        
        $request_http = new com_http(self::getSocketHost() . ':' . config::byKey('socketport', __CLASS__, 55002) . '/');
        try {
            $response = $request_http->exec(30, 1);
        } catch (Exception $e) {
            log::add(__CLASS__, 'debug', __('Erreur lors de la communication avec le démon : ', __FILE__) . $e->getMessage());
            return;
        }
        
        if (!is_json($response)) {
            log::add(__CLASS__, 'debug', __('Réponse du démon invalide : ', __FILE__) . $response);
            return;
        }
        
        $result = json_decode($response, true);
        if (!isset($result['result'])) {
            log::add(__CLASS__, 'debug', __('Réponse du démon sans résultat', __FILE__));
            return;
        }
        
        // Traitement des volets
        if ($this->getConfiguration('device_type') == 'cover') {
            $this->createCoverCommands();
        }
    }

    private function createCoverCommands() {
        // Commande info pour la position
        $cmd = $this->getCmd(null, 'position');
        if (!is_object($cmd)) {
            $cmd = new nikohomecontrol2Cmd();
            $cmd->setLogicalId('position');
            $cmd->setIsVisible(1);
            $cmd->setName(__('Position', __FILE__));
            $cmd->setType('info');
            $cmd->setSubType('numeric');
            $cmd->setUnite('%');
            $cmd->setEqLogic_id($this->getId());
            $cmd->save();
        }

        // Commande info pour l'état (ouvert/fermé)
        $cmd = $this->getCmd(null, 'state');
        if (!is_object($cmd)) {
            $cmd = new nikohomecontrol2Cmd();
            $cmd->setLogicalId('state');
            $cmd->setIsVisible(1);
            $cmd->setName(__('État', __FILE__));
            $cmd->setType('info');
            $cmd->setSubType('string');
            $cmd->setEqLogic_id($this->getId());
            $cmd->save();
        }

        // Commande action pour ouvrir
        $cmd = $this->getCmd(null, 'open');
        if (!is_object($cmd)) {
            $cmd = new nikohomecontrol2Cmd();
            $cmd->setLogicalId('open');
            $cmd->setIsVisible(1);
            $cmd->setName(__('Ouvrir', __FILE__));
            $cmd->setType('action');
            $cmd->setSubType('other');
            $cmd->setEqLogic_id($this->getId());
            $cmd->save();
        }

        // Commande action pour fermer
        $cmd = $this->getCmd(null, 'close');
        if (!is_object($cmd)) {
            $cmd = new nikohomecontrol2Cmd();
            $cmd->setLogicalId('close');
            $cmd->setIsVisible(1);
            $cmd->setName(__('Fermer', __FILE__));
            $cmd->setType('action');
            $cmd->setSubType('other');
            $cmd->setEqLogic_id($this->getId());
            $cmd->save();
        }

        // Commande action pour stop
        $cmd = $this->getCmd(null, 'stop');
        if (!is_object($cmd)) {
            $cmd = new nikohomecontrol2Cmd();
            $cmd->setLogicalId('stop');
            $cmd->setIsVisible(1);
            $cmd->setName(__('Stop', __FILE__));
            $cmd->setType('action');
            $cmd->setSubType('other');
            $cmd->setEqLogic_id($this->getId());
            $cmd->save();
        }

        // Commande action pour position spécifique
        $cmd = $this->getCmd(null, 'setPosition');
        if (!is_object($cmd)) {
            $cmd = new nikohomecontrol2Cmd();
            $cmd->setLogicalId('setPosition');
            $cmd->setIsVisible(1);
            $cmd->setName(__('Définir position', __FILE__));
            $cmd->setType('action');
            $cmd->setSubType('slider');
            $cmd->setConfiguration('minValue', 0);
            $cmd->setConfiguration('maxValue', 100);
            $cmd->setEqLogic_id($this->getId());
            $cmd->save();
        }
    }

    public static function getSocketHost() {
        return '127.0.0.1';
    }


  /*     * **********************Getteur Setteur*************************** */
}

class nhcCmd extends cmd {
  /*     * *************************Attributs****************************** */

  /*
  public static $_widgetPossibility = array();
  */

  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */

  /*
  * Permet d'empêcher la suppression des commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
  public function dontRemoveCmd() {
    return true;
  }
  */

  // Exécution d'une commande
  public function execute($_options = array()) {
    $eqlogic = $this->getEqLogic();
    
    if (!is_object($eqlogic) || $eqlogic->getIsEnable() != 1) {
      throw new Exception(__('Équipement désactivé, impossible d\'exécuter la commande : ' . $this->getHumanName(), __FILE__));
    }

    $niko_id = $eqlogic->getConfiguration('niko_id');
    if ($niko_id == '') {
      throw new Exception(__('ID Niko manquant', __FILE__));
    }

    $action = $this->getLogicalId();
    $value = null;
    
    if (isset($_options['slider'])) {
      $value = $_options['slider'];
    }

    $request = array(
      'action' => 'executeCommand',
      'niko_id' => $niko_id,
      'command' => $action,
      'value' => $value
    );

    $request_http = new com_http(nikohomecontrol2::getSocketHost() . ':' . config::byKey('socketport', 'nikohomecontrol2', 55002) . '/');
    try {
      $response = $request_http->exec(30, 1, json_encode($request));
    } catch (Exception $e) {
      log::add('nikohomecontrol2', 'error', __('Erreur lors de l\'exécution de la commande : ', __FILE__) . $e->getMessage());
      throw $e;
    }

    if (!is_json($response)) {
      throw new Exception(__('Réponse invalide du démon', __FILE__));
    }

    $result = json_decode($response, true);
    if (!isset($result['success']) || $result['success'] !== true) {
      $error = isset($result['error']) ? $result['error'] : 'Erreur inconnue';
      throw new Exception(__('Erreur lors de l\'exécution : ', __FILE__) . $error);
    }

    return $result;
  }

  /*     * **********************Getteur Setteur*************************** */
}
