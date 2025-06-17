<?php

declare(strict_types=1);

namespace pocketmine\world\format\io;

use pocketmine\utils\Binary;
use pocketmine\utils\BinaryStream;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;

use function pack;
use function unpack;

/**
 * Fast chunk serializer for transmitting chunks between threads.
 * NOT intended for permanent storage.
 */
final class FastChunkSerializer{
    private const FLAG_POPULATED = 1 << 1;

    private function __construct(){
        // Static utility class — do not instantiate
    }

    private static function serializePalettedArray(BinaryStream $stream, PalettedBlockArray $array): void{
        $bitsPerBlock = $array->getBitsPerBlock();
        $wordArray = $array->getWordArray();
        $palette = $array->getPalette();

        $stream->putByte($bitsPerBlock);
        $stream->put($wordArray);

        // Efficiently pack palette as binary
        $serialPalette = pack('L*', ...$palette);
        $stream->putInt(strlen($serialPalette));
        $stream->put($serialPalette);
    }

    /**
     * Serializes chunk terrain for thread transfer.
     */
    public static function serializeTerrain(Chunk $chunk): string{
        $stream = new BinaryStream();
        $flags = $chunk->isPopulated() ? self::FLAG_POPULATED : 0;
        $stream->putByte($flags);

        $subChunks = $chunk->getSubChunks();
        $count = \count($subChunks);
        $stream->putByte($count);

        foreach ($subChunks as $y => $subChunk) {
            $stream->putByte($y);
            $stream->putInt($subChunk->getEmptyBlockId());

            $layers = $subChunk->getBlockLayers();
            $layerCount = \count($layers);
            $stream->putByte($layerCount);

            foreach ($layers as $layer) {
                self::serializePalettedArray($stream, $layer);
            }

            self::serializePalettedArray($stream, $subChunk->getBiomeArray());
        }

        return $stream->getBuffer();
    }

    private static function deserializePalettedArray(BinaryStream $stream): PalettedBlockArray{
        $bitsPerBlock = $stream->getByte();
        $wordArraySize = PalettedBlockArray::getExpectedWordArraySize($bitsPerBlock);
        $words = $stream->get($wordArraySize);

        $paletteSize = $stream->getInt();
        $paletteData = $stream->get($paletteSize);

        /** @var int[] $palette */
        $palette = unpack('L*', $paletteData);

        return PalettedBlockArray::fromData($bitsPerBlock, $words, $palette);
    }

    /**
     * Deserializes terrain data into a chunk.
     */
    public static function deserializeTerrain(string $data): Chunk{
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
