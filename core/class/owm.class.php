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
require_once __DIR__ . '/../../../../core/php/core.inc.php';

class owm extends eqLogic {
  /*     * *************************Attributs****************************** */
  public static $_widgetPossibility = array('custom' => true, 'custom::layout' => false);

  /*     * ***********************Methode static*************************** */

  public static function cron($_eqLogic_id = null) {
    if(!in_array(date('i'), array(4, 9, 14, 19, 24, 29, 34, 39, 44, 49, 54, 59))) return;
    if ($_eqLogic_id == null) {
      $eqLogics = self::byType(__CLASS__, true);
    } else {
      $eqLogics = array(self::byId($_eqLogic_id));
    }
    foreach ($eqLogics as $owm) {
      try {
        $owm->updateWeatherData();
      } catch (Exception $e) {
        log::add(__CLASS__, 'info', $e->getMessage());
      }
    }
  }

  public static function getJsonTabInfo($cmd_id, $request) {
    $id = cmd::humanReadableToCmd('#' .$cmd_id .'#');
    $owmCmd = cmd::byId(trim(str_replace('#', '', $id)));
    if(is_object($owmCmd)) {
      $owmJson = $owmCmd->execCmd();
      $json =json_decode($owmJson,true);
      if($json === null)
        log::add(__CLASS__, 'debug', "Unable to decode json: " .substr($owmJson,0,50));
      else {
        $tags = explode('>', $request);
        foreach ($tags as $tag) {
          $tag = trim($tag);
          if (isset($json[$tag])) {
            $json = $json[$tag];
          } elseif (is_numeric(intval($tag)) && isset($json[intval($tag)])) {
            $json = $json[intval($tag)];
          } elseif (is_numeric(intval($tag)) && intval($tag) < 0 && isset($json[count($json) + intval($tag)])) {
            $json = $json[count($json) + intval($tag)];
          } else {
            $json = "Request error: tag[$tag] not found in " .json_encode($json);
            break;
          }
        }
        return (is_array($json)) ? json_encode($json) : $json;
      }
    }
    else log::add(__CLASS__, 'debug', "Command not found: $cmd");
    return(null);
  }

  public static function getIconFromCondition($_condition_id, $_ts, $_sunrise, $_sunset,&$wiIcon) {
// message::add(__FUNCTION__,"Condition: $_condition_id");
    if ($_sunrise == 0 || $_sunset == 0) $jn = '';
    else if ($_ts >= $_sunrise && $_ts < $_sunset) $jn = '-day';
    else $jn = '-night';
      // Corrections
    if($_condition_id == 701) $wiIcon = "wi-owm$jn-741"; // Brume = brouillard
    else if($_condition_id == 801) { // Nuages 11-25 %
      if($jn == '-day') $wiIcon = "wi-day-sunny-overcast";
      else $wiIcon = "wi-night-alt-partly-cloudy";
    }
    else if($_condition_id == 802) { // Nuages 25-50 %
      if($jn == '-day') $wiIcon = "wi-day-sunny-overcast";
      else $wiIcon = "wi-night-alt-partly-cloudy";
    }
    else if($_condition_id == 803) $wiIcon = "wi-cloudy"; // Nuages 51-84 %
    else if($_condition_id == 804) $wiIcon = "wi-cloud"; // Nuages 85-100 %
    else $wiIcon = "wi-owm$jn-$_condition_id";

      //  images du plugin weather
    if ($_condition_id >= 200 && $_condition_id <= 299) return 'meteo-orage';
    if (($_condition_id >= 300 && $_condition_id <= 399)) return 'meteo-brouillard';
    if ($_condition_id >= 500 && $_condition_id <= 510) return 'meteo-nuage-soleil-pluie';
    if ($_condition_id >= 520 && $_condition_id <= 599) return 'meteo-pluie';
    if (($_condition_id >= 600 && $_condition_id <= 699) || ($_condition_id == 511)) return 'meteo-neige';
    if ($_condition_id >= 700 && $_condition_id < 770) return 'meteo-brouillard';
    if ($_condition_id >= 770 && $_condition_id < 799) return 'meteo-vent';
    if ($_condition_id > 800 && $_condition_id <= 899) {
      if ($_sunrise == 0 || ($_ts >= $_sunrise && $_ts < $_sunset)) return 'meteo-nuageux';
      else return 'meteo-nuit-nuage';
    }
    if ($_condition_id == 800) {
      if ($_sunrise == 0 || ($_ts >= $_sunrise && $_ts < $_sunset)) return 'meteo-soleil';
      else return 'meteo-moon';
    }
    if ($_sunrise == 0 || ($_ts >= $_sunrise && $_ts < $_sunset)) return 'meteo-soleil';
    else return 'meteo-moon';
  }

  /*     * *********************Methode d'instance************************* */
  public function preInsert() {
    $this->setCategory('heating', 1);
  }

  public function preUpdate() {
    $cityID = trim($this->getConfiguration('cityID'));
    if ($cityID == '') {
      throw new Exception(__('L\identifiant de la ville ne peut être vide', __FILE__));
    }
    // Recup lat et lon suivant ID de la ville
    // Source: http://bulk.openweathermap.org/sample/
    $fileName= __DIR__ ."/../../data/city.list.min.json.gz";
    $lines = gzfile($fileName);
    if($lines === false) {
      log::add(__CLASS__,'error',"Unable to read json file $fileName");
      return;
    }
    // message::add(__CLASS__, count($lines));
    $fcontent = implode('',$lines);
    unset($lines);
    $pos1 = strpos($fcontent,'{"id": '.$cityID .',');
    if($pos1 !== false) {
      $pos2 = strpos($fcontent,'}}',$pos1);
      if($pos2 !== false) {
        $str=substr($fcontent,$pos1,$pos2+2-$pos1);
        log::add(__CLASS__,'debug',"City found: [$str]");
        $dec = json_decode($str,true);
        if($dec == null) {
          log::add(__CLASS__,'debug',"Unable to decode json. Taille:".strlen($str) ." Err: " .json_last_error_msg());
        }
        else {
          $this->setConfiguration('latitude',$dec['coord']['lat']);
          $this->setConfiguration('longitude',$dec['coord']['lon']);
          $this->setConfiguration('cityName',$dec['name']);
          $this->setConfiguration('country',$dec['country']);
            log::add(__CLASS__,'debug',"Found Lat: " .$dec['coord']['lat']
              ." Lon: " .$dec['coord']['lon'] ." Name: " .$dec['name']);
          unset($dec);
        }
      }
      else log::add(__CLASS__,'debug',"City $cityID not found. 2");
    }
    else log::add(__CLASS__,'debug',"City $cityID not found. 1");
    unset($fcontent);
    // message::add(__CLASS__, "Mem used: " .memory_get_usage(false));
  }

