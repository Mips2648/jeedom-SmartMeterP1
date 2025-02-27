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
    require_once __DIR__ . "/../../../../core/php/core.inc.php";

    if (!jeedom::apiAccess(init('apikey'), 'SmartMeterP1')) {
        echo __('Vous n\'etes pas autorisé à effectuer cette action', __FILE__);
        die();
    }

    if (init('test') != '') {
        echo 'OK';
        log::add('SmartMeterP1', 'debug', 'test from daemon');
        die();
    }
    $result = json_decode(file_get_contents("php://input"), true);
    if (!is_array($result)) {
        die();
    } else {
        foreach ($result as $ip => $data) {
            /** @var SmartMeterP1[] */
            $eqLogics = eqLogic::byLogicalId($ip, 'SmartMeterP1', true);
            foreach ($eqLogics as $eqLogic) {
                $eqLogic->handleMessage($data);
            }
        }
    }

    echo 'OK';
} catch (Exception $e) {
    log::add('SmartMeterP1', 'error', displayException($e));
}
