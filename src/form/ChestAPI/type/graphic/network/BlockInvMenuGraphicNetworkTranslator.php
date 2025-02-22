<?php

declare(strict_types=1);

namespace pocketmine\form\ChestAPI\type\graphic\network;

use InvalidArgumentException;
use pocketmine\form\ChestAPI\session\InvMenuInfo;
use pocketmine\form\ChestAPI\session\PlayerSession;
use pocketmine\form\ChestAPI\type\graphic\PositionedInvMenuGraphic;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;

final class BlockInvMenuGraphicNetworkTranslator implements InvMenuGraphicNetworkTranslator{

	public static function instance() : self{
		static $instance = null;
		return $instance ??= new self();
	}

	private function __construct(){
	}

	public function translate(PlayerSession $session, InvMenuInfo $current, ContainerOpenPacket $packet) : void{
		$graphic = $current->graphic;
		if(!($graphic instanceof PositionedInvMenuGraphic)){
			throw new InvalidArgumentException("Expected " . PositionedInvMenuGraphic::class . ", got " . get_class($graphic));
		}

		$pos = $graphic->getPosition();
		$packet->blockPosition = new BlockPosition((int) $pos->x, (int) $pos->y, (int) $pos->z);
	}
}