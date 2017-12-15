<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

use Detection\MobileDetect;

/**
 * Class xvmpMedium
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class xvmpMedium extends xvmpObject {

	const PUBLISHED_PUBLIC = 'public';
	const PUBLISHED_PRIVATE = 'private';
	const PUBLISHED_HIDDEN = 'hidden';

	public static $published_id_mapping = array(
		'public' => "0",
		'private' => "1",
		'hidden' => "2",
	);

	public static function find($id) {
		try {
			return parent::find($id);
		} catch (xvmpException $e) {
			if ($e->getCode() == 404) {
				$deleted = new xvmpDeletedMedium();
				$deleted->setMid($id);
				return $deleted;
			} else {
				throw $e;
			}
		}
	}


	public static function getUserMedia($ilObjUser = null, $filter = array()) {
		if (!$ilObjUser) {
			global $DIC;
			$ilUser = $DIC['ilUser'];
			$ilObjUser = $ilUser;
		}

		$uid = xvmpUser::getVimpUser($ilObjUser)->getUid();
		$response = xvmpRequest::getUserMedia($uid, $filter)->getResponseArray()['media']['medium'];
		if (!$response) {
			return array();
		}
		foreach ($response as $key => $medium) {
			if ($medium['mediatype'] != 'video') {
				unset($response[$key]);
			}
		}
		return $response;
	}

	public static function getSelectedAsArray($obj_id) {
		$selected = xvmpSelectedMedia::getSelected($obj_id);
		$videos = array();
		foreach ($selected as $rec) {
			try {
				$item = self::getObjectAsArray($rec->getMid());
			} catch (xvmpException $e) {
				if ($e->getCode() == 404) {
					$deleted = new xvmpDeletedMedium();
					$deleted->setMid($rec->getMid());
					$item = $deleted->__toArray();
				} else {
					throw $e;
				}
			}
			$item['visible'] = $rec->getVisible();
			$videos[] = $item;
		}
		return $videos;
	}

	public static function getFilteredAsArray(array $filter) {
		if (!isset($filter['title'])) {
			$filter['title'] = '';
		}

		try {
			$response = xvmpRequest::extendedSearch($filter)->getResponseArray();
		} catch (xvmpException $e) {    // api throws 404 exception if nothing is found
			if ($e->getCode() == 404) {
				return array();
			}
			throw $e;
		}

		if (isset($response['media']['medium']['mid'])) {
			return array($response['media']['medium']);
		}
		return $response['media']['medium'];
	}


	public static function getObjectAsArray($id) {
		$key = self::class . '-' . $id;
		$existing = xvmpCacheFactory::getInstance()->get($key);
		if ($existing) {
			xvmpCurlLog::getInstance()->write('CACHE: used cached: ' . $key, xvmpCurlLog::DEBUG_LEVEL_2);
			return $existing;
		}

		xvmpCurlLog::getInstance()->write('CACHE: cached not used: ' . $key, xvmpCurlLog::DEBUG_LEVEL_2);

		$response = xvmpRequest::getMedium($id)->getResponseArray()['medium'];
		$response = self::formatResponse($response);

		if ($response['status'] == 'legal') { // do not cache transcoding videos, we need to fetch them again to check the status
			self::cache($key, $response);
		}
		return $response;
	}

	public static function getAllAsArray() {
		$response = xvmpRequest::getMedia()->getResponseArray();
		return $response['media']['medium'];
	}

	public function update() {
		$params = array(
			'title' => $this->getTitle(),
			'description' => $this->getDescription(),
			'categories' => implode(',', $this->getCategories()),
			'tags' => is_array($this->getTags()) ? implode(',', $this->getTags()) : $this->getTags(),
			'mediapermissions' => implode(',',$this->getMediapermissions()),
			'hidden' => $this->getPublishedId(),
		);
		// TODO: uncomment when fixed by vimp
//		foreach (xvmpConf::getConfig(xvmpConf::F_FORM_FIELDS) as $field) {
//			$params[$field[xvmpConf::F_FORM_FIELD_ID]] = $this->$field[xvmpConf::F_FORM_FIELD_ID];
//		}
		$response = xvmpRequest::editMedium($this->getId(), $params);
		xvmpCacheFactory::getInstance()->delete(self::class . '-' . $this->getMid());
		self::cache(self::class . '-' . $this->getMid(),$this->__toArray());
		return $response;
	}

	public static function upload($video, $obj_id, $tmp_id, $add_automatically, $notification) {
		global $DIC;
		$ilUser = $DIC['ilUser'];
		$response = xvmpRequest::uploadMedium($video);
		$medium = $response->getResponseArray()['medium'];

		if ($add_automatically) {
			xvmpSelectedMedia::addVideo($medium['mid'], $obj_id, false);
		}

		$uploaded_media = new xvmpUploadedMedia();
		$uploaded_media->setMid($medium['mid']);
		$uploaded_media->setNotification($notification);
		$uploaded_media->setUserId($ilUser->getId());
		$uploaded_media->setTmpId($tmp_id);
		$uploaded_media->create();

		return $medium;
	}

	public static function deleteObject($mid) {
		xvmpRequest::deleteMedium($mid);
		xvmpSelectedMedia::deleteVideo($mid);
		if ($uploaded_media = xvmpUploadedMedia::find($mid)) {
			$uploaded_media->delete();
		}
		xvmpCacheFactory::getInstance()->delete(self::class . '-' . $mid);
	}

	/**
	 * some attributes have to be formatted to fill the form correctly
	 */
	protected static function formatResponse($response) {
		$response['duration_formatted'] = sprintf('%02d:%02d', ($response['duration']/60%60), $response['duration']%60);
		$response['description'] = strip_tags($response['description']);

		if (is_array($response['mediapermissions']['rid'])) {
			$response['mediapermissions'] = $response['mediapermissions']['rid'];
		}
//		foreach ($response['mediapermissions'])

		foreach (array(array('categories', 'category', 'cid'), array('tags', 'tag', 'tid')) as $labels) {
			$result = array();
			if (isset($response[$labels[0]][$labels[1]][$labels[2]])) {
				$response[$labels[0]][$labels[1]] = array($response[$labels[0]][$labels[1]] );
			}
			foreach ($response[$labels[0]][$labels[1]] as $item) {
				$result[$item[$labels[2]]] = $item['name'];
			}
			$response[$labels[0]] = $labels[0] == 'tags' ? implode(', ', $result) : $result;
		}
		return $response;
	}

	public static function cache($identifier, $object, $ttl = NULL) {
		parent::cache($identifier, $object, ($ttl ? $ttl : xvmpConf::getConfig(xvmpConf::F_CACHE_TTL_VIDEOS)));
	}

	/**
	 * @var int
	 */
	protected $mid;
	/**
	 * @var int
	 */
	protected $uid;
	/**
	 * @var String
	 */
	protected $username;
	/**
	 * @var String
	 */
	protected $mediakey;
	/**
	 * @var array
	 */
	protected $mediapermissions;
	/**
	 * @var String
	 */
	protected $mediatype;
	/**
	 * @var String
	 */
	protected $mediasubtype;
	/**
	 * @var String
	 */
	protected $published;
	/**
	 * @var String
	 */
	protected $status;
	/**
	 * @var bool
	 */
	protected $featured;
	/**
	 * @var String
	 */
	protected $culture;
	/**
	 * @var array
	 */
	protected $properties;
	/**
	 * @var String
	 */
	protected $title;
	/**
	 * @var String
	 */
	protected $description;
	/**
	 * @var int
	 */
	protected $duration;
	/**
	 * @var String
	 */
	protected $duration_formatted;
	/**
	 * @var String
	 */
	protected $thumbnail;
	/**
	 * @var String
	 */
	protected $embed_code;
	/**
	 * @var array
	 */
	protected $medium;
	/**
	 * @var String
	 */
	protected $source;
	/**
	 * @var String
	 */
	protected $meta_title;
	/**
	 * @var String
	 */
	protected $meta_description;
	/**
	 * @var String
	 */
	protected $meta_keywords;
	/**
	 * @var String
	 */
	protected $meta_author;
	/**
	 * @var String
	 */
	protected $meta_copyright;
	/**
	 * @var int
	 */
	protected $sum_rating;
	/**
	 * @var int
	 */
	protected $count_views;
	/**
	 * @var int
	 */
	protected $count_rating;
	/**
	 * @var int
	 */
	protected $count_favorites;
	/**
	 * @var int
	 */
	protected $count_comments;
	/**
	 * @var int
	 */
	protected $count_flags;
	/**
	 * @var String
	 */
	protected $created_at;
	/**
	 * @var String
	 */
	protected $updated_at;
	/**
	 * @var array
	 */
	protected $categories;
	/**
	 * @var array
	 */
	protected $tags;
	/**
	 * @var String
	 */
	protected $custom_author;


	/**
	 * @return array
	 */
	public function getMediapermissions() {
		return $this->mediapermissions;
	}


	/**
	 * @param array $mediapermissions
	 */
	public function setMediapermissions($mediapermissions) {
		$this->mediapermissions = $mediapermissions;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->getMid();
	}


	/**
	 * @param int $id
	 */
	public function setId($id) {
		return $this->setMid($id);
	}

	/**
	 * @return int
	 */
	public function getMid() {
		return $this->mid;
	}


	/**
	 * @param int $mid
	 */
	public function setMid($mid) {
		$this->mid = $mid;
	}


	/**
	 * @return int
	 */
	public function getUid() {
		return $this->uid;
	}


	/**
	 * @param int $uid
	 */
	public function setUid($uid) {
		$this->uid = $uid;
	}


	/**
	 * @return String
	 */
	public function getUsername() {
		return $this->username;
	}


	/**
	 * @param String $username
	 */
	public function setUsername($username) {
		$this->username = $username;
	}


	/**
	 * @return String
	 */
	public function getMediakey() {
		return $this->mediakey;
	}


	/**
	 * @param String $mediakey
	 */
	public function setMediakey($mediakey) {
		$this->mediakey = $mediakey;
	}


	/**
	 * @return String
	 */
	public function getMediatype() {
		return $this->mediatype;
	}


	/**
	 * @param String $mediatype
	 */
	public function setMediatype($mediatype) {
		$this->mediatype = $mediatype;
	}


	/**
	 * @return String
	 */
	public function getMediasubtype() {
		return $this->mediasubtype;
	}


	/**
	 * @param String $mediasubtype
	 */
	public function setMediasubtype($mediasubtype) {
		$this->mediasubtype = $mediasubtype;
	}


	/**
	 * @return String
	 */
	public function getPublished() {
		return $this->published;
	}


	/**
	 * @return mixed
	 */
	public function getPublishedId() {
		return self::$published_id_mapping[$this->published];
	}

	/**
	 * @param String $published
	 */
	public function setPublished($published) {
		$this->published = $published;
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
	 * @return bool
	 */
	public function isFeatured() {
		return $this->featured;
	}


	/**
	 * @param bool $featured
	 */
	public function setFeatured($featured) {
		$this->featured = $featured;
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
	 * @return array
	 */
	public function getProperties() {
		return $this->properties;
	}


	/**
	 * @param array $properties
	 */
	public function setProperties($properties) {
		$this->properties = $properties;
	}


	/**
	 * @return String
	 */
	public function getTitle() {
		return $this->title;
	}


	/**
	 * @param String $title
	 */
	public function setTitle($title) {
		$this->title = $title;
	}


	/**
	 * @return String
	 */
	public function getDescription($max_length = 0) {
		if ($max_length && strlen($this->description) > $max_length) {
			return substr($this->description, 0, $max_length) . '...';
		}
		return $this->description;
	}


	/**
	 * @param String $description
	 */
	public function setDescription($description) {
		$this->description = $description;
	}


	/**
	 * @return int
	 */
	public function getDuration() {
		return $this->duration;
	}


	/**
	 * @return string
	 */
	public function getDurationFormatted() {
		return $this->duration_formatted;
	}


	/**
	 * @param String $duration_formatted
	 */
	public function setDurationFormatted($duration_formatted) {
		$this->duration_formatted = $duration_formatted;
	}


	/**
	 * @param int $duration
	 */
	public function setDuration($duration) {
		$this->duration = $duration;
	}


	/**
	 * @return String
	 */
	public function getThumbnail($width = 0, $height = 0) {
		if ($width && $height) {
			return $this->thumbnail . "&size={$width}x{$height}";
		}
		return $this->thumbnail;
	}


	/**
	 * @param String $thumbnail
	 */
	public function setThumbnail($thumbnail) {
		$this->thumbnail = $thumbnail;
	}


	/**
	 * @return String
	 */
	public function getEmbedCode() {
		$detect_mobile = new MobileDetect();
		if ($detect_mobile->isMobile()) {
			return str_replace('responsive=false', 'responsive=true', $this->embed_code);
		}
		return $this->embed_code;
	}


	/**
	 * @param String $embed_code
	 */
	public function setEmbedCode($embed_code) {
		$this->embed_code = $embed_code;
	}


	/**
	 * @return array
	 */
	public function getMedium() {
		return $this->medium;
	}


	/**
	 * @param array $medium
	 */
	public function setMedium($medium) {
		$this->medium = $medium;
	}


	/**
	 * @return String
	 */
	public function getSource() {
		return $this->source;
	}


	/**
	 * @param String $source
	 */
	public function setSource($source) {
		$this->source = $source;
	}


	/**
	 * @return String
	 */
	public function getMetaTitle() {
		return $this->meta_title;
	}


	/**
	 * @param String $meta_title
	 */
	public function setMetaTitle($meta_title) {
		$this->meta_title = $meta_title;
	}


	/**
	 * @return String
	 */
	public function getMetaDescription() {
		return $this->meta_description;
	}


	/**
	 * @param String $meta_description
	 */
	public function setMetaDescription($meta_description) {
		$this->meta_description = $meta_description;
	}


	/**
	 * @return String
	 */
	public function getMetaKeywords() {
		return $this->meta_keywords;
	}


	/**
	 * @param String $meta_keywords
	 */
	public function setMetaKeywords($meta_keywords) {
		$this->meta_keywords = $meta_keywords;
	}


	/**
	 * @return String
	 */
	public function getMetaAuthor() {
		return $this->meta_author;
	}


	/**
	 * @param String $meta_author
	 */
	public function setMetaAuthor($meta_author) {
		$this->meta_author = $meta_author;
	}


	/**
	 * @return String
	 */
	public function getMetaCopyright() {
		return $this->meta_copyright;
	}


	/**
	 * @param String $meta_copyright
	 */
	public function setMetaCopyright($meta_copyright) {
		$this->meta_copyright = $meta_copyright;
	}


	/**
	 * @return int
	 */
	public function getSumRating() {
		return $this->sum_rating;
	}


	/**
	 * @param int $sum_rating
	 */
	public function setSumRating($sum_rating) {
		$this->sum_rating = $sum_rating;
	}


	/**
	 * @return int
	 */
	public function getCountViews() {
		return $this->count_views;
	}


	/**
	 * @param int $count_views
	 */
	public function setCountViews($count_views) {
		$this->count_views = $count_views;
	}


	/**
	 * @return int
	 */
	public function getCountRating() {
		return $this->count_rating;
	}


	/**
	 * @param int $count_rating
	 */
	public function setCountRating($count_rating) {
		$this->count_rating = $count_rating;
	}


	/**
	 * @return int
	 */
	public function getCountFavorites() {
		return $this->count_favorites;
	}


	/**
	 * @param int $count_favorites
	 */
	public function setCountFavorites($count_favorites) {
		$this->count_favorites = $count_favorites;
	}


	/**
	 * @return int
	 */
	public function getCountComments() {
		return $this->count_comments;
	}


	/**
	 * @param int $count_comments
	 */
	public function setCountComments($count_comments) {
		$this->count_comments = $count_comments;
	}


	/**
	 * @return int
	 */
	public function getCountFlags() {
		return $this->count_flags;
	}


	/**
	 * @param int $count_flags
	 */
	public function setCountFlags($count_flags) {
		$this->count_flags = $count_flags;
	}


	/**
	 * @return String
	 */
	public function getCreatedAt($format = '') {
		if ($format) {
			$timestamp = strtotime($this->created_at);
			return date($format, $timestamp);
		}
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


	/**
	 * @return array
	 */
	public function getCategories() {
		return $this->categories;
	}


	/**
	 * @param array $categories
	 */
	public function setCategories($categories) {
		$this->categories = $categories;
	}


	/**
	 * @return array
	 */
	public function getTags() {
		return $this->tags;
	}


	/**
	 * @param array $tags
	 */
	public function setTags($tags) {
		$this->tags = $tags;
	}


	/**
	 * @return String
	 */
	public function getCustomAuthor() {
		return $this->custom_author;
	}


	/**
	 * @param String $custom_author
	 */
	public function setCustomAuthor($custom_author) {
		$this->custom_author = $custom_author;
	}
}