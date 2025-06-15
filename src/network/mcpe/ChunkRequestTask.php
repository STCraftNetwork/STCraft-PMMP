<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe;

use pocketmine\network\mcpe\compression\CompressBatchPromise;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\types\ChunkPosition;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\serializer\ChunkSerializer;
use pocketmine\scheduler\AsyncTask;
use pocketmine\thread\NonThreadSafeValue;
use pocketmine\utils\BinaryStream;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use function chr;

class ChunkRequestTask extends AsyncTask{
	private const TLS_KEY_PROMISE = "promise";

	protected string $encodedData;
	/** @phpstan-var NonThreadSafeValue<Compressor> */
	protected NonThreadSafeValue $compressor;

	public function __construct(int $chunkX, int $chunkZ, int $dimensionId, Chunk $chunk, CompressBatchPromise $promise, Compressor $compressor){
		$this->compressor = new NonThreadSafeValue($compressor);

		$tiles = ChunkSerializer::serializeTiles($chunk);
		$blockTranslator = TypeConverter::getInstance()->getBlockTranslator();
		$subCount = ChunkSerializer::getSubChunkCount($chunk, $dimensionId);
		$payload = ChunkSerializer::serializeFullChunk($chunk, $dimensionId, $blockTranslator, $tiles);

		$stream = new BinaryStream();
		PacketBatch::encodePackets($stream, [
			LevelChunkPacket::create(
				new ChunkPosition($chunkX, $chunkZ),
				$dimensionId,
				$subCount,
				false,
				null,
				$payload
			)
		]);

		$this->encodedData = $stream->getBuffer();
		$this->storeLocal(self::TLS_KEY_PROMISE, $promise);
	}

	public function onRun() : void{
		$compressor = $this->compressor->deserialize();
		$compressed = $compressor->compress($this->encodedData);
		$this->setResult(chr($compressor->getNetworkId()) . $compressed);
	}

	public function onCompletion() : void{	
		$promise = $this->fetchLocal(self::TLS_KEY_PROMISE);
		$promise->resolve($this->getResult());
	}
}
