<?php

namespace XF\Entity;

use XF\Finder\ClassExtensionFinder;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Repository\AddOnRepository;
use XF\Repository\ClassExtensionRepository;

/**
 * COLUMNS
 * @property int|null $extension_id
 * @property string $from_class
 * @property string $to_class
 * @property int $execute_order
 * @property bool $active
 * @property string $addon_id
 *
 * RELATIONS
 * @property-read AddOn|null $AddOn
 */
class ClassExtension extends Entity
{
	protected function verifyFromClass(&$class)
	{
		$class = trim($class);
		$class = trim($class, '\\');

		if (!$class)
		{
			$this->error(\XF::phrase('invalid_class_x', ['class' => "''"]));
			return false;
		}

		return true;
	}

	protected function verifyToClass(&$class)
	{
		$class = trim($class);
		$class = trim($class, '\\');

		if (!$class || !\XF::$autoLoader->findFile($class))
		{
			$this->error(\XF::phrase('invalid_class_x', ['class' => $class ?: "''"]));
			return false;
		}

		return true;
	}

	protected function _preSave()
	{
		if ($this->isChanged(['from_class', 'to_class']))
		{
			$extension = $this->_em->getFinder(ClassExtensionFinder::class)->where([
				'from_class' => $this->from_class,
				'to_class' => $this->to_class,
			])->fetchOne();
			if ($extension && $extension != $this)
			{
				$this->error(\XF::phrase('class_extensions_must_be_unique'), 'to_class');
			}
		}
	}

	protected function _postSave()
	{
		$this->rebuildExtensionCache();
	}

	protected function _postDelete()
	{
		$this->rebuildExtensionCache();
	}

	protected function rebuildExtensionCache()
	{
		$repo = $this->getExtensionRepo();

		\XF::runOnce('classExtensionRebuild', function () use ($repo)
		{
			$repo->rebuildExtensionCache();
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
		$structure->table = 'xf_class_extension';
		$structure->shortName = 'XF:ClassExtension';
		$structure->primaryKey = 'extension_id';
		$structure->columns = [
			'extension_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'from_class' => ['type' => self::STR, 'maxLength' => 100,
				'required' => 'please_enter_valid_base_class',
			],
			'to_class' => ['type' => self::STR, 'maxLength' => 100,
				'required' => 'please_enter_valid_extension_class',
			],
			'execute_order' => ['type' => self::UINT, 'default' => 10],
			'active' => ['type' => self::BOOL, 'default' => 1],
			'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'default' => ''],
		];
		$structure->behaviors = [
			'XF:DevOutputWritable' => [],
		];
		$structure->getters = [];
		$structure->relations = [
			'AddOn' => [
				'entity' => 'XF:AddOn',
				'type' => self::TO_ONE,
				'conditions' => 'addon_id',
				'primary' => true,
			],
		];
		$structure->options = [];

		return $structure;
	}


	/**
	 * @return ClassExtensionRepository
	 */
	protected function getExtensionRepo()
	{
		return $this->repository(ClassExtensionRepository::class);
	}
}
