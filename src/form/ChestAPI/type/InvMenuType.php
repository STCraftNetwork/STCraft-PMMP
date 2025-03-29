<?php

declare(strict_types=1);

namespace pocketmine\form\ChestAPI\type;

use pocketmine\form\ChestAPI\InvMenu;
use pocketmine\form\ChestAPI\type\graphic\InvMenuGraphic;
use pocketmine\inventory\Inventory;
use pocketmine\player\Player;

interface InvMenuType{

	public function createGraphic(InvMenu $menu, Player $player) : ?InvMenuGraphic;

	public function createInventory() : Inventory;
}