<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Purchasable\AbstractPurchasable;
use XF\Repository\AddOnRepository;

/**
 * COLUMNS
 * @property string $purchasable_type_id
 * @property string $purchasable_class
 * @property string $addon_id
 *
 * GETTERS
 * @property-read mixed|string $title
 * @property-read AbstractPurchasable|null $handler
 *
 * RELATIONS
 * @property-read AddOn|null $AddOn
 */
class Purchasable extends Entity
{
	public function isActive()
	{
		return ($this->AddOn ? $this->AddOn->active : false);
	}

	public function isCancelled(PurchaseRequest $purchaseRequest): bool
	{
		$handler = $this->getHandler();

		if (!$handler)
		{
			return false;
		}

		return $handler->isCancelled($purchaseRequest);
	}

	/**
	 * @return mixed|string
	 */
	public function getTitle()
	{
		$handler = $this->handler;
		return $handler ? $handler->getTitle() : '';
	}

	/**
	 * @return AbstractPurchasable|null
	 */
	public function getHandler()
	{
		$class = \XF::stringToClass($this->purchasable_class, '%s\Purchasable\%s');
		if (!class_exists($class))
		{
			return null;
		}

		$class = \XF::extendClass($class);
		return new $class($this->purchasable_type_id);
	}

	protected function _setupDefaults()
	{
		/** @var AddOnRepository $addOnRepo */
		$addOnRepo = $this->_em->getRepository(AddOnRepository::class);
		$this->addon_id = $addOnRepo->getDefaultAddOnId();
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_purchasable';
		$structure->shortName = 'XF:Purchasable';
		$structure->primaryKey = 'purchasable_type_id';
		$structure->columns = [
			'purchasable_type_id' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
			'purchasable_class' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
			'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'default' => ''],
		];
		$structure->getters = [
			'title' => false,
			'handler' => true,
		];
		$structure->relations = [
			'AddOn' => [
				'entity' => 'XF:AddOn',
				'type' => self::TO_ONE,
				'conditions' => 'addon_id',
				'primary' => true,
			],
		];

		return $structure;
	}
}
