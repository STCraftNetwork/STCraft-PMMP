<?php

declare(strict_types=1);

namespace pocketmine\form\ChestAPI\type\graphic;

use pocketmine\math\Vector3;

interface PositionedInvMenuGraphic extends InvMenuGraphic{

	public function getPosition() : Vector3;
}