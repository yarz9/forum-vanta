<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Str\EmojiFormatter;

trait EmojiIconTrait
{
	/**
	 * @return string|null
	 */
	public function getEmoji()
	{
		if (!$this->emoji_shortname)
		{
			return null;
		}

		return $this->getEmojiFormatter()->formatShortnameToEmoji($this->emoji_shortname);
	}

	protected function verifyEmojiShortName(string &$shortname): bool
	{
		if (!$shortname)
		{
			return true;
		}

		$formatter = $this->getEmojiFormatter();

		// convert any raw emoji to short-names
		$shortname = $formatter->formatEmojiToShortname($shortname);

		// validate short-name format
		if (!preg_match('/^:\w+:$/', $shortname))
		{
			$this->error(
				\XF::phrase('please_enter_valid_emoji_short_name'),
				'emoji_shortname'
			);
			return false;
		}

		// validate short-name converts to a valid emoji
		$emoji = $formatter->formatShortnameToEmoji($shortname);
		if ($shortname === $emoji)
		{
			$this->error(
				\XF::phrase('please_enter_valid_emoji_short_name'),
				'emoji_shortname'
			);
			return false;
		}

		return true;
	}

	protected static function addEmojiIconStructureElements(Structure $structure)
	{
		$structure->columns['emoji_shortname'] = [
			'type' => Entity::STR,
			'maxLength' => 100,
			'default' => '',
		];
		$structure->getters['emoji'] = true;
	}

	protected function getEmojiFormatter(): EmojiFormatter
	{
		return $this->app()->stringFormatter()->getEmojiFormatter();
	}
}
