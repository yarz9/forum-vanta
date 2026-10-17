<?php

namespace League\Flysystem;

class CorruptedPathDetected extends \LogicException implements FilesystemException
{
	/**
	 * @param string $path
	 * @return CorruptedPathDetected
	 */
	public static function forPath($path)
	{
		return new CorruptedPathDetected("Corrupted path detected: " . $path);
	}
}
