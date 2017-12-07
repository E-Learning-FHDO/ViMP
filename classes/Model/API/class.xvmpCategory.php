<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class xvmpCategory
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class xvmpCategory extends xvmpObject {

	const DEFAULT_CACHE_TTL = 86400; // 1 day

	public static function getObjectAsArray($id) {
		$key = self::class;
		$existing = xvmpCacheFactory::getInstance()->get($key);
		if ($existing && isset($existing[$key])) {
			xvmpCurlLog::getInstance()->write('CACHE: used cached: ' . $key . '-' . $id, xvmpCurlLog::DEBUG_LEVEL_2);
			return $existing[$key];
		}

		$response = xvmpRequest::getCategory($id)->getResponseArray()['category'];

		if ($existing) {
			$cache = $existing;
			$cache[] = $response;
		} else {
			$cache = array($response);
		}

		self::cache($key, $cache);

		return $response;
	}

	public static function getAllAsArray() {
		$key = self::class;
		$existing = xvmpCacheFactory::getInstance()->get($key);
		if ($existing && ($existing['loaded'] == 1)) {
			unset($existing['loaded']);
			xvmpCurlLog::getInstance()->write('CACHE: used cached: ' . $key, xvmpCurlLog::DEBUG_LEVEL_2);
			return $existing;
		}

		xvmpCurlLog::getInstance()->write('CACHE: cached not used: ' . $key, xvmpCurlLog::DEBUG_LEVEL_2);

		$response = xvmpRequest::getCategories()->getResponseArray()['categories']['category'];
		$response['loaded'] = 1;
		self::cache($key, $response);

		unset($response['loaded']);
		return $response;
	}


	public static function cache($identifier, $object, $ttl = NULL) {
		parent::cache($identifier, $object, ($ttl ? $ttl : xvmpConf::getConfig(xvmpConf::F_CACHE_TTL_CATEGORIES)));
	}


	/**
	 * @var int
	 */
	protected $cid;
	/**
	 * @var int
	 */
	protected $pid;
	/**
	 * @var String
	 */
	protected $culture;
	/**
	 * @var String
	 */
	protected $name;
	/**
	 * @var String
	 */
	protected $description;
	/**
	 * @var String
	 */
	protected $categorytype;
	/**
	 * @var String
	 */
	protected $status;
	/**
	 * @var String
	 */
	protected $picture;
	/**
	 * @var int
	 */
	protected $weight;
	/**
	 * @var String
	 */
	protected $created_at;
	/**
	 * @var String
	 */
	protected $updated_at;


	/**
	 * @return int
	 */
	public function getId() {
		return $this->cid;
	}

	/**
	 * @return int
	 */
	public function getCid() {
		return $this->cid;
	}


	/**
	 * @param int $cid
	 */
	public function setCid($cid) {
		$this->cid = $cid;
	}


	/**
	 * @return int
	 */
	public function getPid() {
		return $this->pid;
	}


	/**
	 * @param int $pid
	 */
	public function setPid($pid) {
		$this->pid = $pid;
	}


	/**
	 * @return String
	 */
	public function getCulture() {
		return $this->culture;
	}


	/**
	 * @param String $culture
	 */
	public function setCulture($culture) {
		$this->culture = $culture;
	}


	/**
	 * @return String
	 */
	public function getName() {
		return $this->name;
	}

	public function getNameWithPath() {
		$path = array($this->getName());
		$category = $this;
		while ($pid = $category->getPid()) {
			$category = xvmpCategory::find($pid);
			array_unshift($path, $category->getName());
		}
		return implode(' » ', $path);
	}


	/**
	 * @param String $name
	 */
	public function setName($name) {
		$this->name = $name;
	}


	/**
	 * @return String
	 */
	public function getDescription() {
		return $this->description;
	}


	/**
	 * @param String $description
	 */
	public function setDescription($description) {
		$this->description = $description;
	}


	/**
	 * @return String
	 */
	public function getCategorytype() {
		return $this->categorytype;
	}


	/**
	 * @param String $categorytype
	 */
	public function setCategorytype($categorytype) {
		$this->categorytype = $categorytype;
	}


	/**
	 * @return String
	 */
	public function getStatus() {
		return $this->status;
	}


	/**
	 * @param String $status
	 */
	public function setStatus($status) {
		$this->status = $status;
	}


	/**
	 * @return String
	 */
	public function getPicture() {
		return $this->picture;
	}


	/**
	 * @param String $picture
	 */
	public function setPicture($picture) {
		$this->picture = $picture;
	}


	/**
	 * @return int
	 */
	public function getWeight() {
		return $this->weight;
	}


	/**
	 * @param int $weight
	 */
	public function setWeight($weight) {
		$this->weight = $weight;
	}


	/**
	 * @return String
	 */
	public function getCreatedAt() {
		return $this->created_at;
	}


	/**
	 * @param String $created_at
	 */
	public function setCreatedAt($created_at) {
		$this->created_at = $created_at;
	}


	/**
	 * @return String
	 */
	public function getUpdatedAt() {
		return $this->updated_at;
	}


	/**
	 * @param String $updated_at
	 */
	public function setUpdatedAt($updated_at) {
		$this->updated_at = $updated_at;
	}


}