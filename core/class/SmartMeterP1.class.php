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
require_once dirname(__FILE__) . '/../../vendor/autoload.php';

use Mips\Http\HttpClient;

class SmartMeterP1 extends eqLogic {
	use MipsEqLogicTrait;

	public static $_encryptConfigKey = array('user', 'password');

	public function getCmdInfoValue($cmdId) {
		$cmd = $this->getCmd(null, $cmdId);
		if (!is_object($cmd)) return '';
		return $cmd->execCmd();
	}

	/**
	 * @return cron
	 */
	public static function setDaemon($sleepTime = 10) {
		$cron = cron::byClassAndFunction(__CLASS__, 'daemon');
		if (!is_object($cron)) {
			$cron = new cron();
		}
		$cron->setClass(__CLASS__);
		$cron->setFunction('daemon');
		$cron->setEnable(1);
		$cron->setDeamon(1);
		$cron->setDeamonSleepTime($sleepTime);
		$cron->setTimeout(1440);
		$cron->setSchedule('* * * * *');
		$cron->save();
		return $cron;
	}

	/**
	 * @return cron
	 */
	private static function getDaemonCron() {
		$cron = cron::byClassAndFunction(__CLASS__, 'daemon');
		if (!is_object($cron)) {
			return self::setDaemon();
		}
		return $cron;
	}

