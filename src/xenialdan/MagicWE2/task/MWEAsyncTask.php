<?php

namespace xenialdan\MagicWE2\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use xenialdan\BossBarAPI\API as BossBarAPI;
use xenialdan\MagicWE2\API;

abstract class MWEAsyncTask extends AsyncTask
{
    public $playerUUID;

    public function onProgressUpdate(Server $server, $progress)
    {
        [$percentage, $title] = $progress;
        $player = $server->getPlayerByUUID(unserialize($this->playerUUID));
        if (is_null($player)) return;
        $session = API::getSession($player);
        if (is_null($session)) return;
        BossBarAPI::setPercentage(intval($percentage), $session->getBossBarId(), [$player]);
        BossBarAPI::setTitle(str_replace("%", "%%%%", $title), $session->getBossBarId(), [$player]);
    }
}