  public function postUpdate() {
    $owmCmd = $this->getCmd(null, 'temperature');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Température', __FILE__));
      $owmCmd->setLogicalId('temperature');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('°C');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'WEATHER_TEMPERATURE');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'feels_like');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Température ressentie', __FILE__));
      $owmCmd->setLogicalId('feels_like');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('°C');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'WEATHER_TEMPERATURE');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'humidity');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Humidité', __FILE__));
      $owmCmd->setLogicalId('humidity');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('%');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'WEATHER_HUMIDITY');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'visibility');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Visibilité', __FILE__));
      $owmCmd->setLogicalId('visibility');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('m');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'WEATHER_VISIBILITY');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'clouds');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Nuages', __FILE__));
      $owmCmd->setLogicalId('clouds');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('%');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'WEATHER_CLOUDS');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'location');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Localisation', __FILE__));
      $owmCmd->setLogicalId('location');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setType('info');
      $owmCmd->setSubType('string');
      $owmCmd->setDisplay('generic_type', 'WEATHER_LOCATION');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'pressure');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Pression', __FILE__));
      $owmCmd->setLogicalId('pressure');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('hPa');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'WEATHER_PRESSURE');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'wind_speed');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Vitesse du vent', __FILE__));
      $owmCmd->setLogicalId('wind_speed');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('km/h');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'WEATHER_WIND_SPEED');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'windGust');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Rafales vent', __FILE__));
      $owmCmd->setLogicalId('windGust');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('km/h');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'WEATHER_WIND_GUST');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'dewPoint');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Point de rosée', __FILE__));
      $owmCmd->setLogicalId('dewPoint');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('°C');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'WEATHER_DEW_POINT');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'wind_direction');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Direction du vent', __FILE__));
      $owmCmd->setLogicalId('wind_direction');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'WEATHER_WIND_DIRECTION');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'sunset');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Coucher du soleil', __FILE__));
      $owmCmd->setLogicalId('sunset');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setConfiguration('repeatEventManagement', 'always');
      $owmCmd->setDisplay('generic_type', 'WEATHER_SUNSET');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'sunrise');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Lever du soleil', __FILE__));
      $owmCmd->setLogicalId('sunrise');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'WEATHER_SUNRISE');
      $owmCmd->setConfiguration('repeatEventManagement', 'always');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'condition');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Condition', __FILE__));
      $owmCmd->setLogicalId('condition');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('');
      $owmCmd->setType('info');
      $owmCmd->setSubType('string');
      $owmCmd->setDisplay('generic_type', 'WEATHER_CONDITION');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'condition_icon');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Icône condition', __FILE__));
      $owmCmd->setLogicalId('condition_icon');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('');
      $owmCmd->setType('info');
      $owmCmd->setSubType('string');
      $owmCmd->setDisplay('generic_type', 'DONT');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, "forecast_daily_json");
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__("Forecast daily json", __FILE__));
      $owmCmd->setLogicalId('forecast_daily_json');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('');
      $owmCmd->setDisplay('generic_type', 'DONT');
    }
    $owmCmd->setType('info');
    $owmCmd->setSubType('string');
    $owmCmd->setIsHistorized(0);
    $owmCmd->save();

    $owmCmd = $this->getCmd(null, "rainForecast_json");
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__("Prévisions de pluie json", __FILE__));
      $owmCmd->setLogicalId('rainForecast_json');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('');
      $owmCmd->setDisplay('generic_type', 'DONT');
    }
    $owmCmd->setType('info');
    $owmCmd->setSubType('string');
    $owmCmd->setIsHistorized(0);
    $owmCmd->save();

    $owmCmd = $this->getCmd(null, "forecast_1h_json");
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__("Forecast 1h json", __FILE__));
      $owmCmd->setLogicalId('forecast_1h_json');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('');
      $owmCmd->setDisplay('generic_type', 'DONT');
    }
    $owmCmd->setType('info');
    $owmCmd->setSubType('string');
    $owmCmd->setIsHistorized(0);
    $owmCmd->save();

    $owmCmd = $this->getCmd(null, "forecast_3h_json");
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__("Forecast 3h json", __FILE__));
      $owmCmd->setLogicalId('forecast_3h_json');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('');
      $owmCmd->setDisplay('generic_type', 'DONT');
    }
    $owmCmd->setType('info');
    $owmCmd->setSubType('string');
    $owmCmd->setIsHistorized(0);
    $owmCmd->save();

    $owmCmd = $this->getCmd(null, 'condition_id');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Numéro condition', __FILE__));
      $owmCmd->setLogicalId('condition_id');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'WEATHER_CONDITION_ID');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'precipitation');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Pluie', __FILE__));
      $owmCmd->setLogicalId('precipitation');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('mm');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'WEATHER_RAIN');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'snow');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Neige', __FILE__));
      $owmCmd->setLogicalId('snow');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('mm');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'WEATHER_RAIN');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'uv');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Index UV', __FILE__));
      $owmCmd->setLogicalId('uv');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'WEATHER_UV');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'timestamp_OW_call');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Horodatage appel OW', __FILE__));
      $owmCmd->setLogicalId('timestamp_OW_call');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('');
      $owmCmd->setType('info');
      $owmCmd->setSubType('numeric');
      $owmCmd->setDisplay('generic_type', 'DONT');
      $owmCmd->save();
    }

    $owmCmd = $this->getCmd(null, 'txtAlerts_json');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Alerte météo', __FILE__));
      $owmCmd->setLogicalId('txtAlerts_json');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('');
    }
    $owmCmd->setType('info');
    $owmCmd->setSubType('string');
    $owmCmd->setIsHistorized(0);
    $owmCmd->save();

    $owmCmd = $this->getCmd(null, 'aqi_json');
    if (!is_object($owmCmd)) {
      $owmCmd = new owmCmd();
      $owmCmd->setName(__('Qualité air', __FILE__));
      $owmCmd->setLogicalId('aqi_json');
      $owmCmd->setEqLogic_id($this->getId());
      $owmCmd->setUnite('');
    }
    $owmCmd->setType('info');
    $owmCmd->setSubType('string');
    $owmCmd->setIsHistorized(0);
    $owmCmd->save();

    $refresh = $this->getCmd(null, 'refresh');
    if (!is_object($refresh)) {
      $refresh = new owmCmd();
      $refresh->setName(__('Rafraichir', __FILE__));
    }
    $refresh->setEqLogic_id($this->getId());
    $refresh->setLogicalId('refresh');
    $refresh->setType('action');
    $refresh->setSubType('other');
    $refresh->save();

    if ($this->getIsEnable() == 1) {
      $this->updateWeatherData();
    }
  }

  public function preRemove() {
    $cron = cron::byClassAndFunction(__CLASS__, 'pull', array('weather_id' => intval($this->getId())));
    if (is_object($cron)) {
      $cron->remove();
    }
  }

  public function convertDegrees2Compass($degrees,$deg=0) {
    $sector = array("Nord","NNE","NE","ENE","Est","ESE","SE","SSE","Sud","SSO","SO","OSO","Ouest","ONO","NO","NNO","Nord");
    $degrees %= 360;
    $idx = round($degrees/22.5);
    if($deg) {
      return($sector[$idx] ." $degrees" ."°");
    }
    else return($sector[$idx]);
  }

