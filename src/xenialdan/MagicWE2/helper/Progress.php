<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\helper;

class Progress
{
    /** @var float Percentage */
    public $progress = 0.0;
    /** @var string */
    public $string = "";

    /**
     * Progress constructor.
     * @param float $progress
     * @param string $info
     */
    public function __construct(float $progress, string $info)
    {
        $this->progress = $progress;
        $this->string = $info;
    }

    public function __toString()
    {
        return "Progress: " . $this->progress . " String: " . $this->string;
    }
}
