<?php
declare(strict_types=1);

namespace pocketmine\world\generator;

use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\SimpleChunkManager;
use pocketmine\world\World;

use function igbinary_serialize;
use function igbinary_unserialize;

class PopulationTask extends AsyncTask {
    private const TLS_KEY_ON_COMPLETION = "onCompletion";

    private ?string $centerChunk;
    private string $adjacentChunksData;

    public function __construct(
        private int $worldId,
        private int $chunkX,
        private int $chunkZ,
        ?Chunk $chunk,
        array $adjacentChunks, // array<int, Chunk|null>
        \Closure $onCompletion  // fn(Chunk, array<int, Chunk>): void
    ) {
        $this->centerChunk = $chunk?->serializeTerrain();
        $this->adjacentChunksData = igbinary_serialize(array_map(
            fn(?Chunk $c) => $c?->serializeTerrain(),
            $adjacentChunks
        ));
        $this->storeLocal(self::TLS_KEY_ON_COMPLETION, $onCompletion);
    }

    public function onRun(): void {
        $context = ThreadLocalGeneratorContext::fetch($this->worldId)
            ?? throw new AssumptionFailedError("Generator context not initialized");

        $gen = $context->getGenerator();
        $mgr = new SimpleChunkManager($context->getWorldMinY(), $context->getWorldMaxY());

        $center = $this->centerChunk !== null ? FastChunkSerializer::deserializeTerrain($this->centerChunk) : null;
        $adjacent = igbinary_unserialize($this->adjacentChunksData);
        $adjChunks = [];

        foreach ($adjacent as $hash => $data) {
            $adjChunks[$hash] = $data !== null
                ? FastChunkSerializer::deserializeTerrain($data)->clearTerrainDirtyFlags()
                : null;
        }

        self::setOrGenerate($mgr, $gen, $this->chunkX, $this->chunkZ, $center);

        foreach ($adjChunks as $hash => $chunkObj) {
            [$dx, $dz] = World::getXZ($hash);
            $adjChunks[$hash] = self::setOrGenerate($mgr, $gen, $this->chunkX + $dx, $this->chunkZ + $dz, $chunkObj);
        }

        $gen->populateChunk($mgr, $this->chunkX, $this->chunkZ);

        $popChunk = $mgr->getChunk($this->chunkX, $this->chunkZ)
            ?? throw new AssumptionFailedError("Generated chunk missing");
        $popChunk->setPopulated();

        $this->centerChunk = FastChunkSerializer::serializeTerrain($popChunk);
        $this->adjacentChunksData = igbinary_serialize(array_map(
            fn(Chunk $c) => $c->isTerrainDirty() ? FastChunkSerializer::serializeTerrain($c) : null,
            $adjChunks
        ));
    }

    private static function setOrGenerate(SimpleChunkManager $mgr, $gen, int $x, int $z, ?Chunk $chunk): Chunk {
        $mgr->setChunk($x, $z, $chunk ?? new Chunk([], false));
        if ($chunk === null) {
            $gen->generateChunk($mgr, $x, $z);
            return $mgr->getChunk($x, $z)
                ?? throw new AssumptionFailedError("Generated chunk missing");
        }
        return $chunk;
    }

    public function onCompletion(): void {
        $callback = $this->fetchLocal(self::TLS_KEY_ON_COMPLETION);
        $center = FastChunkSerializer::deserializeTerrain($this->centerChunk);
        $adjData = igbinary_unserialize($this->adjacentChunksData);
        $readyAdj = [];

        foreach ($adjData as $hash => $data) {
            if ($data !== null) {
                $readyAdj[$hash] = FastChunkSerializer::deserializeTerrain($data);
            }
        }

        $callback($center, $readyAdj);
    }
}
