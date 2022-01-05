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
	require_once __DIR__ . '/../../../../core/php/core.inc.php';
	include_file('core', 'authentification', 'php');

	if (!isConnect('admin')) {
		throw new Exception(__('401 - Accès non autorisé', __FILE__));
	}

	ajax::init();

  if (init('action') == 'searchCity') {
   	$city = init('city');
   	log::add('owm', 'error', 'Ajax: searchCity - city = ' . $city);

    $res = '[{"ville":"Nancy","index":"1"},{"ville":"Toul","index":"2"}]';
    // file_get_contents("http://www.meteofrance.com/mf3-rpc-portlet/rest/lieu/facet/pluie/search/" . $city);
		if($res){
			ajax::success($res);
		} else {
			throw new Exception("Impossible d'obtenir le résultat de la recherche");
		}
  }

	if (init('action') == 'getWeather') {
		$owm = owm::byId(init('id'));
		if (!is_object($owm)) {
			throw new Exception(__('Weather inconnu vérifiez l\'id', __FILE__));
		}
		$return = utils::o2a($owm);
		$return['cmd'] = array();
		foreach ($owm->getCmd() as $cmd) {
			$cmd_info = utils::o2a($cmd);
			$cmd_info['value'] = $cmd->execCmd();
			$return['cmd'][] = $cmd_info;
		}

		ajax::success($return);
	}

	throw new Exception(__('Aucune methode correspondante à : ', __FILE__) . init('action'));
	/*     * *********Catch exeption*************** */
} catch (Exception $e) {
	ajax::error(displayExeption($e), $e->getCode());
}
?>
