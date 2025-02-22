<?php

declare(strict_types=1);

namespace pocketmine\form\ChestAPI\type;

use pocketmine\form\ChestAPI\inventory\InvMenuInventory;
use pocketmine\form\ChestAPI\InvMenu;
use pocketmine\form\ChestAPI\type\graphic\BlockActorInvMenuGraphic;
use pocketmine\form\ChestAPI\type\graphic\BlockInvMenuGraphic;
use pocketmine\form\ChestAPI\type\graphic\InvMenuGraphic;
use pocketmine\form\ChestAPI\type\graphic\MultiBlockInvMenuGraphic;
use pocketmine\form\ChestAPI\type\graphic\network\InvMenuGraphicNetworkTranslator;
use pocketmine\form\ChestAPI\type\util\InvMenuTypeHelper;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\Inventory;
use pocketmine\math\Facing;
use pocketmine\player\Player;
use function count;

final class BlockActorFixedInvMenuType implements FixedInvMenuType{

	public function __construct(
		readonly private Block $block,
		readonly private int $size,
		readonly private string $tile_id,
		readonly private ?InvMenuGraphicNetworkTranslator $network_translator = null,
		readonly private int $animation_duration = 0
	){}

	public function getSize() : int{
		return $this->size;
	}

	public function createGraphic(InvMenu $menu, Player $player) : ?InvMenuGraphic{
		$position = $player->getPosition();
		$origin = $position->addVector(InvMenuTypeHelper::getBehindPositionOffset($player))->floor();
		if(!InvMenuTypeHelper::isValidYCoordinate($origin->y)){
			return null;
		}

		$graphics = [new BlockActorInvMenuGraphic($this->block, $origin, BlockActorInvMenuGraphic::createTile($this->tile_id, $menu->getName()), $this->network_translator, $this->animation_duration)];
		foreach(InvMenuTypeHelper::findConnectedBlocks("Chest", $position->getWorld(), $origin, Facing::HORIZONTAL) as $side){
			$graphics[] = new BlockInvMenuGraphic(VanillaBlocks::BARRIER(), $side);
		}

		return count($graphics) > 1 ? new MultiBlockInvMenuGraphic($graphics) : $graphics[0];
	}

	public function createInventory() : Inventory{
		return new InvMenuInventory($this->size);
	}
}