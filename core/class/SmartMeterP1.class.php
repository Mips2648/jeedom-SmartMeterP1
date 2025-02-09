<?php

require_once __DIR__ . '/../../../../core/php/core.inc.php';
require_once __DIR__ . '/../../vendor/autoload.php';

class SmartMeterP1 extends eqLogic {
	use MipsEqLogicTrait;

	const PYTHON_PATH = __DIR__ . '/../../resources/venv/bin/python3';

	const DEF_CONFIG__P1_PORT = 8088;
	const DEF_CONFIG_SOCKET_PORT = 55075;
	const DEF_CONFIG_CYCLE = 2;

	protected static function getSocketPort() {
		return config::byKey('socketport', __CLASS__, self::DEF_CONFIG_SOCKET_PORT);;
	}

	public static function dependancy_install() {
		log::remove(__CLASS__ . '_update');
		return array('script' => __DIR__ . '/../../resources/install_#stype#.sh', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}

	public static function dependancy_info() {
		$return = array();
		$return['log'] = log::getPathToLog(__CLASS__ . '_update');
		$return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependance';
		$return['state'] = 'ok';
		if (file_exists(jeedom::getTmpFolder(__CLASS__) . '/dependance')) {
			$return['state'] = 'in_progress';
		} elseif (!self::pythonRequirementsInstalled(self::PYTHON_PATH, __DIR__ . '/../../resources/requirements.txt')) {
			$return['state'] = 'nok';
		}
		return $return;
	}

	public static function deamon_info() {
		$return = array();
		$return['log'] = __CLASS__;
		$return['launchable'] = 'ok';
		$return['state'] = 'nok';
		$pid_file = jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
		if (file_exists($pid_file)) {
			if (@posix_getsid(trim(file_get_contents($pid_file)))) {
				$return['state'] = 'ok';
			} else {
				shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
			}
		}
		return $return;
	}

	public static function deamon_start() {
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}

		$path = realpath(__DIR__ . '/../../resources');
		$cmd = self::PYTHON_PATH . " {$path}/p1daemon.py";
		$cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));
		$cmd .= ' --socketport ' . self::getSocketPort();
		$cmd .= ' --cycle ' . config::byKey('cycle', __CLASS__, self::DEF_CONFIG_CYCLE);
		$cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/SmartMeterP1/core/php/jeeSmartMeterP1.php';
		$cmd .= ' --apikey ' . jeedom::getApiKey(__CLASS__);
		$cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
		log::add(__CLASS__, 'info', 'Lancement démon');
		$result = exec($cmd . ' >> ' . log::getPathToLog(__CLASS__ . '_daemon') . ' 2>&1 &');
		$i = 0;
		while ($i < 10) {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				break;
			}
			sleep(1);
			$i++;
		}
		if ($i >= 10) {
			log::add(__CLASS__, 'error', __('Impossible de lancer le démon', __FILE__), 'unableStartDeamon');
			return false;
		}
		message::removeAll(__CLASS__, 'unableStartDeamon');

		/** @var SmartMeterP1 */
		foreach (eqLogic::byType(__CLASS__, true) as $eqLogic) {
			$eqLogic->checkAndUpdateCmd('status', 0);
			if ($eqLogic->getConfiguration('autoConnect', 1) == 1) {
				$eqLogic->connectP1();
			}
		}
		return true;
	}

	public static function deamon_stop() {
		$pid_file = jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
		if (file_exists($pid_file)) {
			log::add(__CLASS__, 'info', 'Arrêt démon');
			$pid = intval(trim(file_get_contents($pid_file)));
			system::kill($pid);
		}
		sleep(1);
		system::kill('p1daemon.py');
		// system::fuserk(config::byKey('socketport', __CLASS__));
		sleep(1);

		/** @var SmartMeterP1 */
		foreach (eqLogic::byType(__CLASS__, true) as $eqLogic) {
			$eqLogic->checkAndUpdateCmd('status', 0);
		}
	}

	private static function isDaemonStarted() {
		$daemon_info = self::deamon_info();
		return ($daemon_info['state'] === 'ok');
	}

	public function handleMessage($data) {
		log::add(__CLASS__, 'debug', "handleMessage: " . json_encode($data));
		foreach ($data as $code => $value) {
			$this->checkAndUpdateCmd($code, $value);
			if ($code == 'status') {
				log::add(__CLASS__, 'info', $this->getName() . ' ' . ($value == 1 ? 'connected' : 'disconnected'));
			}
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

			if ($eqLogic->getConfiguration('autoConnect', 1) == 1 && $eqLogic->getCmdInfoValue('status', 0) == 0) {
				$eqLogic->connectP1();
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

	public function createCommands() {
		log::add(__CLASS__, 'debug', "Checking commands of {$this->getName()}");

		$commands = self::getCommandsFileContent(__DIR__ . '/../config/p1.json');

		$this->createCommandsFromConfig($commands['p1']);
		$this->createCommandsFromConfig($commands['totals']);
		$this->createCommandsFromConfig($commands['meta']);

		return $this;
	}

	public function preInsert() {
		$this->setConfiguration('autoConnect', 1);
	}

	public function postInsert() {
		$this->createCommands();
	}

	public function preUpdate() {
		if ($this->getIsEnable() == 0) return;

		if ($this->getLogicalId() == '') throw new Exception(__("Vous devez saisir une adresse IP pour activer l'équipement", __FILE__));
	}

	public function postUpdate() {
		if ($this->getIsEnable() == 1 && $this->getConfiguration('autoConnect', 1) == 1) {
			$this->connectP1();
		} else {
			$this->disconnectP1();
		}
	}

	public function preRemove() {
		$this->disconnectP1();
	}

	public function connectP1() {
		if (!self::isDaemonStarted()) return;

		log::add(__CLASS__, 'info', "Connecting to {$this->getName()}");

		$params = [
			'action' => 'connect',
			'host' => $this->getLogicalId(),
			'port' => $this->getConfiguration('port', self::DEF_CONFIG__P1_PORT)
		];
		$this->sendToDaemon($params);
	}

	public function disconnectP1() {
		if (!self::isDaemonStarted()) return;

		log::add(__CLASS__, 'info', "Disconnecting from {$this->getName()}");

		$params = [
			'action' => 'disconnect',
			'host' => $this->getLogicalId()
		];
		$this->sendToDaemon($params);
	}
}

class SmartMeterP1Cmd extends cmd {

	public function dontRemoveCmd() {
		return true;
	}

	public function execute($_options = array()) {
		/** @var SmartMeterP1 */
		$eqLogic = $this->getEqLogic();
		log::add('SmartMeterP1', 'debug', "command: {$this->getLogicalId()} on {$eqLogic->getLogicalId()} : {$eqLogic->getName()}");
		switch ($this->getLogicalId()) {
			case 'connect':
				$eqLogic->connectP1();
				break;
			case 'disconnect':
				$eqLogic->disconnectP1();
				break;
		}
	}
}
