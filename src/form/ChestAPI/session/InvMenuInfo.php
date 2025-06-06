<?php

declare(strict_types=1);

namespace pocketmine\form\ChestAPI\session;

use pocketmine\form\ChestAPI\InvMenu;
use pocketmine\form\ChestAPI\type\graphic\InvMenuGraphic;

final class InvMenuInfo{

	public function __construct(
		readonly public InvMenu $menu,
		readonly public InvMenuGraphic $graphic,
		readonly public ?string $graphic_name
	){}
}