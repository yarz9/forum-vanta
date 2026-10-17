<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $reaction_content_id
 * @property int $reaction_id_
 * @property string $content_type
 * @property int $content_id
 * @property int $reaction_user_id
 * @property int $reaction_date
 * @property int $content_user_id
 * @property bool $is_counted
 *
 * GETTERS
 * @property-read Entity|null $Content
 * @property mixed $reaction_id
 *
 * RELATIONS
 * @property-read Reaction|null $Reaction
 * @property-read User|null $ReactionUser
 * @property-read User|null $Owner
 * @property-read User|null $Liker
 */
class LikedContent extends ReactionContent
{
	public function getReactionId()
	{
		// likes are always ID 1
		return 1;
	}

	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->getters['reaction_id'] = false;

		$structure->relations['Liker'] = $structure->relations['ReactionUser'];

		$structure->columnAliases['like_id'] = 'reaction_content_id';
		$structure->columnAliases['like_user_id'] = 'reaction_user_id';
		$structure->columnAliases['like_date'] = 'reaction_date';

		return $structure;
	}
}
