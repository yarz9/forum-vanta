<?php

namespace XF\Service\Language;

use XF\Entity\Language;
use XF\Repository\LanguageRepository;
use XF\Service\AbstractService;
use XF\Tree;

class RebuildService extends AbstractService
{
	/**
	 * @var Tree
	 */
	protected $languageTree;

	protected function setupLanguageTree()
	{
		if ($this->languageTree)
		{
			return;
		}

		/** @var LanguageRepository $repo */
		$repo = $this->app->em()->getRepository(LanguageRepository::class);
		$this->languageTree = $repo->getLanguageTree(false);
	}

	public function rebuildFullParentList()
	{
		$this->setupLanguageTree();

		$this->db()->beginTransaction();
		$this->_rebuildParentList(0, []);
		$this->db()->commit();
	}

	protected function _rebuildParentList($id, array $path)
	{
		array_unshift($path, $id);

		/** @var Language $language */
		$language = $this->languageTree->getData($id);
		if ($language)
		{
			if ($path != $language->parent_list)
			{
				$language->fastUpdate('parent_list', $path);
			}
		}

		foreach ($this->languageTree->childIds($id) AS $childId)
		{
			$this->_rebuildParentList($childId, $path);
		}
	}
}
