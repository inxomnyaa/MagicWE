<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2\task\action;

use Generator;
use pocketmine\math\Vector3;
use xenialdan\MagicWE2\clipboard\SingleClipboard;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\selection\Selection;

abstract class ClipboardAction
{
    /** @var string */
    public $prefix = "";
    /** @var string */
    public $completionString = '{%name} succeed, took {%took}, {%changed} entries out of {%total} changed.';
    /** @var bool */
    public $addClipboard = false;
    /** @var null|Vector3 */
    public $clipboardVector = null;

    /**
     * @param string $sessionUUID
     * @param Selection $selection
     * @param null|int $changed
     * @param SingleClipboard $newSingleClipboard
     * @param string[] $messages
     * @return Generator|Progress[]
     */
    public abstract function execute(string $sessionUUID, Selection $selection, ?int &$changed, SingleClipboard &$newSingleClipboard, array &$messages = []): Generator;

    public static abstract function getName(): string;

    /**
     * @param Vector3|null $clipboardVector
     */
    public function setClipboardVector(?Vector3 $clipboardVector): void
    {
        if ($clipboardVector instanceof Vector3) $clipboardVector = $clipboardVector->asVector3()->floor();
        $this->clipboardVector = $clipboardVector;
    }
}