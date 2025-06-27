
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

$("#table_cmd").sortable({
    axis: "y",
    cursor: "move",
    items: ".cmd",
    placeholder: "ui-state-highlight",
    tolerance: "intersect",
    forcePlaceholderSize: true
});

// Synchronisation avec Niko
$('#bt_syncNiko').on('click', function () {
    $.ajax({
        type: "POST",
        url: "plugins/nikohomecontrol2/core/ajax/nikohomecontrol2.ajax.php",
        data: {
            action: "syncWithNiko"
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
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
                message: '{{Synchronisation réussie}}',
                level: 'success'
            });
            window.location.reload();
        }
    });
});

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
    
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="id" style="display:none;"></span>';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" value="' + init(_cmd.name) + '" placeholder="{{Nom de la commande}}">';
    tr += '</td>';
    tr += '<td>';
    tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    tr += '</td>';
    tr += '<td>';
    
    // Paramètres spécifiques pour les sliders
    if (init(_cmd.subType) == 'slider') {
        tr += '<div class="form-group">';
        tr += '<label class="col-sm-6 control-label">{{Valeur min}}</label>';
        tr += '<div class="col-sm-6">';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" value="' + init(_cmd.configuration.minValue, 0) + '">';
        tr += '</div>';
        tr += '</div>';
        tr += '<div class="form-group">';
        tr += '<label class="col-sm-6 control-label">{{Valeur max}}</label>';
        tr += '<div class="col-sm-6">';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" value="' + init(_cmd.configuration.maxValue, 100) + '">';
        tr += '</div>';
        tr += '</div>';
    }
    
    tr += '<span class="cmdAttr" data-l1key="logicalId" style="display:none;" value="' + init(_cmd.logicalId) + '"></span>';
    tr += '<div class="input-group">';
    tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="unite" value="' + init(_cmd.unite) + '" placeholder="{{Unité}}" title="{{Unité}}">';
    tr += '<span class="input-group-btn">';
    tr += '<a class="btn btn-default btn-sm cursor listCmdInfo" tooltip="{{Rechercher une commande}}" title="{{Rechercher une commande}}"><i class="fas fa-list-alt"></i></a>';
    tr += '</span>';
    tr += '</div>';
    tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display:none;margin-top:5px;" title="{{Commande info liée}}">';
    tr += '<option value="">{{Aucune}}</option>';
    tr += '</select>';
    tr += '</td>';
    tr += '<td>';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }
    tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
    tr += '</td>';
    tr += '</tr>';
    
    $('#table_cmd tbody').append(tr);
    var tr = $('#table_cmd tbody tr').last();
    jeedom.eqLogic.buildSelectCmd({
        id: $('.eqLogicAttr[data-l1key=id]').value(),
        filter: {type: 'info'},
        error: function (error) {
            $('#div_alert').showAlert({message: error.message, level: 'danger'});
        },
        success: function (result) {
            tr.find('.cmdAttr[data-l1key=value]').append(result);
            tr.setValues(_cmd, '.cmdAttr');
            jeedom.cmd.changeType(tr, init(_cmd.subType));
        }
    });
}

// Gestion des changements de type d'équipement
$('.eqLogicAttr[data-l1key=configuration][data-l2key=device_type]').on('change', function () {
    var device_type = $(this).value();
    
    if (device_type == 'cover') {
        // Créer automatiquement les commandes pour un volet
        createCoverCommands();
    }
});

function createCoverCommands() {
    // Supprimer les commandes existantes
    $('#table_cmd tbody').empty();
    
    // Commandes info
    var positionCmd = {
        name: 'Position',
        type: 'info',
        subType: 'numeric',
        logicalId: 'position',
        unite: '%',
        isVisible: 1
    };
    addCmdToTable(positionCmd);
    
    var stateCmd = {
        name: 'État',
        type: 'info',
        subType: 'string',
        logicalId: 'state',
        isVisible: 1
    };
    addCmdToTable(stateCmd);
    
    // Commandes action
    var openCmd = {
        name: 'Ouvrir',
        type: 'action',
        subType: 'other',
        logicalId: 'open',
        isVisible: 1
    };
    addCmdToTable(openCmd);
    
    var closeCmd = {
        name: 'Fermer',
        type: 'action',
        subType: 'other',
        logicalId: 'close',
        isVisible: 1
    };
    addCmdToTable(closeCmd);
    
    var stopCmd = {
        name: 'Stop',
        type: 'action',
        subType: 'other',
        logicalId: 'stop',
        isVisible: 1
    };
    addCmdToTable(stopCmd);
    
    var setPositionCmd = {
        name: 'Définir position',
        type: 'action',
        subType: 'slider',
        logicalId: 'setPosition',
        configuration: {
            minValue: 0,
            maxValue: 100
        },
        isVisible: 1
    };
    addCmdToTable(setPositionCmd);
}