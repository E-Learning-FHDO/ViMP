<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\DI\Container;
use ILIAS\UI\Implementation\Component\Signal;

/**
 * Class xvmpGUI
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
abstract class xvmpGUI {

	const CMD_STANDARD = 'index';
	const CMD_CANCEL = 'cancel';
	const CMD_FLUSH_CACHE = 'flushCache';
	const CMD_FILL_MODAL = 'fillModalPlayer';

	const TAB_ACTIVE = ''; // overwrite in subclass
    const CMD_DOWNLOAD_MEDIUM = 'downloadMedium';
    /**
	 * @var ilObjViMPGUI
	 */
	protected $parent_gui;
	/**
	 * @var ilTemplate
	 */
	protected $tpl;
	/**
	 * @var ilTabsGUI
	 */
	protected $tabs;
	/**
	 * @var ilCtrl
	 */
	protected $ctrl;
	/**
	 * @var ilToolbarGUI|mixed
	 */
	protected $toolbar;
	/**
	 * @var ilObjUser
	 */
	protected $user;
	/**
	 * @var ilViMPPlugin
	 */
	protected $pl;
	/**
	 * @var mixed
	 */
	protected $lng;
    /**
     * @var Container
     */
	protected $dic;
    /**
     * @var xvmpPlayModalRenderer
     */
	protected $modal_renderer;


	/**
	 * xvmpGUI constructor.
	 *
	 * @param ilObjViMPGUI $parent_gui
	 */
	public function __construct(ilObjViMPGUI $parent_gui) {
		global $DIC;
		$tpl = $DIC['tpl'];
		$ilCtrl = $DIC['ilCtrl'];
		$ilTabs = $DIC['ilTabs'];
		$ilToolbar = $DIC['ilToolbar'];
		$ilUser = $DIC['ilUser'];
		$lng = $DIC['lng'];
		$this->dic = $DIC;
		$this->tpl = $tpl;
		$this->tabs = $ilTabs;
		$this->ctrl = $ilCtrl;
		$this->toolbar = $ilToolbar;
		$this->user = $ilUser;
		$this->pl = ilViMPPlugin::getInstance();
		$this->lng = $lng;
		$this->parent_gui = $parent_gui;
		$this->modal_renderer = new xvmpPlayModalRenderer($DIC);
	}

    /**
     * @return ilModalGUI
     */
    protected function getAccessDeniedModal() : ilModalGUI
    {
        $modal = ilModalGUI::getInstance();
        $modal->setId('xvmp_modal_player');
        $modal->setType(ilModalGUI::TYPE_LARGE);
        if (xvmp::is54()) {
            $modal->setBody($this->dic->ui()->renderer()->render($this->dic->ui()->factory()->messageBox()->failure($this->pl->txt('access_denied'))));
        } else {
            $modal->setBody($this->dic->ui()->mainTemplate()->getMessageHTML($this->pl->txt('access_denied'),
                "failure"));
        }
        return $modal;
    }

    /**
     * @param xvmpMedium $video
     * @return xvmpPlayModalDTO
     * @throws xvmpException
     */
    protected function buildPlayModalDTO(xvmpMedium $video) : xvmpPlayModalDTO
    {
        $video_infos = [];
        if ($video->getStatus() !== 'legal') {
            $msg = xvmpConf::getConfig(xvmpConf::F_EMBED_PLAYER) ? $this->pl->txt('info_transcoding_full')
                : $this->pl->txt('info_transcoding_possible_full');
            $video_infos[] = (new xvmpVideoInfo($msg))->withStyle('color:red');
        }
        $video_infos[] = new xvmpVideoInfo($video->getDurationFormatted(), $this->pl->txt(xvmpMedium::F_DURATION));
        $video_infos[] = new xvmpVideoInfo($video->getCreatedAt('d.m.Y, H:i'), $this->pl->txt(xvmpMedium::F_CREATED_AT));

        foreach (xvmpConf::getConfig(xvmpConf::F_FORM_FIELDS) as $field) {
            if ($value = $video->getField($field[xvmpConf::F_FORM_FIELD_ID])) {
                $video_infos[] = new xvmpVideoInfo($value, $field[xvmpConf::F_FORM_FIELD_TITLE]);
            }
        }

        $video_infos[] = (new xvmpVideoInfo(nl2br($video->getDescription(), false), $this->pl->txt(xvmpMedium::F_DESCRIPTION)))
                ->withEllipsis(true);

        $playModalDto = (new xvmpPlayModalDTO($this->getVideoPlayer($video, $this->getObjId())))
            ->withVideoInfos($video_infos);

        if (!is_null($this->getObject())) {
            $link = $this->getPermLinkHTML($video);
            $playModalDto = $playModalDto->withPermLinkHtml($link);
        }

        if ($video->isDownloadAllowed()) {
            $this->dic->ctrl()->setParameter($this, 'mid', $video->getMid());
            $playModalDto = $playModalDto->withButtons([
                $this->dic->ui()->factory()->button()->standard(
                    $this->pl->txt('btn_download'),
                    $this->dic->ctrl()->getLinkTarget($this, self::CMD_DOWNLOAD_MEDIUM)
                )
            ]);
        }

        return $playModalDto;
    }

    /**
     * @param xvmpMedium $video
     * @param bool       $async
     * @return string
     */
    public function getPermLinkHTML(xvmpMedium $video, bool $async = true) : string
    {
        $link_tpl = ilLink::_getStaticLink(
            $this->parent_gui->ref_id,
            $this->parent_gui->getType(),
            true,
            '_' . $video->getMid() . '_TIME_'
        );
        $link_tpl = "<input type='text' id='xvmp_direct_link_tpl' value='{$link_tpl}' hidden>";

        $items = [
            $this->dic->ui()->factory()->button()->shy($this->pl->txt('btn_copy_link'), '')->withOnLoadCode(function (
                $id
            ) {
                return "document.getElementById('{$id}').addEventListener('click', VimpContent.copyDirectLink);";
            })
        ];
        if (!xvmpConf::getConfig(xvmpConf::F_EMBED_PLAYER)) {
            $items[] = $this->dic->ui()->factory()->button()->shy($this->pl->txt('btn_copy_link_w_time'),
                '')->withOnLoadCode(function ($id) {
                return "document.getElementById('{$id}').addEventListener('click', VimpContent.copyDirectLinkWithTime);";
            });
        }
        $dropdown = $this->dic->ui()->factory()->dropdown()->standard($items)->withLabel($this->pl->txt('direct_link_dropdown'));
        return $link_tpl . ($async ? $this->dic->ui()->renderer()->renderAsync($dropdown) : $this->dic->ui()->renderer()->render($dropdown));
    }

    /**
    * @param     $video
    * @param int $obj_id
    * @return xvmpVideoPlayer
    * @throws xvmpException
    */
    protected function getVideoPlayer($video, int $obj_id) : xvmpVideoPlayer
    {
        return (new xvmpVideoPlayer($video, xvmp::isUseEmbeddedPlayer($obj_id, $video)));
    }

    /**
	 *
	 */
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

	/**
	 * @param $cmd
	 */
	protected function performCommand($cmd) {
		switch ($cmd) {
			case self::CMD_FILL_MODAL:
				$mid = $_GET['mid'];
				$medium = xvmpMedium::find($mid);
				ilObjViMPAccess::checkAction(ilObjViMPAccess::ACTION_PLAY_VIDEO, $this, $medium);
				break;
		}

		$this->{$cmd}();
	}


	/**
	 *
	 */
	public function addFlushCacheButton () {
		$button = ilLinkButton::getInstance();
		$button->setUrl($this->ctrl->getLinkTarget($this,self::CMD_FLUSH_CACHE));
		$button->setCaption($this->pl->txt('flush_video_cache'), false);
		$button->setId('xvmp_flush_video_cache');
		$this->toolbar->addButtonInstance($button);

		ilTooltipGUI::addTooltip('xvmp_flush_video_cache', $this->pl->txt('flush_video_cache_tooltip'));
	}

	/**
	 *
	 */
	public function flushCache() {
//		xvmpCacheFactory::getInstance()->flush();
		foreach (xvmpSelectedMedia::getSelected($this->getObjId()) as $selected) {
			xvmpCacheFactory::getInstance()->delete(xvmpMedium::class . '-' . $selected->getMid());
		}
		$this->ctrl->redirect($this, self::CMD_STANDARD);
	}


	/**
	 * @return mixed
	 */
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


	/**
	 * called by ilObjViMPAccess
	 */
	public function accessDenied() {
		ilUtil::sendFailure($this->pl->txt('access_denied'), true);
		$this->ctrl->redirect($this->parent_gui, ilObjViMPGUI::CMD_SHOW_CONTENT);
	}

		/**
	 * @return ilModalGUI
	 */
	public static function getModalPlayer() {
		global $tpl;
		$tpl->addCss(ilViMPPlugin::getInstance()->getDirectory() . '/templates/default/modal.css');
		$modal = ilModalGUI::getInstance();
		$modal->setId('xvmp_modal_player');
		$modal->setType(ilModalGUI::TYPE_LARGE);
        $modal->setBody('<section><div id="xvmp_video_container"></div></section>');
		return $modal;
	}


    /**
     * @param $video_mid
     *
     * @return ilModalGUI
     * @throws ilTemplateException
     * @throws xvmpException
     */
    public function getFilledModalPlayer($video_mid) : ilModalGUI
    {
        $selected_medium = xvmpSelectedMedia::where(array('obj_id' => $this->getObjId(), 'mid' => $video_mid));
        if (!ilObjViMPAccess::hasWriteAccess()) {
            $selected_medium = $selected_medium->where(['visible' => 1]);
        }
        /** @var xvmpSelectedMedia $selected_medium */
        $selected_medium = $selected_medium->first();
        if (!$selected_medium) {
            return $this->getAccessDeniedModal();
        }
        $this->dic->ui()->mainTemplate()->addCss(ilViMPPlugin::getInstance()->getDirectory() . '/templates/default/modal.css');
        $modal_content = $this->fillModalPlayer($video_mid, false);
        /** @var xvmpSettings $settings */
        $settings = xvmpSettings::find($this->getObjId());
        if ($settings->getLpActive()) {
            $this->dic->ui()->mainTemplate()->addOnLoadCode('VimpObserver.init(' . $video_mid . ', ' . json_encode($modal_content->time_ranges) . ');');
        }
        $modal = ilModalGUI::getInstance();
        $modal->setId('xvmp_modal_player');
        $modal->setHeading($modal_content->video_title);
        $modal->setType(ilModalGUI::TYPE_LARGE);
        $modal->setBody('<section><div id="xvmp_video_container">' .
            $modal_content->html .
            '</div></section>');
        return $modal;
    }


    /**
     * @param null $play_video_id
     * @param bool $async
     * @return stdClass
     * @throws ilTemplateException
     * @throws xvmpException
     */
	public function fillModalPlayer($play_video_id = null, bool $async = true) {
		$mid = $play_video_id ?? $_GET['mid'];
		$video = xvmpMedium::find($mid);
        $playModalDto = $this->buildPlayModalDTO($video);

        $response = new stdClass();
		$response->html = $this->modal_renderer->render($playModalDto, $async);
		$response->video_title = $video->getTitle();
		/** @var xvmpUserProgress $progress */
		$progress = xvmpUserProgress::where(array(xvmpUserProgress::F_USR_ID => $this->user->getId(), xvmpMedium::F_MID => $mid))->first();
		if ($progress) {
			$response->time_ranges = json_decode($progress->getRanges());
		} else {
			$response->time_ranges = array();
		}
		if ($async == true) {
            echo json_encode($response);
            exit;
        } else {
		    return $response;
        }
	}

	protected function downloadMedium()
    {
        $mid = filter_input(INPUT_GET, 'mid', FILTER_VALIDATE_INT);
        $video = xvmpMedium::find($mid);
        ilObjViMPAccess::checkAction(ilObjViMPAccess::ACTION_DOWNLOAD_VIDEO, $this, $video);
        xvmp::deliverMedium($video);
    }


	/**
	 * ajax
	 */
	public function updateProgress() {
		$mid = $_POST[xvmpMedium::F_MID];
		$ranges = $_POST[xvmpUserProgress::F_RANGES];
		xvmpUserProgress::storeProgress($this->dic->user()->getid(), $mid, $ranges);
		echo "ok";
		exit;
	}

}
