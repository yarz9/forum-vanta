<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\NodePermissionPlugin;
use XF\ControllerPlugin\SortPlugin;
use XF\Entity\Node;
use XF\Finder\NodeTypeFinder;
use XF\Mvc\ParameterBag;
use XF\Repository\ForumTypeRepository;
use XF\Repository\ModeratorRepository;
use XF\Repository\NodeRepository;
use XF\Repository\PermissionEntryRepository;

use function is_array;

class NodeController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('node');
	}

	public function actionIndex()
	{
		$nodeRepo = $this->getNodeRepo();
		$nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList(null, 'NodeType'));

		/** @var ModeratorRepository $moderatorRepo */
		$moderatorRepo = $this->repository(ModeratorRepository::class);
		$moderators = $moderatorRepo->findContentModeratorsForList()
			->where('content_type', 'node')
			->fetch()->groupBy('content_id');

		$customPermissions = $this->repository(PermissionEntryRepository::class)->getContentWithCustomPermissions('node');

		$viewParams = [
			'nodeTree' => $nodeTree,
			'moderators' => $moderators,
			'customPermissions' => $customPermissions,
		];
		return $this->view('XF:Node\Listing', 'node_list', $viewParams);
	}

	public function actionSort()
	{
		$nodeRepo = $this->getNodeRepo();
		$nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList(null, 'NodeType'));

		if ($this->isPost())
		{
			$sorter = $this->plugin(SortPlugin::class);
			$sortTree = $sorter->buildSortTree($this->filter('nodes', 'json-array'));
			$sorter->sortTree($sortTree, $nodeTree->getAllData(), 'parent_node_id');

			return $this->redirect($this->buildLink('nodes'));
		}
		else
		{
			$viewParams = [
				'nodeTree' => $nodeTree,
			];
			return $this->view('XF:Node\Sort', 'node_sort', $viewParams);
		}
	}

	public function actionEdit(ParameterBag $params)
	{
		$node = $this->assertNodeExists($params['node_id']);
		if (!$node->NodeType)
		{
			return $this->error(\XF::phrase('node_type_for_node_not_found_not_modifiable'), 404);
		}

		return $this->redirect($this->buildLink($node->NodeType->admin_route . '/edit', $node));
	}

	public function actionAdd()
	{
		$nodeTypeId = $this->filter('node_type_id', 'str');
		$parentNodeId = $this->filter('parent_node_id', 'uint');
		$nodeTypes = $this->finder(NodeTypeFinder::class)->order('node_type_id')->fetch();

		if ($this->isPost() && $nodeTypeId && isset($nodeTypes[$nodeTypeId]))
		{
			$nodeType = $nodeTypes[$nodeTypeId];

			$args = [
				'parent_node_id' => $parentNodeId,
				'forum_type_id' => $nodeTypeId == 'Forum' ? $this->filter('forum_type_id', 'str') : null,
			];

			return $this->redirect($this->buildLink($nodeType->admin_route . '/add', null, $args), '');
		}

		$defaultNodeTypeId = $nodeTypeId;
		if (!$defaultNodeTypeId || !isset($nodeTypes[$defaultNodeTypeId]))
		{
			$defaultNodeTypeId = $nodeTypes->first()->node_type_id;
		}

		$forumTypesInfo = $this->repository(ForumTypeRepository::class)->getForumTypesList(
			ForumTypeRepository::FILTER_MANUAL_CREATE
		);
		$defaultForumTypeId = key($forumTypesInfo);

		$viewParams = [
			'nodeTypes' => $nodeTypes,
			'defaultNodeTypeId' => $defaultNodeTypeId,
			'parentNodeId' => $parentNodeId,
			'forumTypesInfo' => $forumTypesInfo,
			'defaultForumTypeId' => $defaultForumTypeId,
		];
		return $this->view('XF:Node\Add', 'node_add', $viewParams);
	}

	public function actionDelete(ParameterBag $params)
	{
		$node = $this->assertNodeExists($params['node_id']);
		if (!$node->NodeType)
		{
			return $this->error(\XF::phrase('node_type_for_node_not_found_not_modifiable'), 404);
		}

		return $this->redirect($this->buildLink($node->NodeType->admin_route . '/delete', $node), '');
	}

	/**
	 * @return NodePermissionPlugin
	 */
	protected function getNodePermissionPlugin()
	{
		/** @var NodePermissionPlugin $plugin */
		$plugin = $this->plugin(NodePermissionPlugin::class);
		$plugin->setFormatters('XF:Permission\Node%s', 'permission_node_%s');
		$plugin->setRoutePrefix('nodes/permissions');

		return $plugin;
	}

	public function actionPermissions(ParameterBag $params)
	{
		return $this->getNodePermissionPlugin()->actionList($params);
	}

	public function actionPermissionsEdit(ParameterBag $params)
	{
		return $this->getNodePermissionPlugin()->actionEdit($params);
	}

	public function actionPermissionsSave(ParameterBag $params)
	{
		return $this->getNodePermissionPlugin()->actionSave($params);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return Node
	 */
	protected function assertNodeExists($id, $with = null, $phraseKey = null)
	{
		if (!is_array($with))
		{
			$with = $with ? [$with] : [];
		}
		$with[] = 'NodeType';

		return $this->assertRecordExists(Node::class, $id, $with, $phraseKey);
	}

	/**
	 * @return NodeRepository
	 */
	protected function getNodeRepo()
	{
		return $this->repository(NodeRepository::class);
	}
}
