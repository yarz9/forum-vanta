<?php

namespace XF\Api\ControllerPlugin;

use XF\Api\Mvc\Reply\ApiResult;
use XF\Behavior\TreeStructured;
use XF\Mvc\Entity\AbstractCollection;
use XF\Repository\AbstractCategoryTree;

class CategoryTreePlugin extends AbstractPlugin
{
	public function actionGet(AbstractCategoryTree $repo)
	{
		$tree = $this->getCategoryTreeForList($repo);

		/** @var AbstractCollection $categories */
		$categories = $tree->getAllData();

		$result = [
			'tree_map' => (object) $tree->getParentMapSimplified(),
			'categories' => $categories->toApiResults(),
		];
		return $this->apiResult($result);
	}

	public function actionGetFlattened(AbstractCategoryTree $repo)
	{
		$tree = $this->getCategoryTreeForList($repo);

		$flat = [];
		foreach ($tree->getFlattened() AS $id => $data)
		{
			$flat[] = [
				'category' => $data['record']->toApiResult(),
				'depth' => $data['depth'],
			];
		}

		return $this->apiResult(['categories_flat' => $flat]);
	}

	public function getCategoryTreeForList(
		AbstractCategoryTree $repo,
		?\XF\Entity\AbstractCategoryTree $withinCategory = null,
		$with = 'api'
	)
	{
		if (\XF::isApiCheckingPermissions())
		{
			$categories = $repo->getViewableCategories($withinCategory, $with);
		}
		else
		{
			$categories = $repo->findCategoryList($withinCategory, $with)->fetch();
		}

		return $repo->createCategoryTree($categories);
	}

	/**
	 * @param \XF\Entity\AbstractCategoryTree $category
	 * @return ApiResult
	 *
	 * @api-in bool $delete_children If true, child nodes will be deleted. Otherwise, they will be connected to this node's parent.
	 *
	 * @api-out true $success
	 */
	public function actionDelete(\XF\Entity\AbstractCategoryTree $category)
	{
		$deleteChildAction = $this->filter('delete_children', 'bool') ? 'delete' : 'move';
		$category->getBehavior(TreeStructured::class)->setOption('deleteChildAction', $deleteChildAction);

		$category->delete();

		return $this->apiSuccess();
	}
}
