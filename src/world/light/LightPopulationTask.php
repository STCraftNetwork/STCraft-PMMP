<?php
declare(strict_types=1);

namespace pocketmine\world\light;

use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\scheduler\AsyncTask;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\format\LightArray;
use pocketmine\world\SimpleChunkManager;
use pocketmine\world\utils\SubChunkExplorer;
use pocketmine\world\World;

use function igbinary_serialize;
use function igbinary_unserialize;

class LightPopulationTask extends AsyncTask {
    private const TLS_KEY_ON_COMPLETION = "onCompletion";

    private string $chunkSerialized;
    private string $heightMapData;
    private string $skyLightData;
    private string $blockLightData;

    /**
     * @param \Closure(array<int, LightArray>, array<int, LightArray>, non-empty-list<int>) $onCompletion
     */
    public function __construct(Chunk $chunk, \Closure $onCompletion) {
        $this->chunkSerialized = FastChunkSerializer::serializeTerrain($chunk);
        $this->storeLocal(self::TLS_KEY_ON_COMPLETION, $onCompletion);
    }

    public function onRun(): void {
        $chunk = FastChunkSerializer::deserializeTerrain($this->chunkSerialized);
        $manager = new SimpleChunkManager(World::Y_MIN, World::Y_MAX);
        $manager->setChunk(0, 0, $chunk);

        $registry = RuntimeBlockStateRegistry::getInstance();
        $explorer = new SubChunkExplorer($manager);

        foreach ([
            function() use ($explorer, $registry): void {
                $update = new BlockLightUpdate($explorer, $registry->lightFilter, $registry->light);
                $update->recalculateChunk(0, 0);
                $update->execute();
            },
            function() use ($explorer, $registry): void {
                $update = new SkyLightUpdate($explorer, $registry->lightFilter, $registry->blocksDirectSkyLight);
                $update->recalculateChunk(0, 0);
                $update->execute();
            }
        ] as $perform) {
            $perform();
        }

        $chunk->setLightPopulated();

        $this->heightMapData = igbinary_serialize($chunk->getHeightMapArray());
        $this->skyLightData   = igbinary_serialize(array_map(fn($s) => $s->getBlockSkyLightArray(), $chunk->getSubChunks()));
        $this->blockLightData = igbinary_serialize(array_map(fn($s) => $s->getBlockLightArray(),     $chunk->getSubChunks()));
    }

    public function onCompletion(): void {
        $callback = $this->fetchLocal(self::TLS_KEY_ON_COMPLETION);
        $callback(
            igbinary_unserialize($this->blockLightData),
            igbinary_unserialize($this->skyLightData),
            igbinary_unserialize($this->heightMapData)
        );
    }
}
