<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */
require_once('./Services/Logging/classes/class.ilLog.php');

/**
 * Class xvmpLog
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class xvmpLog extends ilLog {

	const LOG_TITLE = 'vimp.log';

	const DEBUG_DEACTIVATED = 0;
	const DEBUG_LEVEL_1 = 1;
	const DEBUG_LEVEL_2 = 2;
	const DEBUG_LEVEL_3 = 3;
	const DEBUG_LEVEL_4 = 4;

	/**
	 * @var xoctLog
	 */
	protected static $instance;
	/**
	 * @var int
	 */
	protected static $log_level = self::DEBUG_LEVEL_4;

	/**
	 * @return xvmpLog
	 */
	public static function getInstance() {
		if (! isset(self::$instance)) {
			self::$instance = new self(ILIAS_LOG_DIR, self::LOG_TITLE);
		}

		return self::$instance;
	}

	/**
	 * @param $log_level
	 */
	public static function init($log_level) {
		self::$log_level = $log_level;
	}


	/**
	 * @param $log_level
	 *
	 * @return bool
	 */
	public static function relevant($log_level) {
		return $log_level <= self::$log_level;
	}

	/**
	 * @param      $a_msg
	 * @param null $log_level
	 */
	function write($a_msg, $log_level = null) {
		if (self::relevant($log_level)) {
			parent::write($a_msg);
		}
	}


	public function writeTrace() {
		try {
			throw new Exception();
		} catch (Exception $e) {
			parent::write($e->getTraceAsString());
		}
	}


	/**
	 * @return mixed
	 */
	public function getLogDir() {
		return ILIAS_LOG_DIR;
	}

	/**
	 * @return string
	 */
	public static function getFullPath() {
		$log = self::getInstance();

		return $log->getLogDir() . '/' . $log->getLogFile();
	}

	/**
	 * @return string
	 */
	public function getLogFile() {
		return self::LOG_TITLE;
	}


	/**
	 * @return int
	 */
	public static function getLogLevel() {
		return self::$log_level;
	}
}