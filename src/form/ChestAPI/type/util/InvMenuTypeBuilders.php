<?php

declare(strict_types=1);

namespace pocketmine\form\ChestAPI\type\util;

use pocketmine\form\ChestAPI\type\util\builder\ActorFixedInvMenuTypeBuilder;
use pocketmine\form\ChestAPI\type\util\builder\BlockActorFixedInvMenuTypeBuilder;
use pocketmine\form\ChestAPI\type\util\builder\BlockFixedInvMenuTypeBuilder;
use pocketmine\form\ChestAPI\type\util\builder\DoublePairableBlockActorFixedInvMenuTypeBuilder;

final class InvMenuTypeBuilders{

	public static function ACTOR_FIXED() : ActorFixedInvMenuTypeBuilder{
		return new ActorFixedInvMenuTypeBuilder();
	}

	public static function BLOCK_ACTOR_FIXED() : BlockActorFixedInvMenuTypeBuilder{
		return new BlockActorFixedInvMenuTypeBuilder();
	}

	public static function BLOCK_FIXED() : BlockFixedInvMenuTypeBuilder{
		return new BlockFixedInvMenuTypeBuilder();
	}

	public static function DOUBLE_PAIRABLE_BLOCK_ACTOR_FIXED() : DoublePairableBlockActorFixedInvMenuTypeBuilder{
		return new DoublePairableBlockActorFixedInvMenuTypeBuilder();
	}
}