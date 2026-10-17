<?php

namespace XF;

/**
 * @extends \ArrayObject<string, mixed>
 */
#[\AllowDynamicProperties]
class Options extends \ArrayObject
{
	/**
	 * @param array<string, mixed> $array
	 */
	public function __construct(array $array = [])
	{
		parent::__construct($array, \ArrayObject::ARRAY_AS_PROPS);
	}

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	#[\ReturnTypeWillChange]
	public function &offsetGet($key)
	{
		try
		{
			$value = parent::offsetGet($key);
		}
		catch (\ErrorException $e)
		{
			if (\XF::$debugMode)
			{
				throw $e;
			}

			$value = null;
			\XF::logException($e);
		}

		return $value;
	}
}
