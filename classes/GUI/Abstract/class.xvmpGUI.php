<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class xvmpGUI
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
abstract class xvmpGUI {

	const CMD_STANDARD = 'index';
	const CMD_CANCEL = 'cancel';
	const CMD_FLUSH_CACHE = 'flushCache';

	const TAB_ACTIVE = ''; // overwrite in subclass
	/**
	 * @var ilObjViMPGUI
	 */
	protected $parent_gui;

	public function __construct(ilObjViMPGUI $parent_gui) {
		global $tpl, $ilCtrl, $ilTabs, $ilToolbar, $ilUser, $lng;
		/**
		 * @var $ilCtrl    ilCtrl
		 * @var $ilTabs    ilTabsGUI
		 * @var $tpl       ilTemplate
		 * @var $ilToolbar ilToolbarGUI
		 */
		$this->tpl = $tpl;
		$this->tabs = $ilTabs;
		$this->ctrl = $ilCtrl;
		$this->toolbar = $ilToolbar;
		$this->user = $ilUser;
		$this->pl = ilViMPPlugin::getInstance();
		$this->lng = $lng;
		$this->parent_gui = $parent_gui;
	}


	public function executeCommand() {
		if (!$this->ctrl->isAsynch()) {
			$this->tabs->activateTab(static::TAB_ACTIVE);
		}

		$nextClass = $this->ctrl->getNextClass();
		switch ($nextClass) {
			default:
				$cmd = $this->ctrl->getCmd(self::CMD_STANDARD);
				$this->performCommand($cmd);
				break;
		}
	}


	public function addFlushCacheButton () {
		$button = ilLinkButton::getInstance();
		$button->setUrl($this->ctrl->getLinkTarget($this,self::CMD_FLUSH_CACHE));
		$button->setCaption($this->pl->txt('flush_cache'), false);
		$this->toolbar->addButtonInstance($button);
	}

	/**
	 *
	 */
	public function flushCache() {
		xvmpCacheFactory::getInstance()->flush();
		$this->ctrl->redirect($this, self::CMD_STANDARD);
	}

	/**
	 * @param $cmd
	 */
	protected function performCommand($cmd) {
		$this->{$cmd}();
	}


	abstract protected function index();


	/**
	 *
	 */
	protected function cancel() {
		$this->ctrl->redirect($this, self::CMD_STANDARD);
	}


	/**
	 * @return ilObjViMP
	 */
	public function getObject() {
		return $this->parent_gui->object;
	}

	/**
	 * @return int
	 */
	public function getObjId() {
		return $this->parent_gui->obj_id;
	}

}