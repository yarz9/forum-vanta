<?php

namespace XF\Repository;

use XF\Entity\Node;
use XF\Finder\NodeFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Repository;
use XF\SubTree;
use XF\Tree;

use function in_array, is_array;

class NodeRepository extends Repository
{
	public function getNodeList(?Node $withinNode = null)
	{
		if ($withinNode && !$withinNode->hasChildren())
		{
			return $this->em->getEmptyCollection();
		}

		$nodes = $this->findNodesForList($withinNode)->fetch();
		$this->loadNodeTypeDataForNodes($nodes);

		return $this->filterViewable($nodes);
	}

	public function getFullNodeList(?Node $withinNode = null, $with = null)
	{
		/** @var NodeFinder $finder */
		$finder = $this->finder(NodeFinder::class)->order('lft');
		if ($withinNode)
		{
			$finder->descendantOf($withinNode);
		}
		if ($with)
		{
			$finder->with($with);
		}

		return $finder->fetch();
	}

	public function getFullNodeListWithTypeData(?Node $withinNode = null, $with = null)
	{
		$nodes = $this->getFullNodeList($withinNode, $with);
		$this->loadNodeTypeDataForNodes($nodes);

		return $nodes;
	}

	public function getFullNodeListCached($cacheKey, ?Node $withinNode = null, $with = null)
	{
		static $nodeListCache = [];

		if (isset($nodeListCache[$cacheKey]))
		{
			return $nodeListCache[$cacheKey];
		}

		$nodes = $this->getFullNodeList($withinNode, $with);
		$nodeListCache[$cacheKey] = $nodes;

		return $nodes;
	}

	public function findNodesForList(?Node $withinNode = null)
	{
		/** @var NodeFinder $finder */
		$finder = $this->finder(NodeFinder::class);
		if ($withinNode)
		{
			$finder->descendantOf($withinNode);
		}
		$finder->listable()
			->setDefaultOrder('lft');

		return $finder;
	}

	public function findSiblings(Node $node, $listable = true)
	{
		/** @var NodeFinder $finder */
		$finder = $this->finder(NodeFinder::class);

		$finder->where('parent_node_id', $node->parent_node_id);

		if ($listable)
		{
			$finder->listable();
		}

		$finder->setDefaultOrder('lft');

		return $finder;
	}

	public function findChildren(Node $node, $listable = true)
	{
		/** @var NodeFinder $finder */
		$finder = $this->finder(NodeFinder::class);

		$finder->where('parent_node_id', $node->node_id);

		if ($listable)
		{
			$finder->listable();
		}

		$finder->setDefaultOrder('lft');

		return $finder;
	}

	public function findDescendants(Node $node, $listable = true)
	{
		/** @var NodeFinder $finder */
		$finder = $this->finder(NodeFinder::class);

		$finder->descendantOf($node);

		if ($listable)
		{
			$finder->listable();
		}

		$finder->setDefaultOrder('lft');

		return $finder;
	}

	public function loadNodeTypeDataForNodes($nodes)
	{
		$types = [];
		foreach ($nodes AS $node)
		{
			$types[$node->node_type_id][$node->node_id] = $node->node_id;
		}

		$nodeTypes = $this->app()->container('nodeTypes');

		foreach ($types AS $typeId => $nodeIds)
		{
			if (isset($nodeTypes[$typeId]))
			{
				$entityIdent = $nodeTypes[$typeId]['entity_identifier'];
				$entityClass = $this->em->getEntityClassName($entityIdent);
				$extraWith = $entityClass::getListedWith();
				$this->em->findByIds($entityIdent, $nodeIds, $extraWith);
			}
		}

		return $nodes;
	}

	public function filterViewable(AbstractCollection $nodes)
	{
		if (!$nodes->count())
		{
			return $nodes;
		}

		\XF::visitor()->cacheNodePermissions();
		return $nodes->filterViewable();
	}

