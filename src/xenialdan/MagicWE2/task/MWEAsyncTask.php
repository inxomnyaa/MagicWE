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

    public function onProgressUpdate(Server $server, $progress)
    {
        [$percentage, $title] = $progress;
        $session = API::getSessions()[$this->sessionUUID];
        if ($session instanceof UserSession) $session->getBossBar()->setPercentage($percentage / 100)->setTitle(str_replace("%", "%%%%", $title));
    }
}