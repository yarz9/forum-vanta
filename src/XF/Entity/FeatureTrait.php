<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

trait FeatureTrait
{
	/**
	 * Determines whether or not the visitor may create, edit, or remove a
	 * feature for this entity.
	 */
	abstract public function canFeatureUnfeature(&$error = null): bool;

	public function isFeatured(): bool
	{
		return $this->featured;
	}

	public static function addFeaturableStructureElements(Structure $structure)
	{
		$structure->columns['featured'] = [
			'type' => Entity::BOOL,
			'default' => false,
		];

		$structure->relations['Feature'] = [
			'entity' => 'XF:FeaturedContent',
			'type' => Entity::TO_ONE,
			'conditions' => [
				['content_type', '=', $structure->contentType],
				['content_id', '=', '$' . $structure->primaryKey],
			],
		];
	}
}
