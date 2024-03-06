
/*
 * Fonction pour l'ajout de commande, appellé automatiquement par plugin.template
 */
function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = { configuration: {} };
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
    let tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';

    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="id" style="display:none;"></span>';

    tr += '<div class="input-group">';
    tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">';
    tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>';
    tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>';
    tr += '</div>';
    tr += '</td>';

    tr += '<td>';
    tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>'
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>'
    tr += '</td>';

    tr += '<td>';
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label> ';
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/>{{Historiser}}</label> ';
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label> ';
    tr += '<div style="margin-top:7px;">';
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">';
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">';
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">';
    tr += '</div>';
    tr += '</td>';

    if (typeof jeeFrontEnd !== 'undefined' && jeeFrontEnd.jeedomVersion !== 'undefined') {
        tr += '<td>';
        tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>';
        tr += '</td>';
    }

    tr += '<td>';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }
    tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer la commande}}"></i>';
    tr += '</td>';
    tr += '</tr>';

    $('#table_cmd tbody').append(tr);

    const $tr = $('#table_cmd tbody tr:last');
    $tr.setValues(_cmd, '.cmdAttr');
    jeedom.cmd.changeType($tr, init(_cmd.subType));

    $tr.find('.cmdAttr[data-l1key=type]').prop("disabled", true);
}

$("#table_cmd").sortable({ axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });

$('.pluginAction[data-action=openLocation]').on('click', function () {
    window.open($(this).attr("data-location"), "_blank", null);
});

$('#bt_syncmultiMATIC').on('click', function () {
    $.ajax({
        type: "POST",
        url: "plugins/SmartMeterP1/core/ajax/SmartMeterP1.ajax.php",
        data: {
            action: "syncDevices",
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({ message: data.result, level: 'danger' });
                return;
            }
            $('#div_alert').showAlert({ message: '{{Synchronisation réussie.}}', level: 'success' });
            setTimeout(function () {
                window.location.replace("index.php?v=d&m=SmartMeterP1&p=SmartMeterP1");
            }, 3000);
        }
    });
});

$('#bt_createCommands').on('click', function () {
    $.ajax({
        type: "POST",
        url: "plugins/SmartMeterP1/core/ajax/SmartMeterP1.ajax.php",
        data: {
            action: "createCommands",
            id: $('.eqLogicAttr[data-l1key=id]').value()
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({ message: data.result, level: 'danger' });
                return;
            }
            $('#div_alert').showAlert({ message: '{{Opération réalisée avec succès}}', level: 'success' });
            $('.eqLogicDisplayCard[data-eqLogic_id=' + $('.eqLogicAttr[data-l1key=id]').value() + ']').click();
        }
    });
});

$(".eqLogicAttr[data-l1key='configuration'][data-l2key='type']").change(function () {
    $('#img_device').attr("src", 'core/img/no_image.gif');

    if ($(this).value() != '') {
        $.ajax({
            type: "POST",
            url: "plugins/SmartMeterP1/core/ajax/SmartMeterP1.ajax.php",
            data: {
                action: "getImage",
                id: $('.eqLogicAttr[data-l1key=id]').value()
            },
            dataType: 'json',
            error: function (request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function (data) {
                if (data.state != 'ok') {
                    return;
                }
                console.log(data)
                $('#img_device').attr("src", data.result);
            }
        })
    }
});