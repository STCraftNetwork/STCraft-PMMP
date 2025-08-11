<?php

declare(strict_types=1);

namespace pocketmine\world\format\io;

use pocketmine\utils\Binary;
use pocketmine\utils\BinaryStream;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;

use function count;
use function strlen;

/**
 * Ultra-fast chunk serializer for transmitting chunks between threads.
 * Designed for speed & minimal memory usage.
 */
final class FastChunkSerializer {
    private const FLAG_POPULATED = 1 << 1;

    private function __construct() {
        // Static utility class
    }

    /**
     * Efficiently serializes a paletted block array.
     */
    private static function serializePalettedArray(BinaryStream $stream, PalettedBlockArray $array): void {
        $bitsPerBlock = $array->getBitsPerBlock();
        $stream->putByte($bitsPerBlock);

        // Directly append the word array
        $wordArray = $array->getWordArray();
        $stream->put($wordArray);

        // Serialize palette as raw binary (avoid pack/unpack overhead)
        $palette = $array->getPalette();
        $paletteCount = count($palette);
        $stream->putInt($paletteCount * 4); // Each entry is 4 bytes
        if ($paletteCount > 0) {
            $binaryPalette = '';
            foreach ($palette as $val) {
                $binaryPalette .= Binary::writeInt($val);
            }
            $stream->put($binaryPalette);
        }
    }

    /**
     * Serializes chunk terrain (faster version).
     */
    public static function serializeTerrain(Chunk $chunk): string {
        $stream = new BinaryStream();

        $flags = $chunk->isPopulated() ? self::FLAG_POPULATED : 0;
        $stream->putByte($flags);

        $subChunks = $chunk->getSubChunks();
        $count = count($subChunks);
        $stream->putByte($count);

        foreach ($subChunks as $y => $subChunk) {
            $stream->putByte($y);
            $stream->putInt($subChunk->getEmptyBlockId());

            $layers = $subChunk->getBlockLayers();
            $layerCount = count($layers);
            $stream->putByte($layerCount);

            // Serialize layers directly
            foreach ($layers as $layer) {
                self::serializePalettedArray($stream, $layer);
            }

            // Serialize biome array
            self::serializePalettedArray($stream, $subChunk->getBiomeArray());
        }

        return $stream->getBuffer();
    }

    /**
     * Fast deserialization of a paletted block array.
     */
    private static function deserializePalettedArray(BinaryStream $stream): PalettedBlockArray {
        $bitsPerBlock = $stream->getByte();

        $wordArraySize = PalettedBlockArray::getExpectedWordArraySize($bitsPerBlock);
        $words = $wordArraySize > 0 ? $stream->get($wordArraySize) : '';

        $paletteSize = $stream->getInt();
        $palette = [];
        if ($paletteSize > 0) {
            $paletteData = $stream->get($paletteSize);
            // Each palette entry is 4 bytes
            $entries = $paletteSize >> 2; // divide by 4
            for ($i = 0; $i < $entries; ++$i) {
                $palette[] = Binary::readInt(substr($paletteData, $i * 4, 4));
            }
        }

        return PalettedBlockArray::fromData($bitsPerBlock, $words, $palette);
    }

    /**
     * Deserializes terrain data into a Chunk.
     */
    public static function deserializeTerrain(string $data): Chunk {
        $stream = new BinaryStream($data);

        $flags = $stream->getByte();
        $terrainPopulated = ($flags & self::FLAG_POPULATED) !== 0;

        $subChunkCount = $stream->getByte();
        $subChunks = [];

        for ($i = 0; $i < $subChunkCount; ++$i) {
            $y = Binary::signByte($stream->getByte());
            $airBlockId = $stream->getInt();

            $layerCount = $stream->getByte();
            $layers = [];
            for ($j = 0; $j < $layerCount; ++$j) {
                $layers[] = self::deserializePalettedArray($stream);
            }

            $biomeArray = self::deserializePalettedArray($stream);

            $subChunks[$y] = new SubChunk($airBlockId, $layers, $biomeArray);
        }

        return new Chunk($subChunks, $terrainPopulated);
    }
}
