<?php

namespace XF\Service\StyleProperty;

use XF\Entity\StyleProperty;
use XF\Finder\StylePropertyFinder;
use XF\Repository\StylePropertyRepository;
use XF\Repository\StyleRepository;
use XF\Service\AbstractService;
use XF\Style;
use XF\Tree;
use XF\Util\Arr;
use XF\Util\Color;

use function in_array, is_array, is_string, strlen;

class RebuildService extends AbstractService
{
	/**
	 * @var Tree
	 */
	protected $styleTree;

	/**
	 * @var null|array
	 */
	protected $masterStyleProperties;

	protected function setupStyleTree()
	{
		if ($this->styleTree)
		{
			return;
		}

		$this->styleTree = $this->getStyleRepo()->getStyleTree(false);
	}

	public function rebuildFullPropertyMap()
	{
		$this->setupStyleTree();

		$grouped = [];
		$propertyRes = $this->db()->query("
			SELECT property_id, property_name, style_id
			FROM xf_style_property
		");
		while ($property = $propertyRes->fetch())
		{
			$grouped[$property['style_id']][$property['property_name']] = $property['property_id'];
		}

		$this->db()->beginTransaction();
		$this->db()->delete('xf_style_property_map', null); // not using emptyTable for transaction safety
		$this->_rebuildPropertyMap(0, [], $grouped);
		$this->db()->commit();
	}

	public function rebuildPropertyMapForProperty($propertyName)
	{
		$this->setupStyleTree();

		$grouped = [];
		$propertyRes = $this->db()->query("
			SELECT property_id, property_name, style_id
			FROM xf_style_property
			WHERE property_name = ?
		", $propertyName);
		while ($property = $propertyRes->fetch())
		{
			$grouped[$property['style_id']][$property['property_name']] = $property['property_id'];
		}

		$this->db()->beginTransaction();
		$this->db()->delete('xf_style_property_map', 'property_name = ?', $propertyName);
		$this->_rebuildPropertyMap(0, [], $grouped);
		$this->db()->commit();
	}

	protected function _rebuildPropertyMap($styleId, array $map, array $propertyList)
	{
		if (isset($propertyList[$styleId]))
		{
			foreach ($propertyList[$styleId] AS $propertyName => $propertyId)
			{
				if (isset($map[$propertyName]))
				{
					$parentPropertyId = $map[$propertyName]['property_id'];
				}
				else
				{
					$parentPropertyId = null;
				}

				$map[$propertyName] = [
					'property_id' => $propertyId,
					'parent_property_id' => $parentPropertyId,
				];
			}
		}

		$sql = [];
		foreach ($map AS $propertyName => $data)
		{
			$sql[] = [
				'style_id' => $styleId,
				'property_name' => $propertyName,
				'property_id' => $data['property_id'],
				'parent_property_id' => $data['parent_property_id'],
			];
		}
		if ($sql)
		{
			$this->db()->insertBulk('xf_style_property_map', $sql);
		}

		foreach ($this->styleTree->childIds($styleId) AS $childId)
		{
			$this->_rebuildPropertyMap($childId, $map, $propertyList);
		}
	}

	public function rebuildPropertyStyleCache()
	{
		$this->rebuildPropertyStyleCacheForStyle(0);
		$this->getStyleRepo()->updateAllStylesLastModifiedDateLater();
	}

	public function rebuildPropertyStyleCacheForStyle($styleId)
	{
		$this->setupStyleTree();

		$properties = $this->finder(StylePropertyFinder::class)->order(['style_id', 'property_name'])->fetch();
		$byStyle = [];
		foreach ($properties AS $property)
		{
			$byStyle[$property->style_id][$property->property_name] = $property;
		}

		$effectiveProperties = [];

		if ($styleId)
		{
			/** @var \XF\Entity\Style|null $style */
			$style = $this->styleTree->getData($styleId);
			if (!$style)
			{
				// invalid style, nothing to do
				return;
			}

			if ($style->parent_id)
			{
				$baseStyle = $this->styleTree->getData($style->parent_id);
				if ($baseStyle)
				{
					$effectiveProperties = $this->getPropertyRepo()->getEffectivePropertiesInStyle($baseStyle);
				}
			}
			else if (!empty($byStyle[0]))
			{
				$effectiveProperties = $byStyle[0];
			}

			$masterValues = $this->app->registry()->get('masterStyleProperties');
			if ($masterValues)
			{
				$this->masterStyleProperties = $masterValues;
			}
		}
		// when rebuilding from the master, the first thing we'll do is build masterStyleProperties so don't fetch it

		$this->db()->beginTransaction();
		$this->_rebuildPropertyStyleCacheForStyle($styleId, $byStyle, $effectiveProperties);
		$this->db()->commit();
	}

	protected function _rebuildPropertyStyleCacheForStyle($styleId, array $propertiesByStyle, array $effectiveProperties)
	{
		if (isset($propertiesByStyle[$styleId]))
		{
			foreach ($propertiesByStyle[$styleId] AS $property)
			{
				$effectiveProperties[$property->property_name] = $property;
			}
		}

		$values = [];
		foreach ($effectiveProperties AS $name => $property)
		{
			$values[$name] = $this->getPropertyCacheValue($property, $effectiveProperties);
		}

		if ($styleId)
		{
			// if possible, only store values that differ from the master
			if ($this->masterStyleProperties)
			{
				foreach ($values AS $name => $value)
				{
					if (isset($this->masterStyleProperties[$name]) && $value == $this->masterStyleProperties[$name])
					{
						unset($values[$name]);
					}
				}
			}

			/** @var \XF\Entity\Style|null $style */
			$style = $this->styleTree->getData($styleId);
			if ($style)
			{
				$effectiveAssets = $style->effective_assets;
				foreach ($values AS $name => $value)
				{
					$values[$name] = $this->replaceAssetPlaceholders($value, $effectiveAssets);
				}

				$style->properties = $values;
				$style->saveIfChanged($saved, true, false);
			}
		}
		else
		{
			$this->app->registry()->set('masterStyleProperties', $values);
			$this->getStyleRepo()->updateAllStylesLastModifiedDateLater();

			$this->masterStyleProperties = $values;
		}

		foreach ($this->styleTree->childIds($styleId) AS $childId)
		{
			$this->_rebuildPropertyStyleCacheForStyle($childId, $propertiesByStyle, $effectiveProperties);
		}
	}

	public function getMasterPropertiesWithHueShift($hueShift)
	{
		/** @var StyleProperty[] $effectiveProperties */
		$effectiveProperties = $this->finder(StylePropertyFinder::class)
			->where('style_id', 0)
			->order('property_name')
			->keyedBy('property_name')
			->fetch()
			->toArray();

		$shiftableColors = [
			'paletteColor1', 'paletteColor2', 'paletteColor3', 'paletteColor4', 'paletteColor5',
			'paletteAccent1', 'paletteAccent2', 'paletteAccent3',
		];

		$shiftHue = function (string $value, int $hueShift): ?string
		{
			$color = Color::colorToRgb($value);
			if (!$color)
			{
				return null;
			}

			$hsl = Color::rgbToHsl($color);
			$hsl[0] = abs(($hsl[0] + $hueShift) % 360);

			return "hsl({$hsl[0]}, {$hsl[1]}%, {$hsl[2]}%)";
		};

		foreach ($shiftableColors AS $propertyName)
		{
			$property = clone $effectiveProperties[$propertyName];
			$value = $property->property_value;
			$replace = false;

			if ($property->has_variations)
			{
				foreach ($property->getVariations() AS $variation)
				{
					$color = $shiftHue(
						$property->getVariationValue($variation),
						$hueShift
					);
					if ($color)
					{
						$value[$variation] = $color;
						$replace = true;
					}
				}
			}
			else
			{
				$color = $shiftHue($value, $hueShift);
				if ($color)
				{
					$value = $color;
					$replace = true;
				}
			}

			if ($replace)
			{
				$property->property_value = $value;
				$effectiveProperties[$propertyName] = $property;
			}
		}

		$values = [];
		foreach ($effectiveProperties AS $name => $property)
		{
			$values[$name] = $this->getPropertyCacheValue($property, $effectiveProperties);
		}

		return $values;
	}

	public function replaceAssetPlaceholders($value, array $effectiveAssets)
	{
		if (is_string($value))
		{
			$value = preg_replace_callback(
				'/%ASSET:([a-zA-Z0-9_]+)%/',
				function ($match) use ($effectiveAssets)
				{
					$path = $effectiveAssets[$match[1]] ?? '';
					if (strpos($path, 'data://') === 0)
					{
						$dataPath = substr($path, 7); // remove data://
						$path = $this->app->applyExternalDataUrl($dataPath);
					}
					return $path;
				},
				$value
			);
		}
		else if (is_array($value))
		{
			foreach ($value AS &$subValue)
			{
				$subValue = $this->replaceAssetPlaceholders($subValue, $effectiveAssets);
			}
		}

		return $value;
	}

	/**
	 * @param StyleProperty[] $effectiveProperties
	 * @param bool[]                     $seenProperties
	 * @deprecated
	 */
	public function replacePlaceholdersInProperty($value, array $effectiveProperties, array $seenProperties = [])
	{
		return $this->resolvePropertyValue(
			$value,
			$effectiveProperties,
			Style::VARIATION_DEFAULT,
			$seenProperties
		);
	}

	/**
	 * @param StyleProperty[] $effectiveProperties
	 * @param bool[]                     $seenProperties
	 */
	public function resolvePropertyValue(
		$value,
		array $effectiveProperties,
		string $variation,
		array $seenProperties = []
	)
	{
		if (is_string($value))
		{
			$replaceMatch = function ($propertyName, $subName = null) use (
				$effectiveProperties,
				$variation,
				$seenProperties
			)
			{
				$testName = (is_string($subName) && strlen($subName))
					? "{$propertyName}-{$subName}"
					: $propertyName;

				if (isset($seenProperties[$testName]))
				{
					return '';
				}

				if (!isset($effectiveProperties[$propertyName]))
				{
					return '';
				}

				$matchProperty = $effectiveProperties[$propertyName];
				$innerValue = $matchProperty->getVariationValue($variation);

				if (is_array($innerValue))
				{
					if ($subName === null || !isset($innerValue[$subName]))
					{
						return '';
					}

					$innerValue = $innerValue[$subName];
				}

				$seenProperties[$testName] = true;

				return $this->resolvePropertyValue(
					$innerValue,
					$effectiveProperties,
					$variation,
					$seenProperties
				);
			};

			$value = preg_replace_callback(
				'/@xf-([a-z0-9_]+)(?!-[a-z0-9_])(\--([a-z0-9_-]+))?/i',
				function ($match) use ($replaceMatch)
				{
					return $replaceMatch($match[1], $match[3] ?? null);
				},
				$value
			);

			return $value;
		}
		else if (is_array($value))
		{
			foreach ($value AS &$subValue)
			{
				$subValue = $this->resolvePropertyValue(
					$subValue,
					$effectiveProperties,
					$variation,
					$seenProperties
				);
			}

			return $value;
		}
		else
		{
			return $value;
		}
	}

	protected function getPropertyCacheValue(StyleProperty $property, array $effectiveProperties)
	{
		$values = [];
		$variables = [];

		foreach ($this->getStyleRepo()->getVariations() AS $variation)
		{
			$values[$variation] = $this->getPropertyVariationCacheValue(
				$property,
				$effectiveProperties,
				$variation
			);

			if ($property->has_variations)
			{
				$variables[$variation] = $this->resolvePropertyValue(
					$property->getVariationValue($variation),
					$effectiveProperties,
					Style::VARIATION_VARIABLE
				);
			}
		}

		$values[Style::VARIATION_VARIABLE] = $this->getPropertyVariationCacheValue(
			$property,
			$effectiveProperties,
			Style::VARIATION_VARIABLE
		);

		$values = $this->preparePropertyCacheValue(
			$values,
			Style::VARIATION_DEFAULT
		);

		$variables = $this->preparePropertyCacheValue(
			$variables,
			Style::VARIATION_DEFAULT
		);
		if (!empty($variables))
		{
			ksort($variables);
			$values[Style::VARIABLE_KEY] = $variables;
		}

		$values['_type'] = ($property->property_type === 'css')
			? 'css'
			: $property->value_type;

		ksort($values);
		return $values;
	}

	protected function getPropertyVariationCacheValue(
		StyleProperty $property,
		array $effectiveProperties,
		string $variation
	)
	{
		$value = $this->resolvePropertyValue(
			$property->getVariationValue($variation),
			$effectiveProperties,
			$variation
		);

		if ($property->property_type == 'css')
		{
			$value = $this->standardizeLessCacheValue(
				$value,
				$property->css_components
			);
		}

		return $value;
	}

	protected function preparePropertyCacheValue(
		array $values,
		string $defaultKey
	): array
	{
		$defaultValue = $values[$defaultKey] ?? null;
		if ($defaultValue === null)
		{
			return [];
		}

		foreach ($values AS $key => &$value)
		{
			if ($key === $defaultKey)
			{
				continue;
			}

			if (is_array($value))
			{
				$value = Arr::mapDiff($value, $defaultValue);
				if (empty($value))
				{
					unset($values[$key]);
				}
			}
			else
			{
				if ($value === $defaultValue)
				{
					unset($values[$key]);
				}
			}
		}

		return $values;
	}

	public function standardizeLessCacheValue(array $values, array $allowedComponents)
	{
		$remove = [];
		$sides = ['top', 'right', 'bottom', 'left'];

		if (!in_array('text', $allowedComponents))
		{
			$remove[] = 'font-size';
			$remove[] = 'color';
			$remove[] = 'font-weight';
			$remove[] = 'font-style';
			$remove[] = 'text-decoration';
		}

		if (!in_array('background', $allowedComponents))
		{
			$remove[] = 'background-color';
			$remove[] = 'background-image';
		}

		$checkSimpleBorder = true;

		if (!in_array('border', $allowedComponents))
		{
			$remove[] = 'border-width';
			$remove[] = 'border-color';

			foreach ($sides AS $side)
			{
				$remove[] = "border-{$side}-width";
				$remove[] = "border-{$side}-color";
			}
		}
		else
		{
			$checkSimpleBorder = false;
		}

		if (!in_array('border_radius', $allowedComponents))
		{
			$remove[] = 'border-radius';
			$remove[] = 'border-top-left-radius';
			$remove[] = 'border-top-right-radius';
			$remove[] = 'border-bottom-right-radius';
			$remove[] = 'border-bottom-left-radius';
		}
		else
		{
			$checkSimpleBorder = false;
		}

		if ($checkSimpleBorder)
		{
			$restoreSimpleProp = function ($propName) use (&$remove)
			{
				$skipRemove = array_search($propName, $remove);
				if ($skipRemove !== false)
				{
					unset($remove[$skipRemove]);
				}
			};

			if (in_array('border_color_simple', $allowedComponents))
			{
				$restoreSimpleProp('border-color');
			}
			if (in_array('border_width_simple', $allowedComponents))
			{
				$restoreSimpleProp('border-width');
			}
			if (in_array('border_radius_simple', $allowedComponents))
			{
				$restoreSimpleProp('border-radius');
			}
		}

		if (!in_array('padding', $allowedComponents))
		{
			$remove[] = 'padding';

			foreach ($sides AS $side)
			{
				$remove[] = "padding-{$side}";
			}
		}

		if (!in_array('extra', $allowedComponents))
		{
			$remove[] = 'extra';
		}

		foreach ($remove AS $k)
		{
			unset($values[$k]);
		}

		foreach ($values AS $k => &$value)
		{
			$value = trim($value);
			if ($value === '')
			{
				unset($values[$k]);
			}
		}

		if (isset($values['background-image']))
		{
			$values['background-image'] = preg_replace('/^("|\')(.*)\\1$/', '\\2', $values['background-image']);
			if (!preg_match('#^([a-z0-9-]+\(|@|none$)#i', $values['background-image']))
			{
				$values['background-image'] = 'url("' . $values['background-image'] . '")';
			}
		}

		return $values;
	}

	protected function getStyleRepo(): StyleRepository
	{
		return $this->repository(StyleRepository::class);
	}

	protected function getPropertyRepo(): StylePropertyRepository
	{
		return $this->repository(StylePropertyRepository::class);
	}
}
