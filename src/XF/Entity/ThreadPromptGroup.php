<?php

namespace XF\Entity;

use XF\Mvc\Entity\Structure;
use XF\Phrase;

/**
 * COLUMNS
 * @property int|null $prompt_group_id
 * @property int $display_order
 *
 * GETTERS
 * @property-read Phrase|string $title
 *
 * RELATIONS
 * @property-read \XF\Entity\Phrase|null $MasterTitle
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XF\Entity\ThreadPrompt> $Prompts
 */
class ThreadPromptGroup extends AbstractPromptGroup
{
	protected function getClassIdentifier()
	{
		return 'XF:ThreadPrompt';
	}

	protected static function getContentType()
	{
		return 'thread';
	}

	public static function getStructure(Structure $structure)
	{
		self::setupDefaultStructure(
			$structure,
			'xf_thread_prompt_group',
			'XF:ThreadPromptGroup',
			'XF:ThreadPrompt'
		);

		return $structure;
	}
}
