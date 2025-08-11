<?php

declare(strict_types=1);

namespace pocketmine\world\format;

use pocketmine\block\Block;
use pocketmine\block\tile\Tile;
use pocketmine\data\bedrock\BiomeIds;

/**
 * High-performance MCPE-style chunk implementation with subchunks and XZY ordering.
 */
class Chunk {
    // Dirty flags
    public const DIRTY_FLAG_BLOCKS = 1 << 0;
    public const DIRTY_FLAG_BIOMES = 1 << 3;

    // Dirty flag mask
    public const DIRTY_FLAGS_ALL = self::DIRTY_FLAG_BLOCKS | self::DIRTY_FLAG_BIOMES;
    public const DIRTY_FLAGS_NONE = 0;

    // Subchunk indices
    public const MIN_SUBCHUNK_INDEX = -4;
    public const MAX_SUBCHUNK_INDEX = 19;
    public const MAX_SUBCHUNKS = self::MAX_SUBCHUNK_INDEX - self::MIN_SUBCHUNK_INDEX + 1;

    // Coordinate constants
    public const COORD_BIT_SIZE = SubChunk::COORD_BIT_SIZE;
    public const COORD_MASK = SubChunk::COORD_MASK;

    /** @var SubChunk[] */
    private array $subChunks;

    /** @var Tile[] */
    private array $tiles = [];

    private int $terrainDirtyFlags = self::DIRTY_FLAGS_ALL;
    private bool $tilesDirty = false;

    private ?bool $lightPopulated = null;
    private bool $terrainPopulated;

    private ?HeightArray $heightMap = null;

    /**
     * @param SubChunk[] $subChunks Indexed by subchunk Y from MIN_SUBCHUNK_INDEX to MAX_SUBCHUNK_INDEX
     */
    public function __construct(array $subChunks, bool $terrainPopulated) {
        // Initialize subchunks as a flat array for fast access
        $this->subChunks = [];
        for ($y = self::MIN_SUBCHUNK_INDEX; $y <= self::MAX_SUBCHUNK_INDEX; ++$y) {
            $this->subChunks[$y] = $subChunks[$y] ?? new SubChunk(Block::EMPTY_STATE_ID, [], new PalettedBlockArray(BiomeIds::OCEAN));
        }
        $this->terrainPopulated = $terrainPopulated;
    }

    public function getHeight(): int {
        return self::MAX_SUBCHUNKS;
    }

    public function getBlockStateId(int $x, int $y, int $z): int {
        return $this->subChunks[$y >> self::COORD_BIT_SIZE]->getBlockStateId($x, $y & self::COORD_MASK, $z);
    }

    public function setBlockStateId(int $x, int $y, int $z, int $block): void {
        $this->subChunks[$y >> self::COORD_BIT_SIZE]->setBlockStateId($x, $y & self::COORD_MASK, $z, $block);
        $this->terrainDirtyFlags |= self::DIRTY_FLAG_BLOCKS;
    }

    public function getHighestBlockAt(int $x, int $z): ?int {
        for ($y = self::MAX_SUBCHUNK_INDEX; $y >= self::MIN_SUBCHUNK_INDEX; --$y) {
            $val = $this->subChunks[$y]->getHighestBlockAt($x, $z);
            if ($val !== null) {
                return $val | ($y << self::COORD_BIT_SIZE);
            }
        }
        return null;
    }

    public function getHeightMap(int $x, int $z): int {
        return $this->getOrCreateHeightMap()->get($x, $z);
    }

    public function setHeightMap(int $x, int $z, int $val): void {
        $this->getOrCreateHeightMap()->set($x, $z, $val);
        // HeightMap changes not marked as terrainDirty
    }

    public function getBiomeId(int $x, int $y, int $z): int {
        return $this->subChunks[$y >> self::COORD_BIT_SIZE]->getBiomeArray()->get($x, $y, $z);
    }

    public function setBiomeId(int $x, int $y, int $z, int $id): void {
        $this->subChunks[$y >> self::COORD_BIT_SIZE]->getBiomeArray()->set($x, $y, $z, $id);
        $this->terrainDirtyFlags |= self::DIRTY_FLAG_BIOMES;
    }

    public function addTile(Tile $tile): void {
        $pos = $tile->getPosition();
        $hash = ($pos->y << (2 * self::COORD_BIT_SIZE)) | (($pos->z & self::COORD_MASK) << self::COORD_BIT_SIZE) | ($pos->x & self::COORD_MASK);
        $this->tiles[$hash] = $tile;
        $this->tilesDirty = true;
    }

    public function removeTile(Tile $tile): void {
        $pos = $tile->getPosition();
        $hash = ($pos->y << (2 * self::COORD_BIT_SIZE)) | (($pos->z & self::COORD_MASK) << self::COORD_BIT_SIZE) | ($pos->x & self::COORD_MASK);
        unset($this->tiles[$hash]);
        $this->tilesDirty = true;
    }

    public function hasTileChanges(): bool {
        return $this->tilesDirty;
    }

    public function clearTileDirty(): void {
        $this->tilesDirty = false;
    }

    public function isTerrainDirty(): bool {
        return $this->terrainDirtyFlags !== self::DIRTY_FLAGS_NONE;
    }

    public function clearTerrainDirty(): void {
        $this->terrainDirtyFlags = self::DIRTY_FLAGS_NONE;
    }

    private function getOrCreateHeightMap(): HeightArray {
        if ($this->heightMap === null) {
            $this->heightMap = HeightArray::fill((self::MAX_SUBCHUNK_INDEX + 1) * SubChunk::EDGE_LENGTH);
        }
        return $this->heightMap;
    }

    public function __clone() {
        $new = [];
        foreach ($this->subChunks as $y => $sub) {
            $new[$y] = clone $sub;
        }
        $this->subChunks = $new;
        $this->heightMap = clone $this->getOrCreateHeightMap();
    }
}
