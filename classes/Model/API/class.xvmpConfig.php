<?php

declare(strict_types=1);

/**
 * Class xvmpConfig
 *
 * @author Theodor Truffer <tt@studer-raimann.ch>
 */
class xvmpConfig extends xvmpObject {

    /**
     * @param $id
     *
     * @return array
     * @throws xvmpException
     */
	public static function getObjectAsArray($id): array
    {
		$key = self::class . '-' . $id;
		$existing = xvmpCacheFactory::getInstance()->get($key);
		if ($existing) {
			xvmpCurlLog::getInstance()->write('CACHE: used cached: ' . $key, xvmpCurlLog::DEBUG_LEVEL_2);
			return $existing;
		}

		$array = xvmpRequest::config($id)->getResponseArray()['config'];
		$array['id'] = $id;

		self::cache($key, $array);

		return $array;
	}


	/**
	 * @param       $identifier
	 * @param       $object
	 * @param null  $ttl
	 */
	public static function cache($identifier, $object, $ttl = null) {
		parent::cache($identifier, $object, xvmpConf::getConfig(xvmpConf::F_CACHE_TTL_CONFIG));
	}


	/**
	 * @var string
	 */
	protected string $name;
	/**
	 * @var string
	 */
	protected $value;


	/**
	 * @return string
	 */
	public function getName(): string
    {
		return $this->name;
	}


	/**
	 * @return string
	 */
	public function getValue()
    {
		return $this->value;
	}

}