<?php

namespace XF\Entity;

use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\ForumPrefixRepository;

/**
 * COLUMNS
 * @property int|null $prefix_id
 * @property int $prefix_group_id
 * @property int $display_order
 * @property int $materialized_order
 * @property string $css_class
 * @property array $allowed_user_group_ids
 *
 * GETTERS
 * @property-read string|Phrase $title
 * @property-read bool $has_usage_help
 * @property-read string|Phrase $description
 * @property-read string|Phrase $usage_help
 *
 * RELATIONS
 * @property-read \XF\Entity\Phrase|null $MasterTitle
 * @property-read ThreadPrefixGroup|null $PrefixGroup
 * @property-read \XF\Entity\Phrase|null $MasterDescription
 * @property-read \XF\Entity\Phrase|null $MasterUsageHelp
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XF\Entity\ForumPrefix> $ForumPrefixes
 */
class ThreadPrefix extends AbstractPrefix
{
	protected function getClassIdentifier()
	{
		return 'XF:ThreadPrefix';
	}

	protected static function getContentType()
	{
		return 'thread';
	}

	protected function _postDelete()
	{
		parent::_postDelete();

		$this->repository(ForumPrefixRepository::class)->removePrefixAssociations($this);
	}

	public static function getStructure(Structure $structure)
	{
		self::setupDefaultStructure($structure, 'xf_thread_prefix', 'XF:ThreadPrefix', [
			'has_description' => true,
			'has_usage_help' => true,
		]);

		$structure->relations['ForumPrefixes'] = [
			'entity' => 'XF:ForumPrefix',
			'type' => self::TO_MANY,
			'conditions' => 'prefix_id',
		];

		return $structure;
	}
}
