<?php

namespace XF\Api\Controller;

use XF\Api\ControllerPlugin\NodePlugin;
use XF\Behavior\TreeStructured;
use XF\Entity\Node;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;

/**
 * @api-group Nodes
 */
class NodeController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('node', ['delete' => 'delete']);
	}

	/**
	 * @api-desc Gets information about the specified node
	 *
	 * @api-out Node $node
	 */
	public function actionGet(ParameterBag $params)
	{
		$node = $this->assertViewableNode($params->node_id);

		return $this->apiResult([
			'node' => $node->toApiResult(Entity::VERBOSITY_VERBOSE),
		]);
	}

	/**
	 * @api-desc Updates the specified node
	 *
	 * @api-see XF\Api\ControllerPlugin\Node::setupNodeSave()
	 *
	 * @api-out Node $node The updated node information
	 */
	public function actionPost(ParameterBag $params)
	{
		$this->assertAdminPermission('node');

		$node = $this->assertViewableNode($params->node_id);

		/** @var NodePlugin $nodePlugin */
		$nodePlugin = $this->plugin(NodePlugin::class);
		$nodePlugin->setupNodeSave($node)->run();

		return $this->apiSuccess([
			'node' => $node->toApiResult(Entity::VERBOSITY_VERBOSE),
		]);
	}

	/**
	 * @api-desc Deletes the specified node
	 *
	 * @api-in bool $delete_children If true, child nodes will be deleted. Otherwise, they will be connected to this node's parent.
	 *
	 * @api-out true $success
	 */
	public function actionDelete(ParameterBag $params)
	{
		$this->assertAdminPermission('node');

		$node = $this->assertViewableNode($params->node_id);

		$deleteChildAction = $this->filter('delete_children', 'bool') ? 'delete' : 'move';
		$node->getBehavior(TreeStructured::class)->setOption('deleteChildAction', $deleteChildAction);

		$node->delete();

		return $this->apiSuccess();
	}

	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return Node
	 *
	 * @throws Exception
	 */
	protected function assertViewableNode($id, $with = 'api')
	{
		return $this->assertViewableApiRecord(Node::class, $id, $with);
	}
}
