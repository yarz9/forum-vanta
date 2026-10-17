<?php

namespace XF\Repository;

use XF\Entity\Navigation;
use XF\Finder\NavigationFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Navigation\AbstractType;
use XF\Navigation\Compiler;
use XF\Tree;
use XF\Util\File;

class NavigationRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findNavigationForList()
	{
		return $this->finder(NavigationFinder::class)->order(['parent_navigation_id', 'display_order']);
	}

	public function createNavigationTree($entries = null, $rootId = '')
	{
		if ($entries === null)
		{
			$entries = $this->findNavigationForList()->fetch();
		}

		return new Tree($entries, 'parent_navigation_id', $rootId);
	}


	/**
	 * @return Navigation[]
	 */
	public function getTopLevelEntries()
	{
		return $this->finder(NavigationFinder::class)->where('parent_navigation_id', '')->order('display_order')->fetch();
	}

	/**
	 * @param string $type
	 * @param bool $throw
	 *
	 * @return AbstractType|null
	 */
	public function getTypeHandler($type, $throw = false)
	{
		$handlerClass = $this->db()->fetchOne("
			SELECT handler_class
			FROM xf_navigation_type
			WHERE navigation_type_id = ?
		", $type);
		if (!$handlerClass)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("No navigation type handler for '$type'");
			}
			return null;
		}

		$handlerClass = \XF::stringToClass($handlerClass, '%s\Navigation\%s');

		if (!class_exists($handlerClass))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("Navigation type handler for '$type' does not exist: $handlerClass");
			}
			return null;
		}

		$handlerClass = \XF::extendClass($handlerClass);
		return new $handlerClass($type);
	}

	/**
	 * @return AbstractType[]
	 */
	public function getTypeHandlers()
	{
		$pairs = $this->db()->fetchPairs("
			SELECT navigation_type_id, handler_class
			FROM xf_navigation_type
			ORDER BY display_order
		");
		$handlers = [];
		foreach ($pairs AS $type => $class)
		{
			$className = \XF::stringToClass($class, '%s\Navigation\%s');
			if (class_exists($className))
			{
				$className = \XF::extendClass($className);
				$handlers[$type] = new $className($type);
			}
		}

		return $handlers;
	}

	public function rebuildNavigationEntries(): void
	{
		$entries = $this->finder(NavigationFinder::class)
			->order(['parent_navigation_id', 'display_order'])
			->fetch();
		foreach ($entries AS $entry)
		{
			$entry->rebuildCompiledData();
			$entry->saveIfChanged();
		}

		\XF::runOnce('navigationCacheRebuild', function (): void
		{
			$this->rebuildNavigationCache();
		});
	}

	public function rebuildNavigationCache()
	{
		$entries = $this->finder(NavigationFinder::class)
			->whereAddOnActive()
			->order(['parent_navigation_id', 'display_order'])
			->fetch();

		$tree = $this->createNavigationTree($entries);

		/** @var Compiler $navigationCompiler */
		$navigationCompiler = $this->app()['navigation.compiler'];
		$code = $navigationCompiler->compileTree($tree);

		$cacheFile = 'code-cache://' . $this->app()['navigation.file'];
		$contents = "<?php\n\n" . $code;
		File::writeToAbstractedPath($cacheFile, $contents);
	}
}
