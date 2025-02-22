<?php

declare(strict_types=1);

namespace pocketmine\form\ChestAPI\session\network\handler;

use Closure;
use pocketmine\form\ChestAPI\session\network\NetworkStackLatencyEntry;

interface PlayerNetworkHandler{

	public function createNetworkStackLatencyEntry(Closure $then) : NetworkStackLatencyEntry;
}