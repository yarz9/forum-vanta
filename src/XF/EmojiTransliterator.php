<?php

namespace XF;

use XF\Data\EmojiTranslit;

if (!class_exists(\Transliterator::class))
{
	throw new \LogicException(sprintf('You cannot use the "%s\EmojiTransliterator" class as the "intl" extension is not installed. See https://php.net/intl.', __NAMESPACE__));
}

class EmojiTransliterator extends \Transliterator
{
	protected $map;

	public static function create($id = 'emoji', $direction = self::FORWARD): ?\Transliterator
	{
		if ($direction !== self::FORWARD)
		{
			throw new \InvalidArgumentException('Reverse transliteration is not supported.');
		}

		static $newInstance;
		if ($newInstance === null)
		{
			$newInstance = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
		}
		$instance = $newInstance;

		$instance->map = $instance->buildEmojiMap();

		return $instance;
	}

	protected function buildEmojiMap(): array
	{
		$emojiData = \XF::app()->data(EmojiTranslit::class);
		return $emojiData->getTransliterationMap();
	}

	#[\ReturnTypeWillChange]
	public function transliterate($string, $start = 0, $end = -1)
	{
		if ($start === 0 && $end === -1 && preg_match('//u', $string))
		{
			return strtr($string, $this->map);
		}

		$result = parent::transliterate($string, $start, $end);

		if ($result !== false)
		{
			$result = strtr($result, $this->map);
		}

		return $result;
	}

	public function createInverse(): ?\Transliterator
	{
		throw new \BadMethodCallException('Reverse transliteration is not supported.');
	}
}
