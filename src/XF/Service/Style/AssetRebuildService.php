<?php

namespace XF\Service\Style;

use XF\Entity\Style;
use XF\Repository\StyleRepository;
use XF\Service\AbstractService;
use XF\Tree;

class AssetRebuildService extends AbstractService
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

	public function rebuildAssetStyleCache()
	{
		$this->rebuildAssetStyleCacheForStyle(0);
		$this->repository(StyleRepository::class)->updateAllStylesLastModifiedDateLater();
	}

	public function rebuildAssetStyleCacheForStyle($styleId)
	{
		$this->setupStyleTree();

		$byStyle = [];
		foreach ($this->styleTree->getFlattened() AS $id => $style)
		{
			foreach ($style['record']->assets AS $key => $path)
			{
				$byStyle[$id][$key] = $path;
			}
		}

		$effectiveAssets = [];

		if ($styleId)
		{
			/** @var Style|null $style */
			$style = $this->styleTree->getData($styleId);
			if (!$style)
			{
				// invalid style, nothing to do
				return;
			}

			if (isset($byStyle[$style->parent_id]))
			{
				$effectiveAssets = $byStyle[$style->parent_id];
			}
		}
		// master style doesn't contain any assets by default at this point

		$this->db()->beginTransaction();
		$this->_rebuildAssetStyleCacheForStyle($styleId, $byStyle, $effectiveAssets);
		$this->db()->commit();
	}

	protected function _rebuildAssetStyleCacheForStyle($styleId, array $assetsByStyle, array $effectiveAssets)
	{
		if (isset($assetsByStyle[$styleId]))
		{
			foreach ($assetsByStyle[$styleId] AS $key => $path)
			{
				$effectiveAssets[$key] = $path;
			}
		}

		if ($styleId)
		{
			/** @var Style|null $style */
			$style = $this->styleTree->getData($styleId);
			if ($style)
			{
				$style->effective_assets = $effectiveAssets;
				$style->saveIfChanged($saved, true, false);
			}
		}

		foreach ($this->styleTree->childIds($styleId) AS $childId)
		{
			$this->_rebuildAssetStyleCacheForStyle($childId, $assetsByStyle, $effectiveAssets);
		}
	}
}
