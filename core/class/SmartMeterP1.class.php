<?php

require_once __DIR__ . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../../vendor/autoload.php';

class SmartMeterP1 extends eqLogic {
	use MipsEqLogicTrait;

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
		/** @var SmartMeterP1 */
		foreach (self::byType(__CLASS__, true) as $eqLogic) {
			$eqLogic->refreshP1();
		}
	}

	public static function cron() {
		/** @var SmartMeterP1 */
		foreach (self::byType(__CLASS__, true) as $eqLogic) {
			$currentImport = $eqLogic->getCmdInfoValue('totalImport', 0);

			/** @var SmartMeterP1Cmd */
			$dayImport = $eqLogic->getCmd('info', 'dayImport');
			if (is_object($dayImport)) {
				$dayIndex = $dayImport->getCache('index', 0);
				if ($dayIndex == 0) {
					$dayImport->setCache('index', $currentImport);
					$dayIndex = $currentImport;
				}
				$dayImport->event(round($currentImport - $dayIndex, 3));
			}
			/** @var SmartMeterP1Cmd */
			$monthImport = $eqLogic->getCmd('info', 'monthImport');
			if (is_object($monthImport)) {
				$monthIndex = $monthImport->getCache('index', 0);
				if ($monthIndex == 0) {
					$monthImport->setCache('index', $currentImport);
					$monthIndex = $currentImport;
				}
				$monthImport->event(round($currentImport - $monthIndex, 3));
			}

			$currentExport = $eqLogic->getCmdInfoValue('totalExport', 0);

			/** @var SmartMeterP1Cmd */
			$dayExport = $eqLogic->getCmd('info', 'dayExport');
			if (is_object($dayExport)) {
				$dayIndex = $dayExport->getCache('index', 0);
				if ($dayIndex == 0) {
					$dayExport->setCache('index', $currentExport);
					$dayIndex = $currentExport;
				}
				$dayExport->event(round($currentExport - $dayIndex, 3));
			}
			/** @var SmartMeterP1Cmd */
			$monthExport = $eqLogic->getCmd('info', 'monthExport');
			if (is_object($monthExport)) {
				$monthIndex = $monthExport->getCache('index', 0);
				if ($monthIndex == 0) {
					$monthExport->setCache('index', $currentExport);
					$monthIndex = $currentExport;
				}
				$monthExport->event(round($currentExport - $monthIndex, 3));
			}
		}
	}

	public static function dailyReset() {
		/** @var SmartMeterP1 */
		foreach (self::byType(__CLASS__, true) as $eqLogic) {
			$currentImport = $eqLogic->getCmdInfoValue('totalImport', 0);
			$currentExport = $eqLogic->getCmdInfoValue('totalExport', 0);

			/** @var SmartMeterP1Cmd */
			$dayImport = $eqLogic->getCmd('info', 'dayImport');
			if (is_object($dayImport)) {
				$dayImport->setCache('index', $currentImport);
			}
			/** @var SmartMeterP1Cmd */
			$dayExport = $eqLogic->getCmd('info', 'dayExport');
			if (is_object($dayExport)) {
				$dayExport->setCache('index', $currentExport);
			}

			$date = new DateTime();
			$lastDay = $date->format('Y-m-t');
			$toDay = $date->format('Y-m-d');
			if ($lastDay === $toDay) {
				/** @var SmartMeterP1Cmd */
				$monthImport = $eqLogic->getCmd('info', 'monthImport');
				if (is_object($monthImport)) {
					$monthImport->setCache('index', $currentImport);
				}
				/** @var SmartMeterP1Cmd */
				$monthExport = $eqLogic->getCmd('info', 'monthExport');
				if (is_object($monthExport)) {
					$monthExport->setCache('index', $currentExport);
				}
			}
		}
	}

	private function refreshP1() {
		$host = $this->getConfiguration('host');
		if ($host == '') return;

		$port = $this->getConfiguration('port', 8088);
		if ($port == '') return;

		$cfgTimeOut = "5";

		try {
			$f = fsockopen($host, $port, $cfgTimeOut);

			if (!$f) {
				log::add(__CLASS__, 'warning', "Cannot connect to {$this->getName()} ({$host}:{$port})");
			} else {
				log::add(__CLASS__, 'info', "Connected to {$this->getName()} ({$host}:{$port})");

				$codes = [
					"1.8.1",	// import high
					"1.8.2",	// import low
					"2.8.1",	// export high
					"2.8.2",	// export low
					"1.7.0",	// import power
					"2.7.0",	// export power
					"32.7.0",	// voltage 1
					"52.7.0",	// voltage 2
					"72.7.0",	// voltage 3
					"31.7.0",	// intensity 1
					"51.7.0",	// intensity 1
					"71.7.0"	// intensity 1
				];
				$fullregex = '/\d\-\d:(\d+\.\d+\.\d+)\((\d+\.\d{1,3})\*([VAkWh]+){1,3}\)/';
				$coderegex = '/\d\-\d:(\d+\.\d+\.\d+).*/';
				$results = [];
				while (($data =  fgets($f, 4096)) !== false) {
					$matches = [];
					if (preg_match($fullregex, $data, $matches) === 1) {
						$current_code = $matches[1];
						if (in_array($current_code, $codes)) {
							$value = $matches[2];
							$unit = $matches[3];
							// log::add(__CLASS__, 'debug', "{$current_code}: {$value} {$unit}");
							if ($unit === 'kW') {
								$value *= 1000;
							}
							$this->checkAndUpdateCmd($current_code, $value);
							$results[$current_code] = $value;
						} else {
							// log::add(__CLASS__, 'debug', "Unknown code {$current_code}");
						}
					} elseif (preg_match($coderegex, $data, $matches) === 1) {
						$current_code = $matches[1];
						if ($current_code === "96.13.0") {
							$this->checkAndUpdateCmd('totalImport', $results['1.8.1'] + $results['1.8.2']);
							$this->checkAndUpdateCmd('totalExport', $results['2.8.1'] + $results['2.8.2']);
							$this->checkAndUpdateCmd('Import-Export', $results['1.7.0'] - $results['2.7.0']);
							// log::add(__CLASS__, 'debug', "============");
							sleep(1);
						}
					} else {
						// log::add(__CLASS__, 'debug', "cannot extract actual code & value from raw data: {$data}");
					}
				}
			}
		} catch (\Throwable $th) {
			log::add(__CLASS__, 'error', "Error with {$this->getName()} ({$host}:{$port}): {$th->getMessage()}");
		} finally {
			log::add(__CLASS__, 'info', "Closing connection to {$this->getName()} ({$host}:{$port})");
			fclose($f);
		}
	}

	private static function getTopicPrefix() {
		return config::byKey('topic_prefix', __CLASS__, 'lowi', true);
	}

	private static function tryPublishToMQTT($topic, $value) {
		try {
			$_MQTT2 = 'mqtt2';
			if (!class_exists($_MQTT2)) {
				log::add(__CLASS__, 'debug', __('Le plugin mqtt2 n\'est pas installé', __FILE__));
				return;
			}
			$topic = self::getTopicPrefix() . '/' . $topic;
			$_MQTT2::publish($topic, $value);
			log::add(__CLASS__, 'debug', "published to mqtt: {$topic}={$value}");
		} catch (\Throwable $th) {
			log::add(__CLASS__, 'warning', __('Une erreur s\'est produite dans le plugin mqtt2:', __FILE__) . $th->getMessage());
		}
	}

	public function createCommands($syncValues = false) {
		log::add(__CLASS__, 'debug', "Checking commands of {$this->getName()}");

		$this->createCommandsFromConfigFile(__DIR__ . '/../config/p1.json', 'p1');

		return $this;
	}

	public function postInsert() {
		$this->createCommands();
	}

	public function postSave() {
		// $host = $this->getConfiguration('host');
		// if ($host == '') return;

	}
}

class SmartMeterP1Cmd extends cmd {

	public function execute($_options = array()) {
		$eqLogic = $this->getEqLogic();
		log::add('SmartMeterP1', 'debug', "command: {$this->getLogicalId()} on {$eqLogic->getLogicalId()} : {$eqLogic->getName()}");
	}
}
