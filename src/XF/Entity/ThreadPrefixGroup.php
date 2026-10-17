<?php

namespace XF\Entity;

use XF\Mvc\Entity\Structure;
use XF\Phrase;

/**
 * COLUMNS
 * @property int|null $prefix_group_id
 * @property int $display_order
 *
 * GETTERS
 * @property-read Phrase|string $title
 *
 * RELATIONS
 * @property-read \XF\Entity\Phrase|null $MasterTitle
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XF\Entity\ThreadPrefix> $Prefixes
 */
class ThreadPrefixGroup extends AbstractPrefixGroup
{
	protected function getClassIdentifier()
	{
		return 'XF:ThreadPrefix';
	}

	protected static function getContentType()
	{
		return 'thread';
	}

	public static function getStructure(Structure $structure)
	{
		self::setupDefaultStructure(
			$structure,
			'xf_thread_prefix_group',
			'XF:ThreadPrefixGroup',
			'XF:ThreadPrefix'
		);

		return $structure;
	}
}
