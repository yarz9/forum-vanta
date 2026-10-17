<?php

namespace XF\Service\Style;

use XF\Entity\Style;
use XF\Repository\StyleRepository;
use XF\Service\AbstractService;
use XF\Tree;

class RebuildService extends AbstractService
{
	/**
	 * @var Tree
	 */
	protected $styleTree;

	protected function setupStyleTree()
	{
		if ($this->styleTree)
		{
			return;
		}

		/** @var StyleRepository $repo */
		$repo = $this->app->em()->getRepository(StyleRepository::class);
		$this->styleTree = $repo->getStyleTree(false);
	}

	public function rebuildFullParentList()
	{
		$this->setupStyleTree();

		$this->db()->beginTransaction();
		$this->_rebuildParentList(0, []);
		$this->db()->commit();
	}

	protected function _rebuildParentList($id, array $path)
	{
		array_unshift($path, $id);

		/** @var Style $style */
		$style = $this->styleTree->getData($id);
		if ($style)
		{
			if ($path != $style->parent_list)
			{
				$style->fastUpdate('parent_list', $path);
			}
		}

		foreach ($this->styleTree->childIds($id) AS $childId)
		{
			$this->_rebuildParentList($childId, $path);
		}
	}
}
