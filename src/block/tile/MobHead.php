<?php

declare(strict_types=1);

namespace pocketmine\block\tile;

use pocketmine\block\utils\MobHeadType;
use pocketmine\data\bedrock\MobHeadTypeIdMap;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;

/**
 * @deprecated
 * @see \pocketmine\block\MobHead
 */
class MobHead extends Spawnable {

    private const TAG_SKULL_TYPE = "SkullType"; // TAG_Byte
    private const TAG_ROT = "Rot"; // TAG_Byte
    private const TAG_PLAYER_SKIN = "PlayerSkin"; // TAG_String

    private MobHeadType $mobHeadType = MobHeadType::SKELETON;
    private int $rotation = 0;
    private ?string $playerSkin = null; // Store player skin data

    public function readSaveData(CompoundTag $nbt): void {
        if (($skullTypeTag = $nbt->getTag(self::TAG_SKULL_TYPE)) instanceof ByteTag) {
            $mobHeadType = MobHeadTypeIdMap::getInstance()->fromId($skullTypeTag->getValue());
            if ($mobHeadType === null) {
                throw new SavedDataLoadingException("Invalid skull type tag value " . $skullTypeTag->getValue());
            }
            $this->mobHeadType = $mobHeadType;
        }

        $rotation = $nbt->getByte(self::TAG_ROT, 0);
        if ($rotation >= 0 && $rotation <= 15) {
            $this->rotation = $rotation;
        }

        // Read player skin data
        if (($playerSkinTag = $nbt->getTag(self::TAG_PLAYER_SKIN)) instanceof StringTag) {
            $this->playerSkin = $playerSkinTag->getValue();
        }
    }

    protected function writeSaveData(CompoundTag $nbt): void {
        $nbt->setByte(self::TAG_SKULL_TYPE, MobHeadTypeIdMap::getInstance()->toId($this->mobHeadType));
        $nbt->setByte(self::TAG_ROT, $this->rotation);

        // Write player skin data if available
        if ($this->playerSkin !== null) {
            $nbt->setString(self::TAG_PLAYER_SKIN, $this->playerSkin);
        }
    }

    public function setMobHeadType(MobHeadType $type): void {
        $this->mobHeadType = $type;
    }

    public function getMobHeadType(): MobHeadType {
        return $this->mobHeadType;
    }

    public function getRotation(): int {
        return $this->rotation;
    }

    public function setRotation(int $rotation): void {
        $this->rotation = $rotation;
    }

    public function getPlayerSkin(): ?string {
        return $this->playerSkin;
    }

    public function setPlayerSkin(?string $playerSkin): void {
        $this->playerSkin = $playerSkin;
    }

    protected function addAdditionalSpawnData(CompoundTag $nbt): void {
        $nbt->setByte(self::TAG_SKULL_TYPE, MobHeadTypeIdMap::getInstance()->toId($this->mobHeadType));
        $nbt->setByte(self::TAG_ROT, $this->rotation);

        // Add player skin data to spawn data
        if ($this->playerSkin !== null) {
            $nbt->setString(self::TAG_PLAYER_SKIN, $this->playerSkin);
        }
    }
}