	public function getNodeOptionsData($includeEmpty = true, $enableTypes = null, $type = null, $checkPerms = false)
	{
		$choices = [];
		if ($includeEmpty)
		{
			$choices = [
				0 => ['_type' => 'option', 'value' => 0, 'label' => \XF::phrase('(none)')],
			];
		}

		$nodeList = $this->getFullNodeList();
		if ($checkPerms)
		{
			$this->loadNodeTypeDataForNodes($nodeList);
			$nodeList = $nodeList->filterViewable();
		}

		foreach ($this->createNodeTree($nodeList)->getFlattened() AS $entry)
		{
			/** @var Node $node */
			$node = $entry['record'];

			if ($entry['depth'])
			{
				$prefix = str_repeat('--', $entry['depth']) . ' ';
			}
			else
			{
				$prefix = '';
			}
			$choices[$node->node_id] = [
				'value' => $node->node_id,
				'label' => $prefix . $node->title,
			];
			if ($enableTypes !== null)
			{
				if (!is_array($enableTypes))
				{
					$enableTypes = [$enableTypes];
				}
				$choices[$node->node_id]['disabled'] = in_array($node->node_type_id, $enableTypes) ? false : 'disabled';
			}
			if ($type !== null)
			{
				$choices[$node->node_id]['_type'] = $type;
			}
		}

		return $choices;
	}

	public function createNodeTree($nodes, $rootId = 0)
	{
		return new Tree($nodes, 'parent_node_id', $rootId);
	}

	public function getNodeListExtras(Tree $nodeTree)
	{
		$finalOutput = [];
		$f = function (Node $node, array $children) use (&$f, &$finalOutput)
		{
			$childOutput = [];
			foreach ($children AS $id => $child)
			{
				/** @var SubTree $child */
				$childOutput[$id] = $f($child->record, $child->children());
			}

			$output = $this->mergeNodeListExtras($node->getNodeListExtras(), $childOutput);
			$finalOutput[$node->node_id] = $output;

			return $output;
		};

		foreach ($nodeTree AS $id => $subTree)
		{
			$f($subTree->record, $subTree->children());
		}

		return $finalOutput;
	}

	public function mergeNodeListExtras(array $extras, array $childExtras)
	{
		$output = array_merge([
			'discussion_count' => 0,
			'message_count' => 0,
			'hasNew' => false,
			'privateInfo' => false,
			'childCount' => 0,
			'last_post_id' => 0,
			'last_post_date' => 0,
			'last_post_user_id' => 0,
			'last_post_username' => '',
			'last_thread_id' => 0,
			'last_thread_title' => '',
			'last_thread_prefix_id' => 0,
			'LastThread' => null,
			'LastPostUser' => null,
		], $extras);

		foreach ($childExtras AS $child)
		{
			if (!empty($child['discussion_count']))
			{
				$output['discussion_count'] += $child['discussion_count'];
			}

			if (!empty($child['message_count']))
			{
				$output['message_count'] += $child['message_count'];
			}

			if (!empty($child['last_post_date']) && $child['last_post_date'] > $output['last_post_date'])
			{
				$output['last_post_id'] = $child['last_post_id'];
				$output['last_post_date'] = $child['last_post_date'];
				$output['last_post_user_id'] = $child['last_post_user_id'];
				$output['last_post_username'] = $child['last_post_username'];
				$output['last_thread_id'] = $child['last_thread_id'];
				$output['last_thread_title'] = $child['last_thread_title'];
				$output['last_thread_prefix_id'] = $child['last_thread_prefix_id'];
				$output['LastPostUser'] = $child['LastPostUser'];
				$output['LastThread'] = $child['LastThread'];
			}

			if (!empty($child['hasNew']))
			{
				// one child has new stuff
				$output['hasNew'] = true;
			}

			$output['childCount'] += 1 + (!empty($child['childCount']) ? $child['childCount'] : 0);
		}

		return $output;
	}
}
