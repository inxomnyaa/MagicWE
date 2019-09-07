<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\Progress;
use xenialdan\MagicWE2\session\UserSession;

abstract class MWEAsyncTask extends AsyncTask
{
    /** @var string */
    public $sessionUUID;
    /** @var float */
    public $start;

    public function onProgressUpdate(Server $server, $progress)
    {
        if (!$progress instanceof Progress) {//TODO Temp fix until all async tasks are modified
            $progress = new Progress($progress[0] / 100, $progress[1]);
        }
        $session = API::getSessions()[$this->sessionUUID];
        /** @var Progress $progress */
        if ($session instanceof UserSession) $session->getBossBar()->setPercentage($progress->progress)->setSubTitle(str_replace("%", "%%%%", $progress->string . " | " . floor($progress->progress * 100) . "%"));
        else $session->sendMessage($progress->string . " | " . floor($progress->progress * 100) . "%");//TODO remove, debug
    }

    public function generateTookString(): string
    {
        return date("i:s:", microtime(true) - $this->start) . strval(round(microtime(true) - $this->start, 1, PHP_ROUND_HALF_DOWN));
    }
}