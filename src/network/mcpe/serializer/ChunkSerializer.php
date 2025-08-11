<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\serializer;

use pocketmine\block\tile\Spawnable;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\data\bedrock\LegacyBiomeIdToStringIdMap;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\convert\BlockTranslator;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\utils\Binary;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use function count;

final class ChunkSerializer {
    private function __construct() {}

    /**
     * Returns min/max subchunk index expected by the protocol.
     *
     * @phpstan-param DimensionIds::* $dimensionId
     * @phpstan-return array{int, int}
     */
    public static function getDimensionChunkBounds(int $dimensionId): array {
        return match ($dimensionId) {
            DimensionIds::OVERWORLD => [-4, 19],
            DimensionIds::NETHER => [0, 7],
            DimensionIds::THE_END => [0, 15],
            default => throw new \InvalidArgumentException("Unknown dimension ID $dimensionId"),
        };
    }

    /**
     * Get subchunk count for a chunk.
     *
     * @phpstan-param DimensionIds::* $dimensionId
     */
    public static function getSubChunkCount(Chunk $chunk, int $dimensionId): int {
        [$min, $max] = self::getDimensionChunkBounds($dimensionId);
        $count = $max - $min + 1;

        for ($y = $max; $y >= $min; --$y, --$count) {
            if (!$chunk->getSubChunk($y)->isEmptyFast()) {
                return $count;
            }
        }
        return 0;
    }

    /**
     * Serialize a full chunk into binary data.
     *
     * @phpstan-param DimensionIds::* $dimensionId
     */
    public static function serializeFullChunk(
        Chunk $chunk,
        int $dimensionId,
        BlockTranslator $blockTranslator,
        ?string $tiles = null
    ): string {
        $stream = PacketSerializer::encoder();
        $subChunkCount = self::getSubChunkCount($chunk, $dimensionId);
        if ($subChunkCount === 0) {
            return $tiles ?? self::serializeTiles($chunk);
        }

        [$min, $max] = self::getDimensionChunkBounds($dimensionId);
        for ($y = $min, $written = 0; $written < $subChunkCount; ++$y, ++$written) {
            self::serializeSubChunk($chunk->getSubChunk($y), $blockTranslator, $stream, false);
        }

        $biomeMap = LegacyBiomeIdToStringIdMap::getInstance();
        for ($y = $min; $y <= $max; ++$y) {
            self::serializeBiomePalette($chunk->getSubChunk($y)->getBiomeArray(), $biomeMap, $stream);
        }

        $stream->putByte(0); // border block array count
        $stream->put($tiles ?? self::serializeTiles($chunk));
        return $stream->getBuffer();
    }

    /**
     * Serialize a single subchunk (optimized).
     */
    public static function serializeSubChunk(
        SubChunk $subChunk,
        BlockTranslator $blockTranslator,
        PacketSerializer $stream,
        bool $persistentBlockStates
    ): void {
        $layers = $subChunk->getBlockLayers();
        $layerCount = count($layers);

        $stream->putByte(8);        // subchunk version
        $stream->putByte($layerCount);

        static $nbtSerializer = null;
        $nbtSerializer ??= new NetworkNbtSerializer();
        $blockStateDict = $blockTranslator->getBlockStateDictionary();

        foreach ($layers as $blocks) {
            $bits = $blocks->getBitsPerBlock();
            $stream->putByte(($bits << 1) | ($persistentBlockStates ? 0 : 1));
            $stream->put($blocks->getWordArray());

            $palette = $blocks->getPalette();
            $pCount = count($palette);

            if ($bits !== 0) {
                $stream->putUnsignedVarInt($pCount << 1); // Zigzag optimization
            }

            if ($persistentBlockStates) {
                foreach ($palette as $p) {
                    $state = $blockStateDict->generateDataFromStateId($blockTranslator->internalIdToNetworkId($p))
                        ?? $blockTranslator->getFallbackStateData();
                    $stream->put($nbtSerializer->write(new TreeRoot($state->toNbt())));
                }
            } else {
                foreach ($palette as $p) {
                    $stream->put(Binary::writeUnsignedVarInt($blockTranslator->internalIdToNetworkId($p) << 1));
                }
            }
        }
    }

    /**
     * Serialize biome data for a subchunk.
     */
    private static function serializeBiomePalette(
        PalettedBlockArray $biomePalette,
        LegacyBiomeIdToStringIdMap $biomeIdMap,
        PacketSerializer $stream
    ): void {
        $bits = $biomePalette->getBitsPerBlock();
        $stream->putByte(($bits << 1) | 1);
        $stream->put($biomePalette->getWordArray());

        $palette = $biomePalette->getPalette();
        if ($bits !== 0) {
            $stream->putUnsignedVarInt(count($palette) << 1);
        }

        $ocean = BiomeIds::OCEAN;
        foreach ($palette as $p) {
            if ($biomeIdMap->legacyToString($p) === null) {
                $p = $ocean;
            }
            $stream->put(Binary::writeUnsignedVarInt($p << 1));
        }
    }

    /**
     * Serialize tiles (block entities).
     */
    public static function serializeTiles(Chunk $chunk): string {
        $buffer = '';
        foreach ($chunk->getTiles() as $tile) {
            if ($tile instanceof Spawnable) {
                $buffer .= $tile->getSerializedSpawnCompound()->getEncodedNbt();
            }
        }
        return $buffer;
    }
}
