<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class xvmpVideoPlayer
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class xvmpVideoPlayer {

	/**
	 * @var ilViMPPlugin
	 */
	protected $pl;
	/**
	 * @var ilTemplate
	 */
	protected $tpl;
	/**
	 * @var ilCtrl
	 */
	protected $ctrl;
	/**
	 * @var xvmpMedium
	 */
	protected $video;
	/**
	 * @var bool
	 */
	protected $embed;
	/**
	 * @var array
	 */
	protected $options = array(
		"controls" => true,
		"autoplay" => false,
		"preload" => "auto",
		"fluid" => true,
		"playbackRates" => [ 0.5, 1.0, 1.25, 1.5 ]
	);
	protected static $count = 1;


	/**
	 * xvmpVideoPlayer constructor.
	 *
	 * @param      $video
	 * @param bool $embed
	 *
	 * @throws xvmpException
	 */
	public function __construct($video, $embed = false) {
		global $DIC;
		$tpl = $GLOBALS['tpl']; // necessary because of LM Bug
		$this->ctrl = $DIC['ilCtrl'];
		$this->tpl = $tpl;
		$this->pl = ilViMPPlugin::getInstance();
		if (is_int($video)) {
			$video = xvmpMedium::find($video);
		}
		$this->video = $video;
		$this->embed = $embed;
	}


	/**
	 * @param $load_observer
	 */
	public static function loadVideoJSAndCSS($load_observer) {
		$tpl = $GLOBALS['tpl']; // necessary because of LM Bug
		if ($load_observer) {
			$tpl->addJavaScript(ilViMPPlugin::getInstance()->getDirectory() . '/js/xvmp_observer.js');
		}
		$tpl->addCss(ilViMPPlugin::getInstance()->getDirectory() . '/templates/default/video.css');

		$tpl->addJavaScript(ilViMPPlugin::getInstance()->getDirectory() . '/node_modules/video.js/dist/video.min.js');
		$tpl->addCss(ilViMPPlugin::getInstance()->getDirectory() . '/node_modules/video.js/dist/video-js.min.css');
		$tpl->addJavaScript(ilViMPPlugin::getInstance()->getDirectory()
			. '/node_modules/videojs-contrib-quality-levels/dist/videojs-contrib-quality-levels.min.js');
		$tpl->addJavaScript(ilViMPPlugin::getInstance()->getDirectory()
			. '/node_modules/videojs-hls-quality-selector/dist/videojs-hls-quality-selector.min.js');
	}


	/**
	 * @return string
	 * @throws ilTemplateException
	 */
	public function getHTML() {
		if ($this->embed) {
			return $this->video->getEmbedCode($this->options['width'], $this->options['height']);
		}

		$template = $this->pl->getTemplate('default/tpl.video.html');

		$medium = $this->video->getMedium();
		$isABRStream = false;

		if (is_array($medium)) {
			if (xvmpRequest::config('adaptive_bitrate_streaming')->getResponseArray()['config']['value']) {
				$isABRStream = true;
				$medium = html_entity_decode(end($medium));
				$medium = str_replace('mp4', 'smil', $medium);
			} else {
				$medium = $medium[0];
			}
		}
		$id = ilUtil::randomhash();

		if (xvmp::ViMPVersionGreaterEquals('4.0.5')) {
		    $pathinfo['extension'] = 'application/x-mpegURL';
//			$pathinfo['extension'] = 'video/' . pathinfo($medium)['extension'];     // dev
			$medium = urldecode($medium);
		} else {
			$pathinfo['extension'] = 'video/' . pathinfo($medium)['extension'];

			$sources = xvmp::ViMPVersionEquals('4.0.4') ? xvmpRequest::getVideoSources($this->video->getMediakey(), $_SERVER['HTTP_HOST'])
				->getResponseArray() : xvmpRequest::getVideoSources($this->video->getMediakey(), $_SERVER['HTTP_HOST'])
				->getResponseArray()['sources'];

			if (!empty($sources)) {
				$medium = xvmp::ViMPVersionEquals('4.0.4') ? base64_decode($sources[0][1]) : $sources[0][1];
				$pathinfo['extension'] = 'application/x-mpegURL';
			}
		}

		$subtitles = $this->video->getField(xvmpMedium::F_SUBTITLES);

		if ($subtitles) {
			foreach ($subtitles as $lang => $url) {
				$template->setCurrentBlock('captions');
				$template->setVariable('CAPTION_LANG', $lang);
				$template->setVariable('CAPTION_SOURCE', 'data:text/vtt;base64,' . base64_encode(xvmpRequest::getCaptions($url)->getResponseBody()));
				$template->parseCurrentBlock();
			}
		}

		$chapters = json_decode(xvmpRequest::getChapters($this->video->getMediakey())->getResponseBody());

		if ($chapters->chapters) {
			$output = "WEBVTT \n\n";
			foreach ($chapters->chapters as $chapter) {
				$output .= gmdate("H:i:s", $chapter->time) . ".000 --> " . gmdate("H:i:s", $chapter->time) . ".000\n" . $chapter->title . "\n\n";
			}

			$template->setCurrentBlock('chapters');
			$template->setVariable('CHAPTER_SOURCE', 'data:text/vtt;base64,' . base64_encode($output));
			$template->parseCurrentBlock();
		}

		$template->setVariable('ID', $id);
		$template->setVariable('SOURCE', $medium);
		// TODO: uncomment to proxy video url
		//		$this->ctrl->setParameterByClass(xvmpContentGUI::class, 'mid', $this->video->getId());
		//		$template->setVariable('SOURCE', $this->ctrl->getLinkTargetByClass(array(ilObjViMPGUI::class, xvmpContentGUI::class), xvmpContentGUI::CMD_DELIVER_VIDEO));
		$template->setVariable('THUMBNAIL', $this->video->getThumbnail());
		$template->setVariable('TYPE', $pathinfo['extension']);

		if (!isset($this->options['width']) && !isset($this->options['width'])) {
			$template->setVariable('CSS_CLASS', 'vjs-4-3');
			$this->setOption('fluid', true);
		} else {
			$this->setOption('fluid', false);
		}

		$options = json_encode($this->options);
		$videojs_script = "var player = videojs('xvmp_video_{$id}', {$options}, function () { $('#xvmp_video_{$id}').on('contextmenu', function(e) { e.preventDefault(); });});";

		if ($isABRStream) {
			$videojs_script .= "player.hlsQualitySelector();";
		}

		$template->setCurrentBlock('script');
		$template->setVariable('SCRIPT', $videojs_script);
		$template->parseCurrentBlock();

		return $template->get();
	}


	/**
	 * @param $option
	 * @param $value
	 */
	public function setOption($option, $value) {
		if ($value === null) {
			unset($this->options[$option]);
		} else {
			$this->options[$option] = $value;
		}
	}
}
