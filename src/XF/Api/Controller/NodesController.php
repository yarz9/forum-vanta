<?php

namespace XF\Api\Controller;

use XF\Api\ControllerPlugin\NodePlugin;
use XF\Entity\Node;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Repository\NodeRepository;

/**
 * @api-group Nodes
 */
class NodesController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('node');
	}

	/**
	 * @api-desc Gets the node tree.
	 *
	 * @api-out array $tree_map A mapping that connects node parent IDs to a list of their child node IDs
	 * @api-out Node[] $nodes List of all nodes
	 */
	public function actionGet()
	{
		$nodeTree = $this->getNodeTreeForList();

		/** @var AbstractCollection $nodes */
		$nodes = $nodeTree->getAllData();

		$result = [
			'tree_map' => (object) $nodeTree->getParentMapSimplified(),
			'nodes' => $nodes->toApiResults(),
		];
		return $this->apiResult($result);
	}

	/**
	 * @api-desc Gets a flattened node tree. Traversing this will return a list of nodes in the expected order.
	 *
	 * @api-out array $nodes_flat An array. Each entry contains keys of "node" and "depth"
	 */
	public function actionGetFlattened()
	{
		$nodeTree = $this->getNodeTreeForList();

		$flat = [];
		foreach ($nodeTree->getFlattened() AS $id => $data)
		{
			$flat[] = [
				'node' => $data['record']->toApiResult(),
				'depth' => $data['depth'],
			];
		}

		return $this->apiResult(['nodes_flat' => $flat]);
	}

	/**
	 * @api-desc Creates a new node
	 *
	 * @api-see \XF\Api\ControllerPlugin\Node::setupNodeSave()
	 * @api-in str $node_type_id <req>
	 * @api-in str $node[title] <req>
	 * @api-in int $node[parent_node_id] <req>
	 *
	 * @api-out Node $node Information about the created node
	 */
	public function actionPost()
	{
		$this->assertAdminPermission('node');
		$this->assertRequiredApiInput(['node_type_id', 'node.title', 'node.parent_node_id']);

		/** @var Node $node */
		$node = $this->em()->create(Node::class);
		$node->node_type_id = $this->filter('node_type_id', 'str');
		if ($node->hasErrors())
		{
			return $this->error($node->getErrors());
		}

		/** @var NodePlugin $nodePlugin */
		$nodePlugin = $this->plugin(NodePlugin::class);
		$nodePlugin->setupNodeSave($node)->run();

		return $this->apiSuccess([
			'node' => $node->toApiResult(Entity::VERBOSITY_VERBOSE),
		]);
	}

	protected function getNodeTreeForList()
	{
		$nodeRepo = $this->getNodeRepo();

		if (\XF::isApiCheckingPermissions())
		{
			$nodes = $nodeRepo->getNodeList();
		}
		else
		{
			$nodes = $nodeRepo->getFullNodeListWithTypeData();
		}

		return $nodeRepo->createNodeTree($nodes);
	}

	/**
	 * @return NodeRepository
	 */
	protected function getNodeRepo()
	{
		return $this->repository(NodeRepository::class);
	}
}
