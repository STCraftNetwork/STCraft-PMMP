<?php
declare(strict_types=1);

namespace pocketmine\world\generator;

use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\SimpleChunkManager;
use pocketmine\world\World;
use function array_map;
use function igbinary_serialize;
use function igbinary_unserialize;

/**
 * @phpstan-type OnCompletion \Closure(Chunk $centerChunk, array<int, Chunk> $adjacentChunks) : void
 */
class PopulationTask extends AsyncTask{
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

	public function onRun() : void{
		$context = ThreadLocalGeneratorContext::fetch($this->worldId);
		if($context === null){
			throw new AssumptionFailedError("Generator context should have been initialized before any PopulationTask execution");
		}
		$generator = $context->getGenerator();
		$manager = new SimpleChunkManager($context->getWorldMinY(), $context->getWorldMaxY());

		$chunk = $this->chunk !== null ? FastChunkSerializer::deserializeTerrain($this->chunk) : null;

		/**
		 * @var string[] $serialChunks
		 * @phpstan-var array<int, string|null> $serialChunks
		 */
		$serialChunks = igbinary_unserialize($this->adjacentChunks);
		$chunks = array_map(
			function(?string $serialized) : ?Chunk{
				if($serialized === null){
					return null;
				}
				$chunk = FastChunkSerializer::deserializeTerrain($serialized);
				$chunk->clearTerrainDirtyFlags(); //this allows us to avoid sending existing chunks back to the main thread if they haven't changed during generation
				return $chunk;
			},
			$serialChunks
		);

		self::setOrGenerateChunk($manager, $generator, $this->chunkX, $this->chunkZ, $chunk);

		$resultChunks = []; //this is just to keep phpstan's type inference happy
		foreach($chunks as $relativeChunkHash => $c){
			World::getXZ($relativeChunkHash, $relativeX, $relativeZ);
			$resultChunks[$relativeChunkHash] = self::setOrGenerateChunk($manager, $generator, $this->chunkX + $relativeX, $this->chunkZ + $relativeZ, $c);
		}
		$chunks = $resultChunks;

		$generator->populateChunk($manager, $this->chunkX, $this->chunkZ);
		$chunk = $manager->getChunk($this->chunkX, $this->chunkZ);
		if($chunk === null){
			throw new AssumptionFailedError("We just generated this chunk, so it must exist");
		}
		$chunk->setPopulated();

		$this->chunk = FastChunkSerializer::serializeTerrain($chunk);

		$serialChunks = [];
		foreach($chunks as $relativeChunkHash => $c){
			$serialChunks[$relativeChunkHash] = $c->isTerrainDirty() ? FastChunkSerializer::serializeTerrain($c) : null;
		}
		$this->adjacentChunks = igbinary_serialize($serialChunks) ?? throw new AssumptionFailedError("igbinary_serialize() returned null");
	}

	private static function setOrGenerateChunk(SimpleChunkManager $manager, Generator $generator, int $chunkX, int $chunkZ, ?Chunk $chunk) : Chunk{
		$manager->setChunk($chunkX, $chunkZ, $chunk ?? new Chunk([], false));
		if($chunk === null){
			$generator->generateChunk($manager, $chunkX, $chunkZ);
			$chunk = $manager->getChunk($chunkX, $chunkZ);
			if($chunk === null){
				throw new AssumptionFailedError("We just set this chunk, so it must exist");
			}
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
