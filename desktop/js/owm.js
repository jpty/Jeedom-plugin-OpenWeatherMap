
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

 $('#bt_cronGenerator').on('click',function(){
    jeedom.getCronSelectModal({},function (result) {
        $('.eqLogicAttr[data-l1key=configuration][data-l2key=refreshCron]').value(result.value);
    });
});

function getTxtType(_type) {
  switch (_type) {
    case 'info': return('Info');
    case 'action': return('Action');
  }
  return(_type);
}
function getTxtSubtype(_subtype) {
  switch (_subtype) {
    case 'numeric': return('Numérique');
    case 'binary': return('Binaire');
    case 'string': return('Autre');
    case 'other': return('Défaut');
    case 'slider': return('Curseur');
    case 'message': return('Message');
    case 'color': return('Couleur');
    case 'select': return('Liste');
  }
  return(_subtype);
}
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
      var _cmd = {configuration: {}};
  }
  if (!isset(_cmd.configuration)) {
      _cmd.configuration = {};
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
  tr += '<td><span class="cmdAttr" data-l1key="id"></span></td>';
  tr += '<td>'+_cmd.logicalId+'</td>';
  tr += '<td>';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" style="display : none;">';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="subType" style="display : none;">';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 240px;" placeholder="{{Nom}}"></td>';
  tr += '<td>'+getTxtType(_cmd.type)+'</td><td>'+getTxtSubtype(_cmd.subType)+'</td>';
  tr += '<td>';
  if(!isset(_cmd.type) || (_cmd.type == 'info' && _cmd.subType == 'numeric')){
      tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
  }
  tr += '</td>';
  tr += '<td>';
  if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
      tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
  }
  tr += '</tr>';
  $('#table_cmd tbody').append(tr);
  $('#table_cmd tbody tr').last().setValues(_cmd, '.cmdAttr');
  if (isset(_cmd.type)) {
      $('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
  }
  jeedom.cmd.changeType($('#table_cmd tbody tr').last(), init(_cmd.subType));
}
