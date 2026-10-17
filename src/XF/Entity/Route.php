<?php

namespace XF\Entity;

use XF\Finder\RouteFinder;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Repository\AddOnRepository;
use XF\Repository\RouteRepository;
use XF\Util\Php;

/**
 * COLUMNS
 * @property int $route_id
 * @property string $route_type
 * @property string $route_prefix
 * @property string $sub_name
 * @property string $format
 * @property string $build_class
 * @property string $build_method
 * @property string $controller
 * @property string $context
 * @property string $action_prefix
 * @property string $addon_id
 *
 * GETTERS
 * @property-read string $unique_name
 *
 * RELATIONS
 * @property-read AddOn|null $AddOn
 */
class Route extends Entity
{
	/**
	 * @return string
	 */
	public function getUniqueName()
	{
		return $this->route_prefix . '/' . $this->sub_name;
	}

	protected function verifyRouteType($type)
	{
		// NOTE: disabling this because it prevents any add-ons from creating a new route type

		/*$types = $this->getRouteRepo()->getRouteTypes();
		if (!isset($types[$type]))
		{
			$this->error(\XF::phrase('please_enter_valid_route_type'), 'route_type');
			return false;
		}*/

		return true;
	}

	protected function verifySubName($subName)
	{
		if (!$subName)
		{
			return true;
		}

		if (!preg_match('#^([a-z0-9_-]+/?)+$#i', $subName))
		{
			$this->error(\XF::phrase('please_enter_valid_sub_name'), 'sub_name');
			return false;
		}

		return true;
	}

	protected function _preSave()
	{
		if ($this->getOption('check_duplicate'))
		{
			$existing = $this->finder(RouteFinder::class)->where([
				'route_type' => $this->route_type,
				'route_prefix' => $this->route_prefix,
				'sub_name' => $this->sub_name,
			])->fetchOne();
			if ($existing && $existing !== $this)
			{
				$this->error(\XF::phrase('please_enter_unique_route_prefix_and_sub_name'), 'route_prefix');
			}
		}

		if ($this->build_class || $this->build_method)
		{
			if (!Php::validateCallbackPhrased(
				$this->build_class,
				$this->build_method,
				$error
			))
			{
				$this->error($error, 'build_method');
			}
		}
	}

	protected function _postSave()
	{
		if ($this->isUpdate() && $this->isChanged('route_type'))
		{
			$this->rebuildRouteCache($this->getExistingValue('route_type'));
		}

		$this->rebuildRouteCache();
	}

	protected function _postDelete()
	{
		$this->rebuildRouteCache();
	}

	protected function rebuildRouteCache($routeType = null)
	{
		$repo = $this->getRouteRepo();

		if (!$routeType)
		{
			$routeType = $this->route_type;
		}

		\XF::runOnce('routeCacheRebuild' . $routeType, function () use ($repo, $routeType)
		{
			$repo->rebuildRouteCache($routeType);
		});
	}

	protected function _setupDefaults()
	{
		/** @var AddOnRepository $addOnRepo */
		$addOnRepo = $this->_em->getRepository(AddOnRepository::class);
		$this->addon_id = $addOnRepo->getDefaultAddOnId();
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_route';
		$structure->shortName = 'XF:Route';
		$structure->primaryKey = 'route_id';
		$structure->columns = [
			'route_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'route_type' => ['type' => self::BINARY, 'required' => true, 'maxLength' => 25],
			'route_prefix' => ['type' => self::BINARY, 'maxLength' => 50,
				'required' => 'please_enter_valid_route_prefix',
				'match' => ['#^[a-z0-9_-]+$#i', 'please_enter_valid_route_prefix'],
			],
			'sub_name' => ['type' => self::BINARY, 'maxLength' => 50, 'default' => ''],
			'format' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
			'build_class' => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
			'build_method' => ['type' => self::STR, 'maxLength' => 75, 'default' => ''],
			'controller' => ['type' => self::BINARY, 'maxLength' => 255,
				'required' => 'please_enter_valid_route_class',
			],
			'context' => ['type' => self::BINARY, 'maxLength' => 255, 'default' => ''],
			'action_prefix' => ['type' => self::BINARY, 'maxLength' => 255, 'default' => ''],
			'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'default' => ''],
		];
		$structure->behaviors = [
			'XF:DevOutputWritable' => [],
		];
		$structure->getters = [
			'unique_name' => true,
		];
		$structure->relations = [
			'AddOn' => [
				'entity' => 'XF:AddOn',
				'type' => self::TO_ONE,
				'conditions' => 'addon_id',
				'primary' => true,
			],
		];
		$structure->options = [
			'check_duplicate' => true,
		];

		return $structure;
	}

	/**
	 * @return RouteRepository
	 */
	protected function getRouteRepo()
	{
		return $this->repository(RouteRepository::class);
	}
}
