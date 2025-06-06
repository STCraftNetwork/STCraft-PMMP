<?php

declare(strict_types=1);

namespace pocketmine\form\ChestAPI\type\util\builder;

use pocketmine\form\ChestAPI\type\InvMenuType;

interface InvMenuTypeBuilder{

	public function build() : InvMenuType;
}