<?php

declare(strict_types=1);

namespace pocketmine\form\ChestAPI\type\graphic\network;

use pocketmine\form\ChestAPI\session\InvMenuInfo;
use pocketmine\form\ChestAPI\session\PlayerSession;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;

interface InvMenuGraphicNetworkTranslator{

	public function translate(PlayerSession $session, InvMenuInfo $current, ContainerOpenPacket $packet) : void;
}