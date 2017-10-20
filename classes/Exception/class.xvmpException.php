<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class xvmpException
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class xvmpException extends Exception {

	const API_CALL_UNSUPPORTED = 10;
	const NO_USER_MAPPING = 20;
	const API_CALL_STATUS_500 = 500;
	const API_CALL_STATUS_403 = 403;
	const API_CALL_STATUS_404 = 404;
	const API_CALL_BAD_CREDENTIALS = 401;

	/**
	 * @var array
	 */
	protected static $messages = array(
		self::API_CALL_UNSUPPORTED => 'This Api-Call is not supported',
		self::API_CALL_STATUS_500 => 'An error occurred while communicating with the ViMP-Server',
		self::API_CALL_STATUS_403 => 'Access denied',
		self::API_CALL_STATUS_404 => 'Not Found',
		self::NO_USER_MAPPING => 'Your user-account cannot communicate with the ViMP-Server. please contact your system administrator.',
		self::API_CALL_BAD_CREDENTIALS => 'The ViMP-Server cannot be accessed at the moment.',

	);


	/**
	 * @param string $code
	 * @param string $additional_message
	 */
	public function __construct($code, $additional_message = '') {
		$message = '';
		if (isset(self::$messages[$code])) {
			$message = self::$messages[$code];
		}
		if ($additional_message) {
			$message .= ': ' . $additional_message;
		}
		parent::__construct($message, $code);
	}

}