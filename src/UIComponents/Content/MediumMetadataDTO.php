<?php

namespace srag\Plugins\ViMP\Content;

use DateTime;
use srag\Plugins\ViMP\UIComponents\PlayerModal\MediumAttribute;

/**
 * @author Theodor Truffer <tt@studer-raimann.ch>
 */
class MediumMetadataDTO
{
    /**
     * @var int
     */
    private $mid;
    /**
     * @var string
     */
    private $title;
    /**
     * @var string
     */
    private $description;
    /**
     * @var MediumAttribute[]
     */
    private $medium_infos = [];
    /**
     * @var bool
     */
    private $transcoding;
    /**
     * @var string
     */
    private $thumbnail_url;
    /**
     * @var DateTime|null
     */
    private $availability_start;
    /**
     * @var DateTime|null
     */
    private $availability_end;

    /**
     * VideoMetadataDTO constructor.
     * @param int               $mid
     * @param string            $title
     * @param string            $description
     * @param MediumAttribute[] $video_infos
     * @param bool              $transcoding
     * @param string            $thumbnail_url
     * @param DateTime|null     $availability_start
     * @param DateTime|null     $availability_end
     */
    public function __construct(
        int $mid,
        string $title,
        string $description,
        array $video_infos,
        bool $transcoding,
        string $thumbnail_url,
        DateTime $availability_start = null,
        DateTime $availability_end = null
    ) {
        $this->mid = $mid;
        $this->title = $title;
        $this->description = $description;
        $this->medium_infos = $video_infos;
        $this->transcoding = $transcoding;
        $this->thumbnail_url = $thumbnail_url;
        $this->availability_start = $availability_start;
        $this->availability_end = $availability_end;
    }

    /**
     * @return int
     */
    public function getMid() : int
    {
        return $this->mid;
    }

    /**
     * @return string
     */
    public function getThumbnailUrl() : string
    {
        return $this->thumbnail_url;
    }

    /**
     * @return DateTime|null
     */
    public function getAvailabilityStart() /*: ?DateTime*/
    {
        return $this->availability_start;
    }

    /**
     * @return DateTime|null
     */
    public function getAvailabilityEnd() /*: ?DateTime*/
    {
        return $this->availability_end;
    }

    /**
     * @return string
     */
    public function getTitle() : string
    {
        return $this->title;
    }

    /**
     * @param int $max_length
     * @return string
     */
    public function getDescription(int $max_length = 0) : string
    {
        if ($max_length && mb_strlen($this->description) > $max_length) {
            return mb_substr($this->description, 0, $max_length) . '...';
        }
        return $this->description;    }

    /**
     * @return MediumAttribute[]
     */
    public function getMediumInfos() : array
    {
        return $this->medium_infos;
    }

    /**
     * @return bool
     */
    public function isTranscoding() : bool
    {
        return $this->transcoding;
    }
}
