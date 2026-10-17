<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Repository\TemplateRepository;

/**
 * COLUMNS
 * @property int|null $property_map_id
 * @property int $style_id
 * @property string $property_name
 * @property int $property_id
 * @property int|null $parent_property_id
 *
 * RELATIONS
 * @property-read Style|null $Style
 * @property-read StyleProperty|null $Property
 * @property-read StyleProperty|null $ParentProperty
 */
class StylePropertyMap extends Entity
{
	public function isDefinitionEditable()
	{
		// if not defined in this style or there's a parent version of the property, don't allow it to be edited
		if ($this->Property->style_id != $this->style_id || $this->parent_property_id)
		{
			return false;
		}

		if (!\XF::$developmentMode)
		{
			return false;
		}

		return true;
	}

	public function isRevertable()
	{
		return $this->getCustomizationState() == 'custom';
	}

	public function getCustomizationState()
	{
		if ($this->style_id == 0)
		{
			return '';
		}

		if ($this->Property->style_id == $this->style_id)
		{
			return $this->parent_property_id ? 'custom' : ($this->Property->style_id == -1 ? '' : 'added');
		}
		else
		{
			return $this->Property->style_id ? 'inherited' : '';
		}
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_style_property_map';
		$structure->shortName = 'XF:StylePropertyMap';
		$structure->primaryKey = 'property_map_id';
		$structure->columns = [
			'property_map_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'style_id' => ['type' => self::UINT, 'required' => true],
			'property_name' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
			'property_id' => ['type' => self::UINT, 'required' => true],
			'parent_property_id' => ['type' => self::UINT, 'nullable' => true],
		];
		$structure->getters = [];
		$structure->relations = [
			'Style' => [
				'type' => self::TO_ONE,
				'entity' => 'XF:Style',
				'conditions' => 'style_id',
				'primary' => true,
			],

			'Property' => [
				'type' => self::TO_ONE,
				'entity' => 'XF:StyleProperty',
				'conditions' => 'property_id',
				'primary' => true,
			],

			'ParentProperty' => [
				'type' => self::TO_ONE,
				'entity' => 'XF:StyleProperty',
				'conditions' => [['property_id', '=', '$parent_property_id']],
				'primary' => true,
			],
		];
		$structure->defaultWith = ['Property'];

		return $structure;
	}

	/**
	 * @return TemplateRepository
	 */
	protected function getTemplateRepo()
	{
		return $this->repository(TemplateRepository::class);
	}
}
