<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class xvmpOwnVideosGUI
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 *
 * @ilCtrl_isCalledBy xvmpOwnVideosGUI: ilObjViMPGUI
 */
class xvmpOwnVideosGUI extends xvmpVideosGUI {

	const SUBTAB_ACTIVE = xvmpVideosGUI::SUBTAB_OWN;

	const TABLE_CLASS = 'xvmpOwnVideosTableGUI';

	const CMD_EDIT_VIDEO = 'editVideo';
	const CMD_CHANGE_OWNER = 'changeOwner';
	const CMD_CONFIRMED_CHANGE_OWNER = 'confirmedChangeOwner';
	const CMD_UPDATE_VIDEO = 'updateVideo';
	const CMD_DELETE_VIDEO = 'deleteVideo';
	const CMD_UPLOAD_VIDEO_FORM = 'uploadVideoForm';
	const CMD_CREATE = 'createVideo';
	const CMD_CONFIRMED_DELETE_VIDEO = 'confirmedDeleteVideo';
	const CMD_UPLOAD_CHUNKS = 'uploadChunks';


	/**
	 *
	 */
	public function executeCommand() {
//		echo '<iframe src="http://localhost/media/embed?key=5350eb8bd4d60f6b668764022113f25b&width=350&height=200&autoplay=false&autolightsoff=false&loop=false&chapters=false&related=false&responsive=false" width="350" height="200" frameborder="0" allowfullscreen="allowfullscreen" allowtransparency="true" scrolling="no"></iframe>';exit;
		if (!ilObjViMPAccess::hasWriteAccess() && !ilObjViMPAccess::hasUploadPermission()) {
			ilUtil::sendFailure($this->pl->txt('access_denied'), true);
			$this->ctrl->redirect($this->parent_gui, ilObjViMPGUI::CMD_SHOW_CONTENT);
		}

		parent::executeCommand();
	}


	protected function performCommand($cmd) {
		switch ($cmd) {
			case self::CMD_EDIT_VIDEO:
			case self::CMD_CHANGE_OWNER:
			case self::CMD_UPDATE_VIDEO:
			case self::CMD_DELETE_VIDEO:
			case self::CMD_CONFIRMED_CHANGE_OWNER:
			case self::CMD_CONFIRMED_DELETE_VIDEO:
				$mid = max($_GET['mid'], $_POST['mid']);
				$medium = xvmpMedium::find($mid);
				// check if current user is owner of this video
				if (!$mid || ($medium->getMediatype() != 'video') || ($medium->getUid() != xvmpUser::getVimpUser($this->user)->getUid())) {
					ilUtil::sendFailure($this->pl->txt('access_denied'), true);
					$this->ctrl->redirect($this->parent_gui, ilObjViMPGUI::CMD_SHOW_CONTENT);
				}
				break;
		}
		if ($cmd != self::CMD_UPLOAD_CHUNKS) {
			/**
			 * this will find (and cache) or create a vimp user,
			 * or throw an exception if no vimp user is found and no vimp user can be created.
			 */
			xvmpUser::getOrCreateVimpUser($this->user);
		}
		parent::performCommand($cmd);
	}


	/**
	 *
	 */
	protected function index() {
		$class_name = static::TABLE_CLASS;
		/** @var xvmpTableGUI $table_gui */
		$table_gui = new $class_name($this, self::CMD_STANDARD);
		$this->tpl->setContent($table_gui->getHTML() . $this->getModalPlayer()->getHTML());
	}


	/**
	 *
	 */
	public function editVideo() {
		$mid = $_GET['mid'];
		$xvmpEditVideoFormGUI = new xvmpEditVideoFormGUI($this, $mid);
		$xvmpEditVideoFormGUI->fillForm();
		$this->tpl->setContent($xvmpEditVideoFormGUI->getHTML());
	}


	/**
	 *
	 */
	public function changeOwner() {
		$mid = $_GET['mid'];
		$uid = $_GET['uid'];
		$username = $_GET['username'];
		if ($uid) {
			$ilConfirmationGUI = new ilConfirmationGUI();
			$ilConfirmationGUI->setFormAction($this->ctrl->getFormAction($this));
			$ilConfirmationGUI->setHeaderText($this->pl->txt('msg_warning_change_owner'));
			$ilConfirmationGUI->addItem('mid', $mid, sprintf(
				$this->pl->txt('confirmation_new_owner'),
				xvmpMedium::find($mid)->getTitle(),
				$username
			));
			$ilConfirmationGUI->addHiddenItem('uid', $uid);
			$ilConfirmationGUI->addHiddenItem('username', $username);
			$ilConfirmationGUI->setConfirm($this->lng->txt('confirm'), self::CMD_CONFIRMED_CHANGE_OWNER);
			$ilConfirmationGUI->setCancel($this->lng->txt('cancel'), self::CMD_STANDARD);
			$this->tpl->setContent($ilConfirmationGUI->getHTML());
		} else {
			$xvmpChangeOwnerFormGUI = new xvmpChangeOwnerFormGUI($this, $mid);
			$this->tpl->setContent($xvmpChangeOwnerFormGUI->getHTML());
		}
	}


