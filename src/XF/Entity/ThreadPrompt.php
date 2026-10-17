<?php

namespace XF\Entity;

use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\ForumPromptRepository;

/**
 * COLUMNS
 * @property int|null $prompt_id
 * @property int $prompt_group_id
 * @property int $display_order
 * @property int $materialized_order
 *
 * GETTERS
 * @property-read Phrase|string $title
 *
 * RELATIONS
 * @property-read \XF\Entity\Phrase|null $MasterTitle
 * @property-read ThreadPromptGroup|null $PromptGroup
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XF\Entity\ForumPrompt> $ForumPrompts
 */
class ThreadPrompt extends AbstractPrompt
{
	protected function getClassIdentifier()
	{
		return 'XF:ThreadPrompt';
	}

	protected static function getContentType()
	{
		return 'thread';
	}

	protected function _postDelete()
	{
		parent::_postDelete();

		$this->repository(ForumPromptRepository::class)->removePromptAssociations($this);
	}

	public static function getStructure(Structure $structure)
	{
		self::setupDefaultStructure($structure, 'xf_thread_prompt', 'XF:ThreadPrompt');

		$structure->relations['ForumPrompts'] = [
			'entity' => 'XF:ForumPrompt',
			'type' => self::TO_MANY,
			'conditions' => 'prompt_id',
		];

		return $structure;
	}
}