/* daily.moon_phase Moon phase. 0 and 1 are 'new moon', 0.25 is 'first quarter moon', 0.5 is 'full moon' and 0.75 is 'last quarter moon'. The periods in between are called 'waxing crescent', 'waxing gibous', 'waning gibous', and 'waning crescent', respectively. */
  public static function moonPhaseName($phase) {
    if($phase == 0 || $phase == 1)
      return('Nouvelle lune');
    else if($phase > 0 && $phase < 0.25)
      return('Premier croissant');
    else if($phase == 0.25)
      return('Premier quartier');
    else if($phase > 0.25 && $phase < 0.5)
      return('Gibbeuse croissante');
    else if($phase == 0.5)
      return('Pleine lune');
    else if($phase > 0.5 && $phase < 0.75)
      return('Gibbeuse décroissante');
    else if($phase == 0.75)
      return('Dernier quartier');
    else if($phase > 0.75 && $phase < 1)
      return('Dernier croissant');
  }

  public function toHtml($_version = 'dashboard') {
    $replace = $this->preToHtml($_version);
    if (!is_array($replace)) {
      return $replace;
    }
    $version = jeedom::versionAlias($_version);
    $replace['#url_openweathermap#'] = '';
    $apiKey = trim(config::byKey('apikey', __CLASS__));
    $replace['#apiKey#'] = $apiKey;
    $cityID = $this->getConfiguration('cityID');
    $lat = $this->getConfiguration('latitude');
    $lon = $this->getConfiguration('longitude');
    $dateLoc = $this->getConfiguration('dateLoc');
    $replace['#urlWeather#'] = "http://api.openweathermap.org/data/2.5/weather?id=$cityID&appid=$apiKey&units=metric&lang=fr";
    $replace['#urlForecast#'] = "http://api.openweathermap.org/data/2.5/forecast?id=$cityID&appid=$apiKey&units=metric&lang=fr";
    // log::add(__CLASS__,'debug', "toHtml cityID= " .$cityID);
    if ($cityID != '')
      $replace['#url_openweathermap#'] = 'http://openweathermap.org/city/'.$cityID;

    setlocale(LC_TIME,$dateLoc);
      // Météo actuelle
    $precipitation = $this->getCmd(null, 'precipitation');
    if(is_object($precipitation)) {
      $replace['#precipid#'] = $precipitation->getId();
      $pluie = round($precipitation->execCmd(),1);
      $replace['#precip#'] = '';
      if($pluie > 0) $replace['#precip#'] .= 'Pluie: ' .$pluie .'mm';
    }
    else {
      $replace['#precipid#'] = '';
      $replace['#precip#'] = '';
    }
    $snow = $this->getCmd(null, 'snow');
    if(is_object($snow)) {
      $replace['#neigeid#'] = $snow->getId();
      $neige = round($snow->execCmd(),1);
      $replace['#neige#'] = '';
      if($neige > 0) $replace['#neige#'] .= 'Neige: ' .$neige .'mm';
    }
    else {
      $replace['#neigeid#'] = '';
      $replace['#neige#'] = '';
    }
    $replace['#precipid#'] = is_object($precipitation) ? $precipitation->getId() : '';
    $temperature = $this->getCmd(null, 'temperature');
    $replace['#temperature#'] = is_object($temperature) ? round($temperature->execCmd(),1) : '';
    $replace['#temperatureid#'] = is_object($temperature) ? $temperature->getId() : '';
    $feels_like = $this->getCmd(null, 'feels_like');
    $replace['#feels_like#'] = is_object($feels_like) ? round($feels_like->execCmd(),1) : '';
    $humidity = $this->getCmd(null, 'humidity');
    $replace['#humidity#'] = is_object($humidity) ? $humidity->execCmd() : '';
    $replace['#humidityid#'] = is_object($humidity) ? $humidity->getId() : '';
    $visibility = $this->getCmd(null, 'visibility');
    if( is_object($visibility)) {
      $vis = $visibility->execCmd();
      if($vis > 999 ) $vis = round($vis/1000,1) ."km";
      else $vis .= "m";
      $replace['#visibility#'] = $vis;
      $replace['#visibilityid#'] = $visibility->getId();
    }
    else
    { $replace['#visibility#'] = ''; $replace['#visibilityid#'] = ''; }
    $clouds = $this->getCmd(null, 'clouds');
    if(is_object($clouds)) {
      $val = $clouds->execCmd();
      if($val == 0) $val = '';
      else $val =  '<i class="wi wi-cloud"></i> ' .$val .'%';
      $replace['#cloudsid#'] = $clouds->getId();
      $replace['#clouds#'] = $val;
    }
    else
    { $replace['#clouds#'] = ''; $replace['#cloudsid#'] = ''; }
    $location = $this->getCmd(null, 'location');
    $replace['#location#'] = is_object($location) ? $location->execCmd() : '';
    $replace['#locationid#'] = is_object($location) ? $location->getId() : '';
    $pressure = $this->getCmd(null, 'pressure');
    $replace['#pressure#'] = is_object($pressure) ? $pressure->execCmd() : '';
    $replace['#pressureid#'] = is_object($pressure) ? $pressure->getId() : '';
    $wind_speed = $this->getCmd(null, 'wind_speed');
    $replace['#windspeed#'] = is_object($wind_speed) ? $wind_speed->execCmd() : '';
    $replace['#windid#'] = is_object($wind_speed) ? $wind_speed->getId() : '';
    $wind_direction = $this->getCmd(null, 'wind_direction');
    $replace['#winddir#'] = is_object($wind_direction) ? $this->convertDegrees2Compass($wind_direction->execCmd(),0) : '';
    $windGust = $this->getCmd(null, 'windGust');
    if(is_object($windGust)) $raf = $windGust->execCmd();
    $replace['#windGust#'] = ($raf) ? (' &nbsp; '.$windGust->execCmd().'km/h &nbsp;') : '';
    $dewPoint = $this->getCmd(null, 'dewPoint');
    $replace['#dewPoint#'] = is_object($dewPoint) ? (round($dewPoint->execCmd(),1)) : '-';
    $replace['#dewPointid#'] = is_object($dewPoint) ? $dewPoint->getId() : '';
    $uv = $this->getCmd(null, 'uv');
    $replace['#uv#'] = is_object($uv) ? $uv->execCmd() : '';
    $replace['#uvid#'] = is_object($uv) ? $uv->getId() : '';
    $sunrise = $this->getCmd(null, 'sunrise');
    $sunrise_time = is_object($sunrise) ? $sunrise->execCmd() : 0;
    $replace['#sunrise#'] = date('G:i',$sunrise_time);
    $replace['#sunid#'] = is_object($sunrise) ? $sunrise->getId() : '';
    $sunset = $this->getCmd(null, 'sunset');
    $sunset_time = is_object($sunset) ? $sunset->execCmd() : null;
    $replace['#sunset#'] = date('G:i',$sunset_time);
    $wind_direction = $this->getCmd(null, 'wind_direction');
    $replace['#wind_direction#'] = is_object($wind_direction) ? ($wind_direction->execCmd()) : 180;
    $replace['#wind_direction_vari#'] = is_object($wind_direction) ? ($wind_direction->execCmd()+180) : 180;
    $refresh = $this->getCmd(null, 'refresh');
    $replace['#refresh_id#'] = is_object($refresh) ? $refresh->getId() : '';
    $condition_id = $this->getCmd(null, 'condition_id');
    if (is_object($condition_id)) $cond_id = $condition_id->execCmd();
    else $cond_id = 800;
    $replace['#condition_id#'] = "($cond_id)";
    $condition = $this->getCmd(null, 'condition');
    if (is_object($condition)) {
      $cond = $condition->execCmd() .'.';
      $replace['#conditionid#'] = $condition->getId();
    } else {
      $cond = '';
      $replace['#collectDate#'] = '';
    }
    $replace['#condition#'] = $cond;
    $condition_icon = $this->getCmd(null, 'condition_icon');
    if (is_object($condition_icon)) {
      $icon = $condition_icon->execCmd();
      $img = self::getIconFromCondition($cond_id, time(), $sunrise_time, $sunset_time, $wiIcon);
      if ($this->getConfiguration('wi_icons',0)==1) {
        $replace['#icone#'] = "<div title=\"$cond ($cond_id)\" style=\"text-align:center;margin-top:15px;\"><i class=\"wi $wiIcon\" style=\"font-size: 48px;width: 100px; height: 80px;\"></i></div>";
      }
      else if ($this->getConfiguration('owm_images',0)==1) {
        $replace['#icone#'] = "<div title=\"$cond ($cond_id)\" style=\"background: url('plugins/".__CLASS__."/core/template/images/".$icon."@2x.png') no-repeat center; background-size: 100% 100%; width: 100px; height: 100px;\"></div>";
      }
      else { // internal images
        $replace['#icone#'] = "<div title=\"$cond ($cond_id)\" style=\"background: url('plugins/".__CLASS__."/core/template/images/".$img.".png') no-repeat center; background-size: 100% 100%; width: 100px; height: 100px;\"></div>";

      }
    }
    else $replace['#icone#'] = '';
    

      // PREVISIONS 1 HEURE
    $txt1h = $txt3h = ''; // titre des prévisions uniquement si prev1h et prev3h affichés
    $replace['#forecast1h#'] = '';
    $nbprev1h = ($this->getConfiguration('forecast1h',0)==0)? 0 : 48;
    $nbprev3h = ($this->getConfiguration('forecast3h',0)==0)? 0 : 40; // pour titre prev 1h
    if($nbprev1h > 0 &&
        ($version != 'mobile' || $this->getConfiguration('fullMobileDisplay', 0) == 1)) {
      $jour = date("j",time()-86400);
      $json = $this->getCmd(null, 'forecast_1h_json');
      if(is_object($json)) {
        $decAll = json_decode($json->execCmd(),true);
        if($decAll == null) {
          log::add(__CLASS__, 'debug', __FILE__ ." " .__LINE__ ." Json_decode error : " .json_last_error_msg());
        }
        else {
          if (file_exists( __DIR__ ."/../template/$_version/custom.forecastHourly.html"))
            $forecastHourly_template = getTemplate('core', $version, 'custom.forecastHourly', __CLASS__);
          else
            $forecastHourly_template = getTemplate('core', $version, 'forecastHourly', __CLASS__);
          $cnt = count($decAll);
  // log::add(__CLASS__, 'info', "Nbdec: $cnt");
          for ($i = 0; $i < $cnt; $i++) {
            if($i >= $nbprev1h ) break;
            $dec=$decAll[$i];
  /*
    {"dt":1618077600,"temp":12.53,"feels_like":12.27,"pressure":1017,"humidity":93,"dew_point":11.43,"uvi":0,"clouds":79,"visibility":3670,"wind_speed":6.2,"wind_deg":161,"wind_gust":8.56,"weather":[{"id":500,"main":"Rain","description":"l\u00e9g\u00e8re pluie","icon":"10n"}],"pop":0.75,"rain":{"1h":0.32}
    */
            $replace1h = array();
            $ts = $dec["dt"];
            $replace1h['#day#'] = '';
            if(($i == 0 && date('G',$ts) > 18) || (date('H',$ts)%6) ==0) 
              $replace1h['#day#'] = ucfirst(strftime('%a %d/%m',$ts));
            $replace1h['#day#'] .= "<br/>" .date('G:i', $ts);
            $replace1h['#temperature#'] = round($dec["temp"],1);
            $cloud = ($dec["clouds"]>0)?' Nuages: '.$dec["clouds"].'%':'';
            if(isset($dec['wind_gust']))
              $raf = 'Rafales: ' .round($dec['wind_gust'] * 3.6)."km/h";
            else $raf = '';
            if(date('H',$ts) == 23)
              $replace1h['#weatherDescription#'] =
              "<b>Du " .ucfirst(strftime('%A %d %B %H:%M',$ts)) ." au "
              .strftime('%A %d %B %H:%M',$ts+3600) ."</b>";
            else
              $replace1h['#weatherDescription#'] =
              "<b>" .ucfirst(strftime('%A %d %B',$ts)) ." de " .strftime('%H:%M',$ts)
              ." à " .strftime('%H:%M',$ts+3600) ."</b>";
            $replace1h['#weatherDescription#'] .= "<br/>" .ucfirst($dec["weather"][0]["description"].'.')
              ."(" .$dec["weather"][0]["id"] .")"
              .$cloud
              ."<br/>Température: ".round($dec["temp"],1) ."°C"
              ."<br/>Temp. ressentie: " .round($dec["feels_like"],1) ."°C"
              ."<br/>Pression: " .$dec["pressure"] ."hPa"
              ."<br/>Humidité: ".$dec["humidity"]."%"
              ."<br/>Vent: " .round($dec["wind_speed"]*3.6) ."km/h (".$this->convertDegrees2Compass($dec["wind_deg"],0) .") $raf";
            if(array_key_exists('rain',$dec))
              $replace1h['#weatherDescription#'] .= "<br/>Pluie: " .round($dec["rain"]["1h"],1) ."mm";
            if(array_key_exists('snow',$dec))
              $replace1h['#weatherDescription#'] .= "<br/>Neige: " .round($dec["snow"]["1h"],1) ."mm";
            $icon = $dec["weather"][0]["icon"];
            $condition = $dec["weather"][0]["id"];
            $replace1h['#conditionid#'] = $condition;
            $sun = date_sun_info($ts,$lat,$lon);
            $img = self::getIconFromCondition($condition,$ts,$sun['sunrise'],$sun['sunset'],$wiIcon);
            $weatherDesc = $replace1h['#weatherDescription#'];
            if ($this->getConfiguration('wi_icons',0)==1) {
              $replace1h['#icone#'] = "<div title=\"$weatherDesc\" style=\"text-align:center;margin-top:15px;\"><i class=\"wi $wiIcon\" style=\"font-size: 24px;width: 48px; height: 30px;\"></i></div>";
            }
            else if ($this->getConfiguration('owm_images',0)==1) {
              $replace1h['#icone#'] = "<div title=\"$weatherDesc\" style=\"background: url('plugins/".__CLASS__."/core/template/images/".$icon."@2x.png') no-repeat center; background-size: 100% 100%; width: 48px; height: 48px;\"></div>";
            }
            else { // internal images
              $replace1h['#icone#'] = "<div title=\"$weatherDesc\" style=\"background: url('plugins/".__CLASS__."/core/template/images/".$img.".png') no-repeat center; background-size: 100% 100%; width: 48px; height: 48px;\"></div>";
            }
            $replace['#forecast1h#'] .= template_replace($replace1h, $forecastHourly_template);
          }
          if($nbprev3h) $txt1h = "&nbsp;Prévisions 1h";
          if(strlen($replace['#forecast1h#']))
            $replace['#forecast1h#'] = "<div  style=\"overflow-x: scroll; width: 100%; min-height: 15px; max-height: 200px; margin-top: 1px; font-size: 14px; text-align: left; scrollbar-width: thin;\">$txt1h<div style=\"width: " .(2740/48*$i) ."px;\">" .$replace['#forecast1h#'] ."</div></div>\n";
        }
      }
      else $replace['#forecast1h#'] ='forecast_1h_json Cmd not found. Equipment should be re-saved';
    }
    else $replace['#forecast1h#'] = '';

      // PREVISIONS 3 HEURES
    $replace['#forecast3h#'] = '';
    if($nbprev3h > 0 &&
        ($version != 'mobile' || $this->getConfiguration('fullMobileDisplay', 0) == 1)) {
      $jour = date("j",time()-86400);
      $json = $this->getCmd(null, 'forecast_3h_json');
      if(is_object($json)) {
        $decAll = json_decode($json->execCmd(),true);
        if($decAll == null) {
          $replace['#forecast3h#'] ='Prévisions 3h non disponibles';
          log::add(__CLASS__, 'error', __FILE__ ." Line: " .__LINE__ ." Json_decode error : " .json_last_error_msg());
        }
        else {
          if (file_exists( __DIR__ ."/../template/$_version/custom.forecast3Hours.html"))
            $forecastHourly_template = getTemplate('core', $version, 'custom.forecast3Hours', __CLASS__);
          else
            $forecastHourly_template = getTemplate('core', $version, 'forecast3Hours', __CLASS__);
          $cnt = count($decAll);
  // log::add(__CLASS__, 'info', "Nbdec: $cnt");
          for ($i = 0; $i < $cnt; $i++) {
            if($i >= $nbprev3h ) break;
            $dec=$decAll[$i];
            // log::add(__CLASS__, 'info', "Dec[$i]: " .print_r($dec,true));
              /* list[
    {"dt":1618444800,"main":{"temp":2.2,"feels_like":0.86,"temp_min":-0.42,"temp_max":2.2,"pressure":1027,"sea_level":1027,"grnd_level":1003,"humidity":76,"temp_kf":2.62},"weather":[{"id":800,"main":"Clear","description":"ciel dégagé","icon":"01n"}],"clouds":{"all":4},"wind":{"speed":1.43,"deg":21,"gust":1.42},"visibility":10000,"pop":0,"sys":{"pod":"n"},"dt_txt":"2021-04-15 00:00:00"}
    */
            $replace3h = array();
            $ts = $dec["dt"];
            $jour2 = date("j",$ts);
            $replace3h['#day#'] = '';
            if($jour != $jour2) {
              $replace3h['#day#'] = ucfirst(strftime('%a %d/%m',$ts));
              $jour = $jour2;
            }
            $replace3h['#day#'] .= "<br/>" .date('G:i', $ts);
            $replace3h['#low_temperature#'] = round($dec["main"]["temp_min"]);
            $replace3h['#high_temperature#'] = round($dec["main"]["temp_max"]);
            $replace3h['#temperature#'] = round($dec["main"]["temp"]);
            $replace3h['#icone#'] = $dec["weather"][0]["icon"];
            $cloud = ($dec["clouds"]["all"]>0)?' Nuages: '.$dec["clouds"]["all"].'%':'';
            if(isset($dec['wind']['gust']))
              $raf = 'Rafales: ' .round($dec['wind']['gust'] * 3.6)."km/h";
            else $raf = '';
            if(date('H',$ts) == 23)
              $replace3h['#weatherDescription#'] =
              "<b>Du " .ucfirst(strftime('%A %d %B %H:%M',$ts)) ." au "
              .strftime('%A %d %B %H:%M',$ts+10800) ."</b>";
            else
              $replace3h['#weatherDescription#'] =
              "<b>" .ucfirst(strftime('%A %d %B',$ts)) ." de " .strftime('%H:%M',$ts)
              ." à " .strftime('%H:%M',$ts+10800) ."</b>";
            $replace3h['#weatherDescription#'] .=
              "<br/>" .ucfirst($dec["weather"][0]["description"].'.')
              ."(" .$dec["weather"][0]["id"] .")"
              .$cloud
              ."<br/>Température: ".round($dec["main"]["temp"],1) ."°C"
              ."<br/>Temp. ressentie: " .round($dec["main"]["feels_like"],1) ."°C"
              ."<br/>Pression: " .$dec["main"]["pressure"] ."hPa"
              ."<br/>Humidité: ".$dec["main"]["humidity"]."%"
              ."<br/>Vent: " .round($dec["wind"]["speed"]*3.6) ."km/h (".$this->convertDegrees2Compass($dec["wind"]["deg"],0) .") $raf";
            if(array_key_exists('rain',$dec))
              $replace3h['#weatherDescription#'] .= "<br/>Pluie: " .round($dec["rain"]["3h"],1) ."mm";
            if(array_key_exists('snow',$dec))
              $replace3h['#weatherDescription#'] .= "<br/>Neige: " .round($dec["snow"]["3h"],1) ."mm";

            $icon = $dec["weather"][0]["icon"];
            $condition = $dec["weather"][0]["id"];
            $replace3h['#conditionid#'] = $condition;
            $sun = date_sun_info($ts,$lat,$lon);
            $img = self::getIconFromCondition($condition,$ts,$sun['sunrise'],$sun['sunset'],$wiIcon);
            $weatherDesc = $replace3h['#weatherDescription#'];
            if ($this->getConfiguration('wi_icons',0)==1) {
              $replace3h['#icone#'] = "<div title=\"$weatherDesc\" style=\"text-align:center;margin-top:15px;\"><i class=\"wi $wiIcon\" style=\"font-size: 24px;width: 48px; height: 30px;\"></i></div>";
            }
            else if ($this->getConfiguration('owm_images',0)==1) {
              $replace3h['#icone#'] = "<div title=\"$weatherDesc\" style=\"background: url('plugins/".__CLASS__."/core/template/images/".$icon."@2x.png') no-repeat center; background-size: 100% 100%; width: 48px; height: 48px;\"></div>";
            }
            else { // internal images
              $replace3h['#icone#'] = "<div title=\"$weatherDesc\" style=\"background: url('plugins/".__CLASS__."/core/template/images/".$img.".png') no-repeat center; background-size: 100% 100%; width: 48px; height: 48px;\"></div>";
            }
            $replace['#forecast3h#'] .= template_replace($replace3h, $forecastHourly_template);
          }
          if($nbprev1h) $txt3h = "&nbsp;Prévisions 3h"; // Titre 3h si prev1h affichées
          if(strlen($replace['#forecast3h#']))
            $replace['#forecast3h#'] = "<div  style=\"overflow-x: scroll; width: 100%; min-height: 15px; max-height: 200px; margin-top: 5px; font-size: 14px; text-align: left; scrollbar-width: thin;\">$txt3h<div style=\"width: " .(2300/40*$i) ."px;\">" .$replace['#forecast3h#'] ."</div></div>\n";
        }
      }
      else $replace['#forecast3h#'] ='forecast_3h_json Cmd not found. Equipment should be re-saved';
    }
    else $replace['#forecast3h#'] = '';

      // PREVISIONS PAR JOUR
    $replace['#forecastDaily#'] = '';
    $nbprevDaily = ($this->getConfiguration('forecastDaily',0)==0)? 0 : 8;
    if($nbprevDaily > 0 &&
        ($version != 'mobile' || $this->getConfiguration('fullMobileDisplay', 0) == 1)) {
      $json = $this->getCmd(null, 'forecast_daily_json');
      if(is_object($json)) {
        $decAll = json_decode($json->execCmd(),true);
        if($decAll === null) {
          log::add(__CLASS__, 'error', __FILE__ ." line:" .__LINE__ ." Json_decode error : " .json_last_error_msg());
        }
        else {
          if (file_exists( __DIR__ ."/../template/$_version/custom.forecastDaily.html"))
            $forecastDaily_template = getTemplate('core', $version, 'custom.forecastDaily', __CLASS__);
          else
            $forecastDaily_template = getTemplate('core', $version, 'forecastDaily', __CLASS__);
          $cnt = count($decAll);
  // log::add(__CLASS__, 'info', "Nbdec: $cnt");
          for ($i = 0; $i < $cnt; $i++) {
            if($i >= $nbprevDaily ) break;
            $dec=$decAll[$i];

            // log::add(__CLASS__, 'info', "Dec[$i]: " .print_r($dec,true));
            /*
            { "dt":1618484400, "sunrise":1618461849, "sunset":1618511138, "moonrise":1618467600, "moonset":0, "moon_phase":0.1, "temp": { "day":8.14, "min":-1.63, "max":8.81, "night":1.31, "eve":8.22, "morn":-1.63 }, "feels_like": { "day":5.41, "night":-4.02, "eve":5.64, "morn":-4.02 }, "pressure":1027, "humidity":48, "dew_point":-1.95, "wind_speed":5.02, "wind_deg":62, "wind_gust":7.46, "weather":[{ "id":500, "main":"Rain", "description":"l\u00e9g\u00e8re pluie", "icon":"10d" }], "clouds":25, "pop":0.36, "rain":0.11, "uvi":3.55 }
              */
            $replaceDay = array();
            $ts = $dec["dt"];
            $replaceDay['#day#'] = ucfirst(strftime('%A<br/>%d/%m',$ts));
            $replaceDay['#low_temperature#'] = round($dec["temp"]["min"]);
            $replaceDay['#high_temperature#'] = round($dec["temp"]["max"]);
            $replaceDay['#icone#'] = $dec["weather"][0]["icon"];
            $cloud = ($dec["clouds"]>0)?' Nuages: '.$dec["clouds"].'%':'';
            if(isset($dec['wind_gust']))
              $raf = 'Rafales: ' .round($dec['wind_gust'] * 3.6)."km/h";
            else $raf = '';
              $replaceDay['#weatherDescription#'] =
              "<b>" .ucfirst(strftime('%A %d %B',$ts)) ."</b>"
              ."<br/>" .ucfirst($dec["weather"][0]["description"].'.')
              ."(" .$dec["weather"][0]["id"] .")"
              .$cloud
              ."<br/>Pression: " .$dec["pressure"] ."hPa"
              ."<br/>Humidité: ".$dec["humidity"]."%"
              ."<br/>Vent: " .round($dec["wind_speed"]*3.6) ."km/h (".$this->convertDegrees2Compass($dec["wind_deg"],0) .") $raf";
            if(array_key_exists('rain',$dec)) {
              $replaceDay['#weatherDescription#'] .= "<br/>Pluie: " .round($dec["rain"],1) ."mm";
              if(array_key_exists('pop',$dec))
                $replaceDay['#weatherDescription#'] .= " Pop: " .($dec["pop"]*100) ."%";
            }
            if(array_key_exists('snow',$dec))
              $replaceDay['#weatherDescription#'] .= "<br/>Neige: " .round($dec["snow"],1) ."mm";
            $replaceDay['#weatherDescription#'] .= 
               "<table><tr id=wDesc1><td></td><td>Matin&nbsp;</td><td>Après-midi&nbsp;</td><td>Soirée&nbsp;</td><td>Nuit</td></tr>"
              ."<tr id='wDesc2'><td>Temp.&nbsp;</td><td>".round($dec["temp"]["morn"]) ."°C</td><td>".round($dec["temp"]["day"]) ."°C</td><td>".round($dec["temp"]["eve"]) ."°C</td><td>".round($dec["temp"]["night"]) ."°C</td></tr>"
              ."<tr id='wDesc3'><td>Ress.&nbsp;</td><td>".round($dec["feels_like"]["morn"]) ."°C</td><td>".round($dec["feels_like"]["day"]) ."°C</td><td>".round($dec["feels_like"]["eve"]) ."°C</td><td>".round($dec["feels_like"]["night"]) ."°C</td></tr>"
              ."</table>";
            $ts1 = $dec["moonrise"]; $ts2 = $dec["moonset"];
            $replaceDay['#weatherDescription#'] .= "Lune: "
              .(($ts1 == 0)? '--' : date('H:i',$ts1))
              .' | '
              .(($ts2 == 0)? '--' : date('H:i',$ts2))
              .' ' .self::moonPhaseName($dec["moon_phase"]);
            $replaceDay['#conditionid#'] = $dec["weather"][0]["id"];
            $icon = $dec["weather"][0]["icon"];
            $condition = $dec["weather"][0]["id"];
            $img = self::getIconFromCondition($condition,$ts,$dec['sunrise'],$dec['sunset'],$wiIcon);
            $weatherDesc = $replaceDay['#weatherDescription#'];
            if ($this->getConfiguration('wi_icons',0)==1) {
              $replaceDay['#icone#'] = "<div title=\"$weatherDesc\" style=\"text-align:center;margin-top:15px;\"><i class=\"wi $wiIcon\" style=\"font-size: 24px;width: 48px; height: 30px;\"></i></div>";
            }
            else if ($this->getConfiguration('owm_images',0)==1) {
              $replaceDay['#icone#'] = "<div title=\"$weatherDesc\" style=\"background: url('plugins/".__CLASS__."/core/template/images/".$icon."@2x.png') no-repeat center; background-size: 100% 100%; width: 48px; height: 48px;\"></div>";
            }
            else { // internal images
              $replaceDay['#icone#'] = "<div title=\"$weatherDesc\" style=\"background: url('plugins/".__CLASS__."/core/template/images/".$img.".png') no-repeat center; background-size: 100% 100%; width: 48px; height: 48px;\"></div>";
            }
            $replace['#forecastDaily#'] .= template_replace($replaceDay, $forecastDaily_template);
          }
        }
        $replace['#forecastDaily#'] = "<center><div style=\"margin-top:5px;\">" .$replace['#forecastDaily#'] ."</div></center>";
      }
      else $replace['#rainForecast#'] ='forecast_daily_json Cmd not found. Equipment should be re-saved';
    }
    else $replace['#forecastDaily#'] = '';

      // QUANTITÉ PLUIE DANS L'HEURE
    $replace['#rainForecast#'] = '';
    $rf = $this->getConfiguration('rainForecast',0);
    if($rf == 1 &&
        ($version != 'mobile' || $this->getConfiguration('fullMobileDisplay', 0) == 1)) {
      if (file_exists( __DIR__ ."/../template/$_version/custom.rainForecast.html"))
        $rainForecast_template = getTemplate('core', $version, 'custom.rainForecast', __CLASS__);
      else
        $rainForecast_template = getTemplate('core', $version, 'rainForecast', __CLASS__);
      $json = $this->getCmd(null, 'rainForecast_json');
      $tsArray = array();
      $precipArray = array();
      $cumul1h = 0;
      $startIdx = -1;
      $endIdx = -1;
      $nbminPluie = 0;
      if(is_object($json)) {
        $decAll = json_decode($json->execCmd(),true);
        if($decAll == null) {
          log::add(__CLASS__, 'debug', __FILE__ ." " .__LINE__ ." Json_decode error : " .json_last_error_msg());
        }
        else {
          $cnt = count($decAll);
          for ($i = 0; $i < $cnt; $i++) {
            $dec=$decAll[$i];
            /* {"dt":1618768920,"precipitation":0.133} */
            $ts = $dec['dt'];
            $tsArray[$i] = "'" . strftime('%H:%M',$ts) ."'";
            $precipitation = $dec['precipitation'];
            if($precipitation && $startIdx == -1) { $start = $ts; $startIdx = $i; } 
            if(!$precipitation && $startIdx != -1 && $endIdx == -1) { $end = $ts; $endIdx=$i; }
            $cumul1h += $precipitation;
            $precipArray[] = $precipitation;
            if($precipitation) $nbminPluie++;
          }
        }
        if($cumul1h == 0) {
          $txtRain = "Pas de précipitations prévues (" .trim($tsArray[0],"'") ."-" .trim($tsArray[($cnt-1)],"'") .")";
          $yTxtRain = 30;
        }
        else {
          $txtRain = "Précipitations prévues: " .round(($cumul1h/$nbminPluie),2) ."mm.";
          $yTxtRain = 5;
          if($startIdx > 0) $txtRain .= " Début à " .strftime('%H:%M',$start);
          if($endIdx > 0 && $end > $start) $txtRain .= " Fin à " .strftime('%H:%M',$end);
        }
        $id = $this->getId();
        $replaceRF['#id#'] = $id;
        $replaceRF['#txtRain#'] = $txtRain;
        $replaceRF['#yTxtRain#'] = $yTxtRain;
        $replaceRF['#tsArray#'] = implode(',',$tsArray);
        $replaceRF['#precipArray#'] = implode(',',$precipArray);
        $replace['#rainForecast#'] .= template_replace($replaceRF, $rainForecast_template);
      }
      else $replace['#rainForecast#'] ='rainForecast_json Cmd not found. Equipment should be re-saved';
    }
    else $replace['#rainForecast#'] = '';

      // ALERTE METEO
    if ($this->getConfiguration('alerts', 0) == 0) $replace['#alerts#'] = '';
    else {
      $txtAlert = '';
      $alertCmd = $this->getCmd(null, 'txtAlerts_json');
      if(is_object($alertCmd)) {
        $alertJson = $alertCmd->execCmd();
        $alert =json_decode($alertJson,true);
        if($alert === null) {
          log::add(__CLASS__,'debug',"Unable to decode json. $alertJson Err: " .json_last_error_msg()); 
          $nbalert = 0;
        }
        else $nbalert= count($alert);
        if($nbalert == 0) $txtAlert = "Pas d'alerte météo en cours";
        else {
          $known = array();
            // Recup alertes connues
          $fileName= __DIR__ ."/../../data/KnownWeatherAlerts.json";
          $fcontent= file_get_contents($fileName);
          if($fcontent !== false) {
            if(strlen($fcontent)) {
              $known = json_decode($fcontent,true);
              if($known == null) {
                log::add(__CLASS__,'warning',"Unable to decode file: $fileName Size:".strlen($fcontent) ." Err: " .json_last_error_msg()); 
              }
            }
          }
          else log::add(__CLASS__,'warning',"Unable to read file: $fileName"); 
          for($j=0;$j<$nbalert;$j++) {
            $found = -1;
            for($i=0;$i<count($known);$i++) {
              if(trim($alert[$j]['event']) == trim($known[$i]['OWtxt'])) {
                $found = $i;
                break;
              }
            }
// message::add(__FUNCTION__, "$j Cherche: [" .$alert[$j]['event'] ."] Found $found Nb known " .count($known));
            if($found == -1) { // Non trouvé
              if($txtAlert != '') $txtAlert .= "<br/>";
              $txtAlert .= $alert[$j]['event'] ." du " .strftime('%A %e %b %H:%M',$alert[$j]['start']) ." au " .strftime('%A %e %b %H:%M',$alert[$j]['end']);
              $known[] = array("sender_name" => $alert[$j]['sender_name'],"OWtxt" => $alert[$j]['event'], "TradFr" => $alert[$j]['event'], "Level" => 'blue', "Icon" => "wi-na");
log::add(__CLASS__,'debug', "$j Add [" .$alert[$j]['event'] ."] Count known: " .count($known));
              // Ajout condition dans fichier KnownWeatherAlerts.json
              $hdle = fopen($fileName, "wb");
              if($hdle !== FALSE) { fwrite($hdle, json_encode($known)); fclose($hdle); }
              else log::add(__CLASS__,'error',"Unable to write $fileName file."); 
            }
            else {
              if($txtAlert == '') $txtAlert = '<table style="width: 100%"><tr><td><b>Source alertes météo:</b> '.$alert[$j]['sender_name'] .' ';
              $txtAlert .= "<span title=\"" .$known[$found]['TradFr'] . "<br/>Du " .strftime('%A %e %b %H:%M',$alert[$j]['start'])
                ." au " .strftime('%A %e %b %H:%M',$alert[$j]['end']) ."\"> ";
              $txtAlert .= "&nbsp; <i class=\"wi " .$known[$found]['Icon'] ."\" style=\"font-size: 24px; color:" .$known[$found]['Level'] ."\"></i>";
              $txtAlert .= "</span>";
            }
          }
          if($txtAlert != '') $txtAlert .= '</td></tr></table>';
        }
      }
      else $txtAlert = "txtAlerts_json Cmd not found. Equipment should be re-saved";
      $replace['#alerts#'] = "<div style=\"position:relative;margin-left:5px;margin-top:2px;\"><span style=\"font-size: 0.9em;\">" .$txtAlert ."</span></div>";
    }

      // QUALITÉ DE L'AIR
    // DOC API: https://openweathermap.org/api/air-pollution
    if ($this->getConfiguration('air_quality', 0) == 0 ) { // Pas d'AQI
      $replace['#aqi#'] = '';
    }
    else {
      $aqiCmd = $this->getCmd(null, 'aqi_json');
      if(is_object($aqiCmd)) {
        $aqiJson = $aqiCmd->execCmd();
        $aqi =json_decode($aqiJson,true);
        switch($aqi[0]['main']['aqi']) {
        case 1:
          $aqicolor='#00ff1e'; $aqifont='black'; $aqiTxt = 'Bon';
          $helpAqi = "NO<sub>2</sub>: 0-50 PM<sub>10</sub>: 0-25 O<sub>3</sub> 0-60 PM<sub>2.5</sub> 0-15";
          break;
        case 2:
          $aqicolor='#FFde33'; $aqifont='black'; $aqiTxt = 'Moyen';
          $helpAqi = "NO<sub>2</sub>: 50-100 PM<sub>10</sub>: 25-50 O<sub>3</sub> 60-120 PM<sub>2.5</sub> 15-30";
          break;
        case 3:
          $aqicolor='#FF9933'; $aqifont='white'; $aqiTxt = 'Modéré';
          $helpAqi = "NO<sub>2</sub>: 100-200 PM<sub>10</sub>: 50-90 O<sub>3</sub> 120-180 PM<sub>2.5</sub> 30-55";
          break;
        case 4:
          $aqicolor='#CC0033'; $aqifont='white'; $aqiTxt = 'Mauvais';
          $helpAqi = "NO<sub>2</sub>: 200-400 PM<sub>10</sub>: 90-180 O<sub>3</sub> 180-240 PM<sub>2.5</sub> 55-110";
          break;
        case 5:
          $aqicolor='#660035'; $aqifont='white'; $aqiTxt = 'Très mauvais';
          $helpAqi = "NO<sub>2</sub>: &gt;400 PM<sub>10</sub>: &gt;180 O<sub>3</sub> &gt;240 PM<sub>2.5</sub> &gt;110";
          break;
        }
        $txtAqi = "<div title=\"Indice général: $aqiTxt (le ".date("d-m H:i:s",$aqi[0]['dt']) .") $helpAqi\" class=\"aqiGeneral\" style=\"background-color:$aqicolor; color:$aqifont\"><center>".$aqi[0]['main']['aqi']."</center></div><br/>";
        foreach($aqi[0]['components'] as $key => $value) {
          switch($key) {
            case 'co': $key2 ='CO'; $txt2 = "Monoxyde de carbone"; break;
            case 'no': $key2 ='NO'; $txt2 = "Monoxyde d'azote"; break;
            case 'no2': $key2 ='NO<sub>2</sub>'; $txt2 = "Dioxyde d'azote"; break;
            case 'o3': $key2 ='O<sub>3</sub>'; $txt2 = "Ozone"; break;
            case 'so2': $key2 ='SO<sub>2</sub>'; $txt2 = "Dioxyde de soufre"; break;
            case 'pm2_5': $key2 ='PM<sub>2.5</sub>'; $txt2 = "Particules fines &lt;2,5&mu;m combustion"; break;
            case 'pm10': $key2 ='PM<sub>10</sub>'; $txt2 = "Particules fines &lt;10&mu;m poussière, pollen"; break;
            case 'nh3': $key2 ='NH<sub>3</sub>'; $txt2 = "Ammoniac"; break;
            default: $key2 = $key; break;
          }
          $txtAqi .= "<span title=\"$txt2\"><strong>$key2:</strong>&nbsp;${value}&mu;g/m<sup>3</sup></span>&nbsp; ";
        }
      }
      else {
        $txtAqi = "aqi_json Cmd not found. Equipment should be re-saved";
      }
      $replace['#aqi#'] = "<div style=\"position:relative;margin-left:5px;margin-top:2px;\">Qualité de l'air: <span style=\"font-size: 0.8em;\">" .$txtAqi ."</span></div>";
    }

      // Date de la dernière mise à jour
    $replace['#dateMaj#'] = date("d-m H:i:s");
    $ow_call = $this->getCmd(null, 'timestamp_OW_call');
    if(is_object($ow_call)) $replace['#dateMajOW#'] = date("d-m H:i:s",$ow_call->execCmd());
    else $replace['#dateMajOW#'] = 'Inconnue';

    if (file_exists( __DIR__ ."/../template/$_version/custom.main.html"))
      return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'custom.main', __CLASS__)));
    else
      return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'main', __CLASS__)));
  } // toHtml

  public function fetchOpenweather($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
    
    $content = curl_exec($ch);
    if ($content === false) {
      log::add(__CLASS__,'error', __FUNCTION__ ." $url Failed curl_error: (" .curl_errno($ch) .") " .curl_error($ch));
      curl_close($ch);
      return(null);
    }
    curl_close($ch);
    return($content);
  }

  public function updateWeatherData() {
    if ($this->getConfiguration('cityID') == '') {
      throw new Exception(__('La ville ne peut être vide', __FILE__));
    }
    $changed = 0;
    $apiKey = trim(config::byKey('apikey', __CLASS__));
    $cityID = trim($this->getConfiguration('cityID'));
    $lat = $this->getConfiguration('latitude',120);
    $lon = $this->getConfiguration('longitude',120);
    $cityName = $this->getConfiguration('cityName','---');
    if($lat == 120 || $lon == 120)
      throw new Exception('Coordonnées du lieu inconnues. Resauvez l\'équipement: ' .$this->getName());

    $lang= substr(config::byKey('language','core', 'fr_FR'),0,2);
    $changed = $this->checkAndUpdateCmd('location', $cityName) || $changed;
      // Nouvelle API Onecall. Description voir https://openweathermap.org/api/one-call-api
    $url="https://api.openweathermap.org/data/2.5/onecall?lat=$lat&lon=$lon&units=metric&appid=$apiKey&lang=$lang"; // &exclude=current";
    // log::add(__CLASS__,'debug',"Fetch URL: $url");
    $content = $this->fetchOpenweather($url);
    if($content == null) return;
      // TODO de/commenter les 2 lignes
    // $hdle = fopen(__DIR__ ."/../../data/lastOneCallOpenweather.json", "wb");
    // if($hdle !== FALSE) { fwrite($hdle, $content); fclose($hdle); }

    $dec = json_decode($content,true);
    if($dec == null) {
      log::add(__CLASS__, 'error', __FUNCTION__ ." L:" .__LINE__ ." Json_decode error : " .json_last_error_msg() ." [" . substr($content,0,50) ."] ... [" .substr($content,-50) ."]");
      /* // debug
      $hdle = fopen("/var/www/html/" .__FUNCTION__ .date('Ymd-His').".json", "wb");
      if($hdle !== FALSE) { fwrite($hdle, $content); fclose($hdle); }
       */
      return;
    }

      // Recup meteo current en json
    $changed = false;
    if(isset($dec['current'])) {
      $this->checkAndUpdateCmd('timestamp_OW_call', $dec["current"]["dt"]);
      $changed = $this->checkAndUpdateCmd('temperature', $dec["current"]["temp"]) || $changed;
      $changed = $this->checkAndUpdateCmd('humidity', $dec["current"]["humidity"]) || $changed;
      $changed = $this->checkAndUpdateCmd('feels_like', $dec["current"]["feels_like"]) || $changed;
      $changed = $this->checkAndUpdateCmd('uv', round($dec['current']['uvi'])) || $changed;
      $changed = $this->checkAndUpdateCmd('visibility', $dec["current"]["visibility"]) || $changed;
      $changed = $this->checkAndUpdateCmd('clouds', $dec["current"]["clouds"]) || $changed;
      $changed = $this->checkAndUpdateCmd('pressure', $dec["current"]["pressure"]) || $changed;
      $changed = $this->checkAndUpdateCmd('wind_speed', round($dec["current"]["wind_speed"] * 3.6),1) || $changed;
      $changed = $this->checkAndUpdateCmd('wind_direction', $dec["current"]["wind_deg"]) || $changed;
      $changed = $this->checkAndUpdateCmd('sunrise', $dec["current"]["sunrise"]) || $changed;
      $changed = $this->checkAndUpdateCmd('sunset', $dec["current"]["sunset"]) || $changed;
      $changed = $this->checkAndUpdateCmd('condition', ucfirst($dec["current"]["weather"][0]["description"])) || $changed;
      $changed = $this->checkAndUpdateCmd('condition_id', $dec["current"]["weather"][0]["id"]) || $changed;
      $changed = $this->checkAndUpdateCmd('condition_icon', $dec["current"]["weather"][0]["icon"]) || $changed;
      if(isset($dec['current']['wind_gust']))
        $changed = $this->checkAndUpdateCmd('windGust', round($dec['current']['wind_gust'] * 3.6)) || $changed;
      else
        $changed = $this->checkAndUpdateCmd('windGust', '-') || $changed;
      $changed = $this->checkAndUpdateCmd('dewPoint', $dec['current']['dew_point']) || $changed;
      if(array_key_exists('rain', $dec["current"]) && array_key_exists('1h', $dec["current"]['rain'])) {
        $changed = $this->checkAndUpdateCmd('precipitation', $dec["current"]["rain"]["1h"]) || $changed;
      }
      else {
        $changed = $this->checkAndUpdateCmd('precipitation', 0) || $changed;
      }
      if(array_key_exists('snow', $dec["current"]) && array_key_exists('1h', $dec["current"]['snow'])) {
        $changed = $this->checkAndUpdateCmd('snow', $dec["current"]["snow"]["1h"]) || $changed;
      }
      else {
        $changed = $this->checkAndUpdateCmd('snow', 0) || $changed;
      }
    }
    else {
      $changed = $this->checkAndUpdateCmd('temperature', 0) || $changed;
      $changed = $this->checkAndUpdateCmd('humidity', 0) || $changed;
      $changed = $this->checkAndUpdateCmd('feels_like', 0) || $changed;
      $changed = $this->checkAndUpdateCmd('uv', 0) || $changed;
      $changed = $this->checkAndUpdateCmd('visibility', 0) || $changed;
      $changed = $this->checkAndUpdateCmd('clouds', 0) || $changed;
      $changed = $this->checkAndUpdateCmd('pressure', 0) || $changed;
      $changed = $this->checkAndUpdateCmd('wind_speed', 0) || $changed;
      $changed = $this->checkAndUpdateCmd('wind_direction', 0) || $changed;
      $changed = $this->checkAndUpdateCmd('sunrise', 0) || $changed;
      $changed = $this->checkAndUpdateCmd('sunset', 0) || $changed;
      $changed = $this->checkAndUpdateCmd('condition', '') || $changed;
      $changed = $this->checkAndUpdateCmd('condition_id', 0) || $changed;
      $changed = $this->checkAndUpdateCmd('condition_icon', '') || $changed;
      $changed = $this->checkAndUpdateCmd('windGust', 0) || $changed;
      $changed = $this->checkAndUpdateCmd('dewPoint', 0) || $changed;
      $changed = $this->checkAndUpdateCmd('precipitation', 0) || $changed;
      $changed = $this->checkAndUpdateCmd('snow', 0) || $changed;
    }

    if ($this->getConfiguration('rainForecast', 0) == 1 && isset($dec['minutely'])) {
      /* $cnt = count($dec['minutely']);
      log::add(__CLASS__,'error',"$cnt minutely forecast");
      $hdle = fopen(__DIR__ ."/../../data/" .__FUNCTION__ ."-minutely.json", "wb");
      if($hdle !== FALSE) { fwrite($hdle, json_encode($dec['minutely'])); fclose($hdle); }
     */
      $changed = $this->checkAndUpdateCmd('rainForecast_json', json_encode($dec['minutely'])) || $changed;
    }
    else $changed = $this->checkAndUpdateCmd('rainForecast_json', json_encode(null)) || $changed;

    if ($this->getConfiguration('forecast1h', 0) > 0 && isset($dec['hourly'])) {
      /* $cnt = count($dec['hourly']);
        log::add(__CLASS__,'error',"$cnt Hourly forecast");
        $hdle = fopen(__DIR__ ."/" .__FUNCTION__ ."-hourly.json", "wb");
        if($hdle !== FALSE) { fwrite($hdle, json_encode($dec['hourly'])); fclose($hdle); }
      */
      $changed = $this->checkAndUpdateCmd('forecast_1h_json', json_encode($dec['hourly'])) || $changed;
    }
    else $changed = $this->checkAndUpdateCmd('forecast_1h_json', json_encode(null)) || $changed;

    if ($this->getConfiguration('forecastDaily', 0) > 0 && isset($dec['daily'])) {
      /* $cnt = count($dec['daily']);
        log::add(__CLASS__,'error',"$cnt daily forecast");
        $hdle = fopen(__DIR__ ."/../../data/" .__FUNCTION__ ."-daily.json", "wb");
        if($hdle !== FALSE) { fwrite($hdle, json_encode($dec['daily'])); fclose($hdle); }
       */
      $changed = $this->checkAndUpdateCmd('forecast_daily_json', json_encode($dec['daily'])) || $changed;
    }
    else $changed = $this->checkAndUpdateCmd('forecast_daily_json', json_encode(null)) || $changed;

      // Alertes meteo en json
    if ($this->getConfiguration('alerts', 0) == 1 && isset($dec['alerts'])) {
      $cnt = count($dec['alerts']);
      // log::add(__CLASS__,'error',"$cnt Weather-alerts.");
      /*
      $hdle = fopen(__DIR__ ."/../../data/" .__FUNCTION__ ."-alerts.json", "wb");
      if($hdle !== FALSE) { fwrite($hdle, json_encode($dec['alerts'])); fclose($hdle); }
       */
      /*
       * {"sender_name":"METEO-FRANCE","event":"Moderate avalanches warning","start":1618545600,"end":1618632000,"description":"Although rather usual in this region, locally or potentially dangerous phenomena are expected. (such as local winds, summer thunderstorms, rising streams or high waves)"}
       */
      $txtAlerts = array();
      for($i=0;$i<$cnt;$i++) {
        $sender =  $dec['alerts'][$i]['sender_name'];
        $event =  $dec['alerts'][$i]['event'];
        $dateDeb = $dec['alerts'][$i]['start'];
        $dateFin = $dec['alerts'][$i]['end'];
        $txtAlerts[] = array("sender_name" => $sender, "event" => $event, "start" => $dateDeb, "end" => $dateFin);
      }
      $changed = $this->checkAndUpdateCmd('txtAlerts_json', json_encode($txtAlerts)) || $changed;
    }
    else $changed = $this->checkAndUpdateCmd('txtAlerts_json', '[]') || $changed;

      // qualité de l'air
    // DOC API: https://openweathermap.org/api/air-pollution
    if ($this->getConfiguration('air_quality', 0) == 1 ) {
      $url = "http://api.openweathermap.org/data/2.5/air_pollution?lat=$lat&lon=$lon&appid=$apiKey";
      $content = $this->fetchOpenweather($url);
      /*
        $hdle = fopen(__DIR__ ."/../../data/airquality.json", "wb");
        if($hdle !== FALSE) { fwrite($hdle, $content); fclose($hdle); }
       */
      if($content !== null) {
        $dec = json_decode($content,true);
        if($dec !== null) {
          $changed = $this->checkAndUpdateCmd('aqi_json', json_encode($dec['list']));
        }
        else {
          log::add(__CLASS__, 'debug', __FILE__ ." " .__LINE__ ." Json_decode error : " .json_last_error_msg());
          log::add(__CLASS__, 'debug', "air quality " ." " .substr($content,0,50) ." ... " .substr($content,-50));
          $changed = $this->checkAndUpdateCmd('aqi_json', '[]') || $changed;
        }
      }
    }

      // Recup previsions meteo 3 heures en json
    $cnt = $this->getConfiguration('forecast3h', 0);
    // message::add(__CLASS__, "Cnt: $cnt");
    if ($cnt > 0) {
      $url = "http://api.openweathermap.org/data/2.5/forecast?id=$cityID&appid=$apiKey&units=metric&lang=$lang";
      // $url = "http://api.openweathermap.org/data/2.5/forecast?appid=$apiKey&units=metric&lang=$lang&cnt=$cnt&lat=$lat&lon=$lon";
      // message::add(__CLASS__, "URL: $url");
      $content = $this->fetchOpenweather($url);
      if($content !== null) {
        $dec = json_decode($content,true);
        if($dec !== null)
          $changed = $this->checkAndUpdateCmd('forecast_3h_json', json_encode($dec['list'])) || $changed;
        else {
          log::add(__CLASS__, 'debug', __FILE__ ." " .__LINE__ ." Json_decode error : " .json_last_error_msg());
          log::add(__CLASS__, 'debug', "3h forecast " ." " .substr($content,0,50) ." ... " .substr($content,-50));
          $hdle = fopen(__DIR__ ."/../../data/lastForecastOpenweather.json", "wb");
          if($hdle !== FALSE) { fwrite($hdle, $content); fclose($hdle); }
          $changed = $this->checkAndUpdateCmd('forecast_3h_json', json_encode(null)) || $changed;
        }
      }
      else $changed = $this->checkAndUpdateCmd('forecast_3h_json', json_encode(null)) || $changed;
    }
    else $changed = $this->checkAndUpdateCmd('forecast_3h_json', json_encode(null)) || $changed;

    if ($changed) $this->refreshWidget();
  } // FIN updateWeatherData()

  public function refresh()
  {
          message::add(__CLASS__, 'Refresh  cmd: ' . $s);
  }

} // fin classe owm

class owmCmd extends cmd {
  /*     * *************************Attributs****************************** */

  public static $_widgetPossibility = array('custom' => false);

  /*     * ***********************Methode static*************************** */

  /*     * *********************Methode d'instance************************* */

  public function execute($_options = array()) {
    if ($this->getLogicalId() == 'refresh') {
      $this->getEqLogic()->updateWeatherData();
    }
    return false;
  }

  /*     * **********************Getteur Setteur*************************** */
}