	/**
	 *
	 */
	public function confirmedChangeOwner() {
		$mid = $_POST['mid'];
		$uid = $_POST['uid'];
		$username = $_POST['username'];

		$medium = xvmpMedium::getObjectAsArray($mid);
		if ($medium['uid'] !== xvmpUser::getVimpUser($this->user)->getUid()) {
			ilUtil::sendFailure($this->pl->txt('permission_denied'), true);
			$this->ctrl->redirect($this, self::CMD_STANDARD);
		}

		$response = xvmpRequest::editMedium($mid, array('uid' => $uid))->getResponseBody();
		if ($response) {
			ilUtil::sendSuccess($this->pl->txt('form_saved'), true);
			$medium['uid'];
			xvmpCacheFactory::getInstance()->delete(xvmpMedium::class . '-' . $mid);
			xvmpMedium::cache(xvmpMedium::class . '-' . $mid, $medium);
			xvmpEventLog::logEvent(xvmpEventLog::ACTION_CHANGE_OWNER, $this->getObjId(), array(
				'owner' => $username,
				'mid' => $mid,
				'title' => $medium['title']
			));
		} else {
			ilUtil::sendFailure($this->pl->txt('failure'));
		}

		$this->ctrl->redirect($this, self::CMD_STANDARD);
	}

	/**
	 *
	 */
	public function updateVideo() {
		$xvmpEditVideoFormGUI = new xvmpEditVideoFormGUI($this, $_POST['mid']);
		$xvmpEditVideoFormGUI->setValuesByPost();
		if ($xvmpEditVideoFormGUI->saveForm()) {
			ilUtil::sendSuccess($this->pl->txt('form_saved'), true);
			$this->ctrl->redirect($this, self::CMD_EDIT_VIDEO);
		}
		ilUtil::sendFailure($this->pl->txt('msg_incomplete'));
		$this->tpl->setContent($xvmpEditVideoFormGUI->getHTML());
	}

	/**
	 *
	 */
	public function uploadVideoForm() {
		$xvmpEditVideoFormGUI = new xvmpUploadVideoFormGUI($this);
		$this->tpl->setContent($xvmpEditVideoFormGUI->getHTML());
	}


	/**
	 *
	 */
	public function createVideo() {
		$xvmpEditVideoFormGUI = new xvmpUploadVideoFormGUI($this);
		$xvmpEditVideoFormGUI->setValuesByPost();
		if ($xvmpEditVideoFormGUI->uploadVideo()) {
			ilUtil::sendSuccess($this->pl->txt('video_uploaded'), true);
			$this->ctrl->redirect($this, self::CMD_STANDARD);
		}

		ilUtil::sendFailure($this->pl->txt('form_incomplete'));
		$xvmpEditVideoFormGUI->setValuesByPost();
		$this->tpl->setContent($xvmpEditVideoFormGUI->getHTML());
	}


	/**
	 *
	 */
	public function deleteVideo() {
		$mid = $_GET['mid'];
		$video = xvmpMedium::find($mid);
		$confirmation_gui = new ilConfirmationGUI();
		$confirmation_gui->setFormAction($this->ctrl->getFormAction($this));
		$confirmation_gui->setHeaderText($this->pl->txt('confirm_delete_text'));
		$confirmation_gui->addItem('mid', $mid, $video->getTitle());
		$confirmation_gui->setConfirm($this->lng->txt('delete'),self::CMD_CONFIRMED_DELETE_VIDEO);
		$confirmation_gui->setCancel($this->lng->txt('cancel'), self::CMD_STANDARD);
		$this->tpl->setContent($confirmation_gui->getHTML());
	}


	/**
	 *
	 */
	public function confirmedDeleteVideo() {
		$mid = $_POST['mid'];

		// fetch the video for logging purposes
		$video = xvmpMedium::getObjectAsArray($mid);

		xvmpMedium::deleteObject($mid);

		xvmpEventLog::logEvent(xvmpEventLog::ACTION_DELETE, $this->getObjId(), $video);

		ilUtil::sendSuccess($this->pl->txt('video_deleted'), true);
		$this->ctrl->redirect($this, self::CMD_STANDARD);
	}


	/**
	 *
	 */
	protected function uploadChunks() {
		$xoctPlupload = new xoctPlupload();
		$tmp_id = $_GET['tmp_id'];

		$dir = ILIAS_ABSOLUTE_PATH  . ltrim(ilUtil::getWebspaceDir(), '.') . '/vimp/' . $tmp_id;
		if (!is_dir($dir)) {
			ilUtil::makeDir($dir);
		}

		$xoctPlupload->setTargetDir($dir);
		$xoctPlupload->handleUpload();
	}

}