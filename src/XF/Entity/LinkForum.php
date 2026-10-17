<?php

namespace XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $node_id
 * @property string $link_url
 * @property int $redirect_count
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
class LinkForum extends AbstractNode
{
	public function isSearchEngineIndexable(): bool
	{
		return false;
	}

	public function getNodeTemplateRenderer($depth)
	{
		return [
			'template' => 'node_list_link_forum',
			'macro' => $depth <= 2 ? 'depth' . $depth : 'depthN',
		];
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_link_forum';
		$structure->shortName = 'XF:LinkForum';
		$structure->primaryKey = 'node_id';
		$structure->columns = [
			'node_id' => ['type' => self::UINT, 'required' => true],
			'link_url' => ['type' => self::STR, 'maxLength' => 150,
				'required' => 'please_enter_valid_url', 'api' => true,
			],
			'redirect_count' => ['type' => self::UINT, 'forced' => true, 'default' => 0, 'api' => true],
		];
		$structure->getters = [];
		$structure->relations = [];

		static::addDefaultNodeElements($structure);

		return $structure;
	}
}
