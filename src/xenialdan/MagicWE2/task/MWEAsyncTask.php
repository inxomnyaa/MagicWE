<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\session\UserSession;

abstract class MWEAsyncTask extends AsyncTask
{
    /** @var string */
    public $sessionUUID;
    /** @var float */
    public $start;

    public function onProgressUpdate(Server $server, $progress)
    {
        [$percentage, $title] = $progress;
        $session = API::getSessions()[$this->sessionUUID];
        if ($session instanceof UserSession) $session->getBossBar()->setPercentage($percentage / 100)->setTitle(str_replace("%", "%%%%", $title));
    }

    public function generateTookString(): string
    {
        return date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN));
    }
}