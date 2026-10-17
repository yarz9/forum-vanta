<?php

namespace XF\Repository;

use XF\Entity\Style;
use XF\Entity\User;
use XF\Finder\StyleFinder;
use XF\Job\Atomic;
use XF\Job\StyleAssetRebuild;
use XF\Job\StylePropertyRebuild;
use XF\Job\TemplateRebuild;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Service\Style\AssetRebuildService;
use XF\Service\StyleProperty\RebuildService;
use XF\Tree;

class StyleRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findStyles()
	{
		return $this->finder(StyleFinder::class)->order('style_id');
	}

	/**
	 * @return Style
	 */
	public function getMasterStyle()
	{
		$style = $this->em->create(Style::class);
		$style->setTrusted('style_id', 0);
		$style->setTrusted('parent_list', [0]);
		$style->setTrusted('parent_id', -1);
		$style->title = \XF::phrase('master_style');
		$style->setReadOnly(true);

		return $style;
	}

	public function getSelectableStyles()
	{
		$styles = [];
		foreach ($this->getStyleTree(false)->getFlattened(0) AS $id => $record)
		{
			if (\XF::visitor()->is_admin || $record['record']->user_selectable)
			{
				$styles[$id] = $record['record']->toArray();
				$styles[$id]['depth'] = $record['depth'];
			}
		}
		return $styles;
	}

	public function getSelectedStyleIdForUser(?User $user = null): int
	{
		$user = $user ?? \XF::visitor();

		$styleId = $user->style_id !== 0
			? $user->style_id
			: (int) \XF::options()->defaultStyleId;

		$styles = \XF::app()->container('style.cache');
		$selectedStyle = $styles[$styleId] ?? null;
		if (
			!$selectedStyle ||
			!$selectedStyle['user_selectable'] && !$user->is_admin
		)
		{
			$styleId = (int) \XF::options()->defaultStyleId;
		}

		return $styleId;
	}

	/**
	 * @param User|null $user
	 *
	 * @return Style[]
	 */
	public function getUserSelectableStyles(?User $user = null)
	{
		if (!$user)
		{
			$user = \XF::visitor();
		}

		$styles = [];
		foreach ($this->getStyleTree(false)->getFlattened(0) AS $id => $record)
		{
			if ($user->is_admin || $record['record']->user_selectable)
			{
				$styles[$id] = $record['record'];
			}
		}
		return $styles;
	}

	public function getStyleTree($withMaster = null)
	{
		$styles = $this->findStyles()->fetch();
		return $this->createStyleTree($styles, $withMaster);
	}

	public function createStyleTree($styles, $withMaster = null, $rootId = null)
	{
		if ($withMaster === null)
		{
			$withMaster = \XF::$developmentMode;
		}
		if ($withMaster)
		{
			if ($styles instanceof AbstractCollection)
			{
				$styles = $styles->toArray();
			}
			$styles[0] = $this->getMasterStyle();
		}

		if ($rootId === null)
		{
			$rootId = $withMaster ? -1 : 0;
		}

		return new Tree($styles, 'parent_id', $rootId);
	}

	public function canSupportStyleArchives(): bool
	{
		return class_exists('ZipArchive');
	}

	/**
	 * @return list<string>
	 */
	public function getVariations(bool $includeDefault = true): array
	{
		$variations = ['alternate'];

		if ($includeDefault)
		{
			array_unshift($variations, \XF\Style::VARIATION_DEFAULT);
		}

		return $variations;
	}

	protected static $lastModifiedUpdate = null;

	public function updateAllStylesLastModifiedDate()
	{
		$newModified = time();
		if (self::$lastModifiedUpdate && self::$lastModifiedUpdate === $newModified)
		{
			return;
		}

		self::$lastModifiedUpdate = $newModified;

		$this->db()->update('xf_style', ['last_modified_date' => $newModified], null);
		\XF::registry()->set('masterStyleModifiedDate', $newModified);

		// none of this will be valid, so use this opportunity to just wipe it
		$this->db()->emptyTable('xf_css_cache');

		\XF::runOnce('styleCacheRebuild', function ()
		{
			$this->rebuildStyleCache();
		});
	}

	public function updateAllStylesLastModifiedDateLater()
	{
		\XF::runOnce('styleLastModifiedDate', function ()
		{
			$this->updateAllStylesLastModifiedDate();
		});
	}

	public function getStyleCacheData()
	{
		$styles = $this->finder(StyleFinder::class)->fetch();
		$cache = [];

		foreach ($styles AS $style)
		{
			/** @var Style $style */
			$cache[$style->style_id] = $style->toArray();
		}

		return $cache;
	}

	public function rebuildStyleCache()
	{
		$cache = $this->getStyleCacheData();
		\XF::registry()->set('styles', $cache);
		return $cache;
	}

	public function enqueuePartialStyleDataRebuild()
	{
		\XF::runOnce('stylePartialRebuild', function ()
		{
			$this->triggerPartialStyleDataRebuild();
		});
	}

	public function triggerPartialStyleDataRebuild()
	{
		$this->app()->service(AssetRebuildService::class)->rebuildAssetStyleCache();
		$this->app()->service(RebuildService::class)->rebuildPropertyStyleCache();
	}

	public function triggerStyleDataRebuild()
	{
		// this task is a subset of what we're doing so don't bother
		\XF::dequeueRunOnce('stylePartialRebuild');

		$this->app()->service(\XF\Service\Style\RebuildService::class)->rebuildFullParentList();

		$this->app()->jobManager()->enqueueUnique('styleRebuild', Atomic::class, [
			'execute' => [TemplateRebuild::class, StyleAssetRebuild::class, StylePropertyRebuild::class],
		]);
	}
}
