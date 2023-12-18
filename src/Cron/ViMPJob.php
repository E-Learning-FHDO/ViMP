<?php

namespace srag\Plugins\ViMP\Cron;

use ilViMPPlugin;
use ilCronJob;
use ilCronJobResult;
use xvmpCron;

/**
 * Class ViMPJob
 *
 * @package srag\Plugins\ViMP\Cron
 *
 * @author  studer + raimann ag - Team Custom 1 <support-custom1@studer-raimann.ch>
 */
class ViMPJob extends ilCronJob
{
    const CRON_JOB_ID = ilViMPPlugin::XVMP;
    const PLUGIN_CLASS_NAME = ilViMPPlugin::class;
    private ilViMPPlugin $pl;

    /**
     * ViMPJob constructor
     */
    public function __construct()
    {
        $this->pl = ilViMPPlugin::getInstance();
    }


    public function getId() : string
    {
        return self::CRON_JOB_ID;
    }


    /**
     * @inheritDoc
     */
    public function hasAutoActivation() : bool
    {
        return true;
    }


    public function hasFlexibleSchedule() : bool
    {
        return true;
    }


    public function getDefaultScheduleType() : int
    {
        return self::SCHEDULE_TYPE_IN_MINUTES;
    }


    public function getDefaultScheduleValue(): ?int
    {
        return 1;
    }


    public function getTitle() : string
    {
        return ilViMPPlugin::PLUGIN_NAME . ": " . $this->pl->txt("cron_title");
    }


    public function getDescription() : string
    {
        return $this->pl->txt("cron_description");
    }


    public function run() : ilCronJobResult
    {
        $result = new ilCronJobResult();

        $srViMPCronjob = new xvmpCron();
        $srViMPCronjob->run();

        $result->setStatus(ilCronJobResult::STATUS_OK);
        return $result;
    }
}
