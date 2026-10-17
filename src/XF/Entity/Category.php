<?php

namespace XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $node_id
 *
 * GETTERS
 * @property-read string|null $node_name
 * @property-read string|null $title
 * @property-read string|null $description
 * @property-read int $depth
 *
 * RELATIONS
 * @property-read Node|null $Node
 */
class Category extends AbstractNode
{
	public function isSearchEngineIndexable(): bool
	{
		if ($this->Node->depth == 0
			&& $this->Node->display_in_list
			&& !$this->app()->options()->categoryOwnPage
		)
		{
			// don't include categories that are just anchors on the forum list
			return false;
		}

		return true;
	}

	public function getNodeTemplateRenderer($depth)
	{
		return [
			'template' => 'node_list_category',
			'macro' => $depth <= 2 ? 'depth' . $depth : 'depthN',
		];
	}

	public function getCategoryAnchor()
	{
		return $this->app()->router('public')->prepareStringForUrl($this->title) . '.' . $this->node_id;
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_category';
		$structure->shortName = 'XF:Category';
		$structure->primaryKey = 'node_id';
		$structure->columns = [
			'node_id' => ['type' => self::UINT, 'required' => true],
		];
		$structure->getters = [];
		$structure->relations = [];

		static::addDefaultNodeElements($structure);

		return $structure;
	}
}
