<?php

require_once __DIR__ . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../../vendor/autoload.php';

class SmartMeterP1 extends eqLogic {
	use MipsEqLogicTrait;

	/**
	 * @return cron
	 */
	public static function setDaemon() {
		$cron = cron::byClassAndFunction(__CLASS__, 'daemon');
		if (!is_object($cron)) {
			$cron = new cron();
		}
		$cron->setClass(__CLASS__);
		$cron->setFunction('daemon');
		$cron->setEnable(1);
		$cron->setDeamon(1);
		$cron->setDeamonSleepTime(config::byKey('daemonSleepTime', __CLASS__, 5));
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

	public static function postConfig_daemonSleepTime($value) {
		self::setDaemon();
		$deamon_info = self::deamon_info();
		if ($deamon_info['state'] == 'ok') {
			self::deamon_start();
		}
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
			$today = $date->format('Y-m-d');
			if ($lastDay === $today) {
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
				log::add(__CLASS__, 'error', "Cannot connect to {$this->getName()} ({$host}:{$port})");
			} else {
				log::add(__CLASS__, 'debug', "Connected to {$this->getName()} ({$host}:{$port})");

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
					"71.7.0",	// intensity 1
					"21.7.0",	// import power 1
					"41.7.0",	// import power 2
					"61.7.0",	// import power 3
					"22.7.0",	// export power 1
					"42.7.0",	// export power 2
					"62.7.0"	// export power 3
				];
				$unused_codes = [
					"1.4.0", // power last quarter; not used in plugin
					"17.0.0" // power limit for client with pre-paid contract; not used in plugin
				];
				$fullregex = '/\d\-\d:(\d+\.\d+\.\d+)\((\d+\.\d{1,3})\*([VAkWh]+){1,3}\)/';
				$coderegex = '/\d\-\d:(\d+\.\d+\.\d+)\((.*)\)/';
				$results = [];
				while (($line =  fgets($f, 4096)) !== false) {
					$line = trim($line);
					if (empty($line)) continue;
					log::add(__CLASS__, 'debug', "Parse: {$line}");
					$matches = [];
					if (preg_match($fullregex, $line, $matches) === 1) {
						$code = $matches[1];
						if (in_array($code, $codes)) {
							$value = $matches[2];
							$unit = $matches[3];
							if ($unit === 'kW') {
								$value *= 1000;
							}
							$this->checkAndUpdateCmd($code, $value);
							$results[$code] = $value;
						} elseif (!in_array($code, $unused_codes)) {
							log::add(__CLASS__, 'warning', "Unknown code {$code}: {$line}");
						}
					} elseif (preg_match($coderegex, $line, $matches) === 1) {
						$code = $matches[1];
						$data = $matches[2];

						switch ($code) {
							case '1.0.0': // datetime; ex:'240118094756W' => 24/01/18 09:47:56
							case '1.6.0': // max power / quarter this month
							case '31.4.0': // current limit
							case '96.3.10': // breaker state?
							case '98.1.0':
								// not usefull
								break;
							case '96.1.1': // serial number
							case '96.1.4': // id
								$this->checkAndUpdateCmd($code, $data);
								break;
							case '96.14.0': // day/night
								$this->checkAndUpdateCmd($code, $data == '0001');
								break;
							case '96.13.0': // message and last code from the run
								if ($data != '') {
									log::add(__CLASS__, 'info', "Message received: {$code}={$data}");
								}
								$this->checkAndUpdateCmd('totalImport', $results['1.8.1'] + $results['1.8.2']);
								$this->checkAndUpdateCmd('totalExport', $results['2.8.1'] + $results['2.8.2']);
								$this->checkAndUpdateCmd('Import-Export', $results['1.7.0'] - $results['2.7.0']);
								break 2; // break from switch & while because last code from the run
							default:
								log::add(__CLASS__, 'warning', "Unknown data: {$code}={$data}");
								break;
						}
					} else {
						// log::add(__CLASS__, 'debug', "cannot extract actual code & value from raw data: {$line}");
					}
				}
			}
		} catch (\Throwable $th) {
			log::add(__CLASS__, 'error', "Error with {$this->getName()} ({$host}:{$port}): {$th->getMessage()}");
		} finally {
			log::add(__CLASS__, 'debug', "Closing connection to {$this->getName()} ({$host}:{$port})");
			fclose($f);
		}
		log::add(__CLASS__, 'info', "Successfuly refreshed values of {$this->getName()} ({$host}:{$port})");
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
