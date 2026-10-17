<?php

namespace XF\Navigation;

use XF\Entity\Navigation;
use XF\Entity\Node;
use XF\Finder\NodeFinder;
use XF\Repository\NodeRepository;
use XF\SubTree;
use XF\Tree;

use function intval;

class NodeType extends AbstractType
{
	public function getTitle()
	{
		return \XF::phrase('node');
	}

	public function validateConfigInput(Navigation $nav, array $config, Compiler $compiler, &$error = null, &$errorField = null)
	{
		$input = \XF::app()->inputFilterer()->filterArray($config, [
			'node_id' => 'uint',
			'with_children' => 'bool',
			'node_title' => 'bool',
		]);

		$node = \XF::em()->find(Node::class, $input['node_id']);
		if (!$node)
		{
			$error = \XF::phrase('requested_node_not_found');
			$errorField = 'node_id';
			return false;
		}
		$extraAttrs = $this->validateExtraAttrs($config, $compiler, $error, $errorField);
		if ($extraAttrs === false)
		{
			return false;
		}

		return [
			'node_id' => $node->node_id,
			'with_children' => $input['with_children'],
			'node_title' => $input['node_title'],
			'extra_attributes' => $extraAttrs,
		];
	}

	public function compileCode(Navigation $nav, Compiler $compiler)
	{
		$class = '\\' . self::class;

		$nodeId = intval($nav->type_config['node_id']);
		$navigationIdQuoted = '"' . $nav->navigation_id . '"';

		$indent = $compiler->getIndenter();
		$entryExpression = "[\n" . $this->getConfigArrayValuesCode($nav, $compiler) . "{$indent}]";

		$dataExpression = "{$class}::displayNodeExtended({$nodeId}, {$navigationIdQuoted})";
		$compiled = new CompiledEntry($nav->navigation_id, $dataExpression);
		$compiled->setGlobalSetup("{$class}::configureDisplayExtended({$nodeId}, {$navigationIdQuoted}, {$entryExpression});");

		return $compiled;
	}

	protected function getConfigArrayValuesCode(Navigation $nav, Compiler $compiler)
	{
		$indent = $compiler->getIndenter();
		$config = $nav->type_config;

		$withChildren = $config['with_children'] ?? false;
		$extraAttrs = $config['extra_attributes'] ?? [];

		if (empty($config['node_title']))
		{
			$titleCode = '\\XF::phrase(' . $compiler->getStringCode($nav->getPhraseName()) . ')';
		}
		else
		{
			$titleCode = 'null';
		}

		return (
			"{$indent}\t'title' => " . $titleCode . ",\n"
			. "{$indent}\t'with_children' => " . ($withChildren ? 'true' : 'false') . ",\n"
			. "{$indent}\t'attributes' => " . $compiler->compileArrayValue($extraAttrs) . ",\n"
		);
	}

	protected function getExtraEditParams(Navigation $nav, array $config)
	{
		$nodeRepo = \XF::repository(NodeRepository::class);
		$nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList(null, 'NodeType'));

		return [
			'nodeTree' => $nodeTree,
		];
	}

	protected static $loadIds = [];
	protected static $loadChildren = [];
	protected static $displayConfig = [];
	protected static $defaultConfig = [
		'with_children' => false,
		'title' => null,
		'attributes' => [],
	];
	protected static $loaded = [];

	public static function configureDisplayExtended($nodeId, $navigationId, array $config = [])
	{
		static::$loadIds[$nodeId] = $nodeId;

		$config = array_replace(static::$defaultConfig, $config);
		if ($config['with_children'])
		{
			static::$loadChildren[$nodeId] = $nodeId;
		}

		if ($navigationId === null)
		{
			static::$displayConfig[$nodeId] = $config;
		}
		else
		{
			static::$displayConfig["{$nodeId}-{$navigationId}"] = $config;
		}
	}

	public static function configureDisplay($nodeId, array $config = [])
	{
		static::configureDisplayExtended($nodeId, null, $config);
	}

	public static function displayNodeExtended($nodeId, $navigationId)
	{
		$config = $navigationId === null ? static::$displayConfig[$nodeId] : static::$displayConfig["{$nodeId}-{$navigationId}"];

		static::loadPendingNodeData();

		if (!isset(static::$loaded[$nodeId]))
		{
			return null;
		}

		/** @var Node $node */
		$node = static::$loaded[$nodeId];
		if (!$node->canView())
		{
			return null;
		}

		$link = static::getNodeLink($node, $config);

		if ($config['with_children'])
		{
			$tree = new Tree(static::$loaded, 'parent_node_id', $node->node_id);
			$children = [];
			foreach ($tree AS $subTree)
			{
				if ($subTree->record->canView())
				{
					$children[] = static::displaySubTree($subTree);
				}
			}
			if ($children)
			{
				$link['children'] = $children;
			}
		}

		return $link;
	}

	public static function displayNode($nodeId)
	{
		return static::displayNodeExtended($nodeId, null);
	}

	protected static function getNodeLink(Node $node, array $config = [])
	{
		return [
			'title' => !empty($config['title']) ? $config['title'] : $node->title,
			'href' => \XF::app()->router('public')->buildLink($node->getRoute('public'), $node),
			'attributes' => $config['attributes'] ?? [],
		];
	}

	protected static function displaySubTree(SubTree $subTree)
	{
		$link = static::getNodeLink($subTree->record);
		$children = [];
		foreach ($subTree AS $childTree)
		{
			if ($childTree->record->canView())
			{
				$children[] = static::displaySubTree($childTree);
			}
		}
		if ($children)
		{
			$link['children'] = $children;
		}

		return $link;
	}

	/**
	 * @param bool $withChildren DEPRECATED - parameter is ignored, disregard
	 */
	protected static function loadPendingNodeData($withChildren = true)
	{
		if (!static::$loadIds)
		{
			return;
		}

		$em = \XF::em();
		$nodeWith = ['Permissions|' . \XF::visitor()->permission_combination_id];

		$nodes = $em->findByIds(Node::class, static::$loadIds, $nodeWith)->toArray();

		if (static::$loadChildren)
		{
			$descendantWhere = [];
			foreach ($nodes AS $nodeId => $node)
			{
				if (!isset(static::$loadChildren[$nodeId]))
				{
					continue;
				}

				/** @var Node $node */
				$left = $node->lft;
				$right = $node->rgt;
				if ($left + 1 < $right)
				{
					$descendantWhere[] = [
						['lft', '>', $left],
						['rgt', '<', $right],
					];
				}
			}

			if ($descendantWhere)
			{
				/** @var NodeFinder $descendantFinder */
				$descendantFinder = $em->getFinder(NodeFinder::class);
				$descendantFinder
					->whereOr($descendantWhere)
					->listable()
					->order('lft')
					->with($nodeWith);

				$nodes += $descendantFinder->fetch()->toArray();
			}
		}

		\XF::repository(NodeRepository::class)->loadNodeTypeDataForNodes($nodes);

		static::$loaded += $nodes;

		static::$loadIds = [];
		static::$loadChildren = [];
	}
}
