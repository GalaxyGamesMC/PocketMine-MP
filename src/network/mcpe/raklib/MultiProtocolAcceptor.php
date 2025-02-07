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

namespace pocketmine\network\mcpe\raklib;

use raklib\server\ProtocolAcceptor;
use function in_array;

class MultiProtocolAcceptor implements ProtocolAcceptor{
	/**
	 * @param int[] $protocolVersions
	 */
	public function __construct(private int $primaryVersion, private array $protocolVersions){}

	public function accepts(int $protocolVersion) : bool{
		return in_array($protocolVersion, $this->protocolVersions, true);
	}

	public function getPrimaryVersion() : int{
		return $this->primaryVersion;
	}
}
