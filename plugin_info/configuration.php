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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
?>
<form class="form-horizontal">
    <fieldset>
        <legend><i class="fas fa-wrench"></i> {{Configuration Niko Home Control II}}</legend>
        
        <!-- Configuration de connexion -->
        <div class="form-group">
            <label class="col-lg-3 control-label">{{Adresse IP du contrôleur}}</label>
            <div class="col-lg-4">
                <input class="configKey form-control" data-l1key="host" placeholder="192.168.1.100"/>
            </div>
            <div class="col-lg-2">
                <a class="btn btn-success btn-sm" id="bt_testConnection"><i class="fas fa-check"></i> {{Tester}}</a>
            </div>
        </div>
        
        <div class="form-group">
            <label class="col-lg-3 control-label">{{Port}}</label>
            <div class="col-lg-2">
                <input class="configKey form-control" data-l1key="port" placeholder="8884"/>
            </div>
        </div>
        
        <div class="form-group">
            <label class="col-lg-3 control-label">{{Nom d'utilisateur API Hobby}}</label>
            <div class="col-lg-4">
                <input class="configKey form-control" data-l1key="username" placeholder="hobby"/>
            </div>
        </div>
        
        <div class="form-group">
            <label class="col-lg-3 control-label">{{Mot de passe API Hobby}}</label>
            <div class="col-lg-4">
                <input type="password" class="configKey form-control" data-l1key="password" placeholder=""/>
            </div>
        </div>
        
        <hr>
        
        <!-- Configuration du démon -->
        <legend><i class="fas fa-cogs"></i> {{Configuration du démon}}</legend>
        
        <div class="form-group">
            <label class="col-lg-3 control-label">{{Port du socket interne}}</label>
            <div class="col-lg-2">
                <input class="configKey form-control" data-l1key="socketport" placeholder="55002"/>
            </div>
        </div>
        
        <div class="form-group">
            <label class="col-lg-3 control-label">{{Niveau de log du démon}}</label>
            <div class="col-lg-2">
                <select class="configKey form-control" data-l1key="log_level">
                    <option value="error">{{Erreur}}</option>
                    <option value="warning">{{Attention}}</option>
                    <option value="info">{{Info}}</option>
                    <option value="debug">{{Debug}}</option>
                </select>
            </div>
        </div>
        
        <hr>
        
        <!-- Statut du démon -->
        <legend><i class="fas fa-info-circle"></i> {{Statut du démon}}</legend>
        
        <div class="form-group">
            <label class="col-lg-3 control-label">{{Statut}}</label>
            <div class="col-lg-6">
                <span class="label label-info" id="daemon_state">{{Inconnu}}</span>
                <span style="margin-left: 10px;">
                    <a class="btn btn-success btn-sm" id="bt_startDaemon"><i class="fas fa-play"></i> {{Démarrer}}</a>
                    <a class="btn btn-danger btn-sm" id="bt_stopDaemon"><i class="fas fa-stop"></i> {{Arrêter}}</a>
                    <a class="btn btn-warning btn-sm" id="bt_restartDaemon"><i class="fas fa-sync"></i> {{Redémarrer}}</a>
                </span>
            </div>
        </div>
        
        <div class="form-group">
            <label class="col-lg-3 control-label">{{Lancement automatique}}</label>
            <div class="col-lg-2">
                <label class="checkbox-inline">
                    <input type="checkbox" class="configKey" data-l1key="auto_start" checked/>
                    {{Activer}}
                </label>
            </div>
        </div>
        
        <hr>
        
        <!-- Informations -->
        <legend><i class="fas fa-question-circle"></i> {{Informations}}</legend>
        
        <div class="form-group">
            <div class="col-lg-12">
                <div class="alert alert-info">
                    <h4>{{Configuration de l'API Hobby}}</h4>
                    <p>{{Pour utiliser ce plugin, vous devez avoir configuré l'API Hobby sur votre contrôleur Niko Home Control II.}}</p>
                    <ol>
                        <li>{{Connectez-vous à l'interface web de votre contrôleur}}</li>
                        <li>{{Allez dans les paramètres système}}</li> 
                        <li>{{Activez l'API Hobby et définissez un mot de passe}}</li>
                        <li>{{Redémarrez votre contrôleur}}</li>
                        <li>{{Saisissez les informations de connexion ci-dessus}}</li>
                    </ol>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <div class="col-lg-12">
                <div class="alert alert-warning">
                    <h4>{{Dépendances}}</h4>
                    <p>{{Ce plugin nécessite l'installation de la bibliothèque Python nhc2-coco.}}</p>
                    <p>{{L'installation se fait automatiquement lors de l'installation du plugin.}}</p>
                    <p>{{Si vous rencontrez des problèmes, vérifiez les logs d'installation des dépendances.}}</p>
                </div>
            </div>
        </div>
    </fieldset>
</form>

<script>
// Test de connexion
$('#bt_testConnection').on('click', function () {
    $.ajax({
        type: "POST",
        url: "plugins/nikohomecontrol2/core/ajax/nikohomecontrol2.ajax.php",
        data: {
            action: "testConnection"
        },
        dataType: 'json',
        error: function (request, status, error) {
            $('#div_alert').showAlert({
                message: 'Erreur: ' + error,
                level: 'danger'
            });
        },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({
                    message: data.result,
                    level: 'danger'
                });
                return;
            }
            $('#div_alert').showAlert({
                message: '{{Connexion réussie}}',
                level: 'success'
            });
        }
    });
});

// Mise à jour du statut du démon
function updateDaemonStatus() {
    $.ajax({
        type: "POST",
        url: "plugins/nikohomecontrol2/core/ajax/nikohomecontrol2.ajax.php",
        data: {
            action: "getDaemonStatus"
        },
        dataType: 'json',
        success: function (data) {
            if (data.state == 'ok') {
                if (data.result.state == 'ok') {
                    $('#daemon_state').removeClass('label-danger label-warning').addClass('label-success').text('{{Démarré}}');
                } else {
                    $('#daemon_state').removeClass('label-success label-warning').addClass('label-danger').text('{{Arrêté}}');
                }
            }
        }
    });
}

// Démarrage du démon
$('#bt_startDaemon').on('click', function () {
    $.ajax({
        type: "POST",
        url: "plugins/nikohomecontrol2/core/ajax/nikohomecontrol2.ajax.php",
        data: {
            action: "startDaemon"
        },
        dataType: 'json',
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({
                    message: data.result,
                    level: 'danger'
                });
                return;
            }
            $('#div_alert').showAlert({
                message: '{{Démon démarré}}',
                level: 'success'
            });
            setTimeout(updateDaemonStatus, 2000);
        }
    });
});

// Arrêt du démon
$('#bt_stopDaemon').on('click', function () {
    $.ajax({
        type: "POST",
        url: "plugins/nikohomecontrol2/core/ajax/nikohomecontrol2.ajax.php",
        data: {
            action: "stopDaemon"
        },
        dataType: 'json',
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({
                    message: data.result,
                    level: 'danger'
                });
                return;
            }
            $('#div_alert').showAlert({
                message: '{{Démon arrêté}}',
                level: 'success'
            });
            setTimeout(updateDaemonStatus, 2000);
        }
    });
});

// Redémarrage du démon
$('#bt_restartDaemon').on('click', function () {
    $.ajax({
        type: "POST",
        url: "plugins/nikohomecontrol2/core/ajax/nikohomecontrol2.ajax.php",
        data: {
            action: "restartDaemon"
        },
        dataType: 'json',
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({
                    message: data.result,
                    level: 'danger'
                });
                return;
            }
            $('#div_alert').showAlert({
                message: '{{Démon redémarré}}',
                level: 'success'
            });
            setTimeout(updateDaemonStatus, 5000);
        }
    });
});

// Mise à jour initiale du statut
$(document).ready(function() {
    updateDaemonStatus();
    // Mise à jour périodique du statut
    setInterval(updateDaemonStatus, 30000);
});
</script>