<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe;

use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializerContext;
use pocketmine\network\mcpe\serializer\ChunkSerializer;
use pocketmine\scheduler\AsyncTask;
use pocketmine\thread\NonThreadSafeValue;
use pocketmine\utils\Binary;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
/** @phpstan-ignore-next-line */
use function xxhash64;

class ChunkRequestTask extends AsyncTask{
	private const TLS_KEY_PROMISE = "promise";

	protected string $chunk;
	protected int $chunkX;
	protected int $chunkZ;
	/** @phpstan-var NonThreadSafeValue<Compressor> */
	protected NonThreadSafeValue $compressor;
	protected int $mappingProtocol;
	private string $tiles;

	public function __construct(int $chunkX, int $chunkZ, Chunk $chunk, TypeConverter $typeConverter, CachedChunkPromise $promise, Compressor $compressor){
		$this->compressor = new NonThreadSafeValue($compressor);
		$this->mappingProtocol = $typeConverter->getProtocolId();

		$this->chunk = FastChunkSerializer::serializeTerrain($chunk);
		$this->chunkX = $chunkX;
		$this->chunkZ = $chunkZ;
		$this->tiles = ChunkSerializer::serializeTiles($chunk, $typeConverter);

		$this->storeLocal(self::TLS_KEY_PROMISE, $promise);
	}

	public function onRun() : void{
		$chunk = FastChunkSerializer::deserializeTerrain($this->chunk);

		$cache = new CachedChunk();

		$converter = TypeConverter::getInstance($this->mappingProtocol);
		$encoderContext = new PacketSerializerContext($converter->getItemTypeDictionary(), $this->mappingProtocol);

		foreach(ChunkSerializer::serializeSubChunks($chunk, $converter->getBlockTranslator(), $encoderContext) as $subChunk){
			/** @phpstan-ignore-next-line */
			$cache->addSubChunk(Binary::readLong(xxhash64($subChunk)), $subChunk);
		}

		$encoder = PacketSerializer::encoder($encoderContext);
		$biomeEncoder = clone $encoder;
		ChunkSerializer::serializeBiomes($chunk, $biomeEncoder);
		/** @phpstan-ignore-next-line */
		$cache->setBiomes(Binary::readLong(xxhash64($chunkBuffer = $biomeEncoder->getBuffer())), $chunkBuffer);

		$chunkDataEncoder = clone $encoder;
		ChunkSerializer::serializeChunkData($chunk, $chunkDataEncoder, $converter, $this->tiles);

		$cache->compressPackets(
			$this->chunkX,
			$this->chunkZ,
			$chunkDataEncoder->getBuffer(),
			$this->compressor->deserialize(),
			$encoderContext,
		);

		$this->setResult($cache);
	}

	public function onCompletion() : void{
		/** @var CachedChunk $result */
		$result = $this->getResult();

		/** @var CachedChunkPromise $promise */
		$promise = $this->fetchLocal(self::TLS_KEY_PROMISE);
		$promise->resolve($result);
	}
}