	public static function deamon_info() {
		$return = array();
		$return['log'] = '';
		$return['state'] = 'nok';
		$cron = self::getDaemonCron();
		if ($cron->running()) {
			$return['state'] = 'ok';
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
		$cron = self::getDaemonCron();
		$cron->run();
	}

	public static function deamon_stop() {
		$cron = self::getDaemonCron();
		$cron->halt();
	}

	public static function deamon_changeAutoMode($_mode) {
		$cron = self::getDaemonCron();
		$cron->setEnable($_mode);
		$cron->save();
	}


	public static function daemon() {
		foreach (self::byType(__CLASS__, true) as $eqLogic) {
			$host = $eqLogic->getConfiguration('host');
			if ($host == '') continue;

			log::add(__CLASS__, 'info', "Start refresh of data for {$eqLogic->getName()}");
			$date = new DateTime();
			$weekday = $date->format('w');
			$year = $date->format('y');
			$month = $date->format('n');
			$day = $date->format('j');
			$hour = $date->format('G');
			$minute = $date->format('i');

			$httpClient = new HttpClient("http://{$host}/", log::getLogger(__CLASS__));
			$response = $httpClient->doGet("?1=REFRESH|1|{$weekday}|{$year}|{$month}|{$day}|{$hour}|{$minute}");
			if (!$response->isSuccess()) {
				log::add(__CLASS__, 'error', "Erreur REFRESH: ({$response->getHttpStatusCode()}) - {$response->getError()}");
				continue;
			}
			$data = $response->getBody();
			log::add(__CLASS__, 'debug', "Data:{$data}");
			$arr = explode('|', $data);

			if ($arr[0] != 2) {
				log::add(__CLASS__, 'warning', "Incorrect response received");
				return;
			}

			$mod_max = 16;
			$color = $arr[1];
			$temp_valmod = $arr[2];
			$lastUpdate = $arr[3];
			$mask = 1;
			$index = 4;

			for ($mod = 0; $mod < $mod_max; $mod++) {
				if (($temp_valmod & $mask) == $mask) {
					switch ($arr[$index]) {
						case 0:
							$eqLogic->parseImporExport(array_slice($arr, $index, 12));
							$index += 12;
							break;
						case 1:
							$eqLogic->parseImport(array_slice($arr, $index, 12));
							$index += 12;
							break;
						case 2:
							$eqLogic->parseExport(array_slice($arr, $index, 12));
							$index += 12;
							break;
						case 5:
							$eqLogic->parseImportHigh(array_slice($arr, $index, 12));
							$index += 12;
							break;
						case 6:
							$eqLogic->parseImportLow(array_slice($arr, $index, 12));
							$index += 12;
							break;
						case 7:
							$eqLogic->parseExportHigh(array_slice($arr, $index, 12));
							$index += 12;
							break;
						case 8:
							$eqLogic->parseExportLow(array_slice($arr, $index, 12));
							$index += 12;
							break;
						default:
							$index += 12;
							break;
					}
				}
				$mask *= 2;
			}
		}
	}

	private function parseImporExport($arr) {
		log::add(__CLASS__, 'debug', "Import-Export:" . implode('|', $arr));
		$this->checkAndUpdateCmd("importExportPower", $arr[2]);
		$this->checkAndUpdateCmd("importExportDay", $arr[3] / 1000);
		$this->checkAndUpdateCmd("importExportMonth", $arr[4] / 1000);
		$this->checkAndUpdateCmd("totalImport", $arr[5] / 1000);
		$this->checkAndUpdateCmd("totalExport", $arr[6] / 1000);
		$this->checkAndUpdateCmd("totalImportExport", ($arr[5] - $arr[6]) / 1000);
	}

	private function parseImport($arr) {
		log::add(__CLASS__, 'debug', "Import H+L:" . implode('|', $arr));
		$this->checkAndUpdateCmd("importPower", $arr[2]);
		$this->checkAndUpdateCmd("importDay", $arr[3] / 1000);
		$this->checkAndUpdateCmd("importMonth", $arr[4] / 1000);
		$this->checkAndUpdateCmd("totalImportHigh", $arr[5] / 1000);
		$this->checkAndUpdateCmd("totalImportLow", $arr[6] / 1000);
	}

	private function parseExport($arr) {
		log::add(__CLASS__, 'debug', "Export H+L:" . implode('|', $arr));
		$this->checkAndUpdateCmd("exportPower", $arr[2]);
		$this->checkAndUpdateCmd("exportDay", $arr[3] / 1000);
		$this->checkAndUpdateCmd("exportMonth", $arr[4] / 1000);
		$this->checkAndUpdateCmd("totalExportHigh", $arr[5] / 1000);
		$this->checkAndUpdateCmd("totalExportLow", $arr[6] / 1000);
	}

	private function parseImportHigh($arr) {
		log::add(__CLASS__, 'debug', "Import H:" . implode('|', $arr));
		$this->checkAndUpdateCmd("importHighPower", $arr[2]);
		$this->checkAndUpdateCmd("importHighDay", $arr[3] / 1000);
		$this->checkAndUpdateCmd("importHighMonth", $arr[4] / 1000);
	}

	private function parseImportLow($arr) {
		log::add(__CLASS__, 'debug', "Import L:" . implode('|', $arr));
		$this->checkAndUpdateCmd("importLowPower", $arr[2]);
		$this->checkAndUpdateCmd("importLowDay", $arr[3] / 1000);
		$this->checkAndUpdateCmd("importLowMonth", $arr[4] / 1000);
	}

	private function parseExportHigh($arr) {
		log::add(__CLASS__, 'debug', "Export H:" . implode('|', $arr));
		$this->checkAndUpdateCmd("exportHighPower", $arr[2]);
		$this->checkAndUpdateCmd("exportHighDay", $arr[3] / 1000);
		$this->checkAndUpdateCmd("exportHighMonth", $arr[4] / 1000);
	}

	private function parseExportLow($arr) {
		log::add(__CLASS__, 'debug', "Export L:" . implode('|', $arr));
		$this->checkAndUpdateCmd("exportLowPower", $arr[2]);
		$this->checkAndUpdateCmd("exportLowDay", $arr[3]);
		$this->checkAndUpdateCmd("exportLowMonth", $arr[4]);
	}

	public function createCommands($syncValues = false) {
		log::add(__CLASS__, 'debug', "Checking commands of {$this->getName()}");

		$this->createCommandsFromConfigFile(__DIR__ . '/../config/SmartMeterP1.json', 'SmartMeterP1');

		return $this;
	}

	public function postInsert() {
		$this->createCommands();
	}

	public function postSave() {
		$host = $this->getConfiguration('host');
		if ($host == '') return;
		log::add(__CLASS__, 'info', "Load config for {$this->getName()}");

		$httpClient = new HttpClient("http://{$host}/", log::getLogger(__CLASS__));
		$response = $httpClient->doGet("?1=LOAD_CONFIG");
		if (!$response->isSuccess()) {
			log::add(__CLASS__, 'error', "Erreur during LOAD_CONFIG: ({$response->getHttpStatusCode()}) - {$response->getError()}");
			return;
		}
		$data = $response->getBody();
		log::add(__CLASS__, 'debug', "Config:{$data}");
		$arr = explode('|', $data);
		$this->setConfiguration('meterId', $arr[2]);
		$this->save(true);
	}
}

class SmartMeterP1Cmd extends cmd {

	public function execute($_options = array()) {
		$eqLogic = $this->getEqLogic();
		log::add('SmartMeterP1', 'debug', "command: {$this->getLogicalId()} on {$eqLogic->getLogicalId()} : {$eqLogic->getName()}");
	}
}
