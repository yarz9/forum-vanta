<?php

namespace XF\DesignerOutput;

use XF\Finder\StylePropertyFinder;
use XF\Mvc\Entity\Entity;
use XF\Repository\StylePropertyRepository;
use XF\Repository\StyleRepository;
use XF\Service\StyleProperty\RebuildService;
use XF\Style;
use XF\Util\Json;

use function is_array, is_bool, is_scalar, strlen;

class StyleProperty extends AbstractHandler
{
	/**
	 * @var array<int, array<string, \XF\Entity\StyleProperty|null>>
	 */
	protected $parentPropertyMap = [];

	protected function getTypeDir()
	{
		return 'style_properties';
	}

	/**
	 * @param \XF\Entity\StyleProperty $property
	 *
	 * @return bool
	 */
	public function export(Entity $property)
	{
		$fileName = $this->getFileName($property);

		$json = $this->getJsonStructure($property);

		$this->queuePropertyLessCacheRebuild($property->Style);

		return $this->designerOutput->writeFile(
			$this->getTypeDir(),
			$property->Style,
			$fileName,
			Json::jsonEncodePretty($json)
		);
	}

	protected function getJsonStructure(\XF\Entity\StyleProperty $property): array
	{
		return [
			'group_name' => $property->group_name,
			'title' => $property->getValue('title'),
			'description' => $property->getValue('description'),
			'property_type' => $property->property_type,
			'css_components' => $property->css_components,
			'value_type' => $property->value_type,
			'value_parameters' => $property->value_parameters,
			'has_variations' => $property->has_variations,
			'depends_on' => $property->depends_on,
			'value_group' => $property->value_group,
			'property_value' => $property->property_value,
			'display_order' => $property->display_order,
		];
	}

	protected function decodeJson(string $jsonString): array
	{
		/** @var \XF\Entity\StyleProperty $property */
		$property = \XF::em()->create($this->shortName);

		$json = json_decode($jsonString, true);

		foreach (array_keys($this->getJsonStructure($property)) AS $column)
		{
			if (!isset($json[$column]))
			{
				$json[$column] = $property->get($column);
			}
		}

		return $json;
	}

	protected function getEntityForImport($name, $styleId, $json, array $options)
	{
		/** @var \XF\Entity\StyleProperty $property */
		$property = \XF::em()->getFinder(StylePropertyFinder::class)->where([
			'property_name' => $name,
			'style_id' => $styleId,
		])->fetchOne();

		if (!$property)
		{
			$style = $this->getStyle($styleId);
			$parentProperty = $style
				? $this->getParentProperty($style, $name)
				: null;
			$property = $parentProperty
				? $parentProperty->getPropertyCopyInStyle($style)
				: null;
		}

		if (!$property)
		{
			$property = \XF::em()->create(\XF\Entity\StyleProperty::class);
			$property->property_name = $name;
			$property->style_id = $styleId;
		}

		$property = $this->prepareEntityForImport($property, $options);

		return $property;
	}

	public function import($name, $styleId, $contents, array $metadata, array $options = [])
	{
		$json = $this->decodeJson($contents);

		/** @var \XF\Entity\StyleProperty $property */
		$property = $this->getEntityForImport($name, $styleId, $json, $options);
		$property->setOption('update_phrase', false);

		$style = $this->getStyle($styleId);
		$parentProperty = $style
			? $this->getParentProperty($style, $name)
			: null;

		$value = $json['property_value'];
		unset($json['property_value']);

		if (!$parentProperty)
		{
			$property->bulkSet($json);
		}

		$this->setPropertyValue($property, $value, $json['has_variations']);

		$property->save();
		// this will update the metadata itself

		return $property;
	}

	protected function getStyle(int $styleId): ?\XF\Entity\Style
	{
		return \XF::app()->find(\XF\Entity\Style::class, $styleId);
	}

	protected function getParentProperty(
		\XF\Entity\Style $style,
		string $name
	): ?\XF\Entity\StyleProperty
	{
		if (!isset($this->parentPropertyMap[$style->style_id]))
		{
			if ($style->parent_id)
			{
				$parent = $style->Parent;
			}
			else
			{
				$styleRepo = \XF::repository(StyleRepository::class);
				$parent = $styleRepo->getMasterStyle();
			}

			$propertyRepo = \XF::repository(StylePropertyRepository::class);
			$parentProperties = $propertyRepo->getEffectivePropertiesInStyle($parent);
			$this->parentPropertyMap[$style->style_id] = $parentProperties;
		}

		return $this->parentPropertyMap[$style->style_id][$name] ?? null;
	}

	/**
	 * @param string|array $value
	 */
	protected function setPropertyValue(
		\XF\Entity\StyleProperty $property,
		$value,
		bool $valueHasVariations
	): void
	{
		if ($property->property_type === 'value')
		{
			if ($property->has_variations && !$valueHasVariations)
			{
				$value = [Style::VARIATION_DEFAULT => $value];
			}
			else if (!$property->has_variations && $valueHasVariations)
			{
				$value = $value[Style::VARIATION_DEFAULT] ?? '';
			}
		}

		$property->property_value = $value;
	}

	protected function getFileName(Entity $entity, $new = true)
	{
		$id = $new ? $entity->getValue('property_name') : $entity->getExistingValue('property_name');
		return "{$id}.json";
	}

	protected function queuePropertyLessCacheRebuild(\XF\Entity\Style $style)
	{
		\XF::runOnce('stylePropLessCacheRebuild-' . $style->designer_mode, function () use ($style)
		{
			$this->rebuildPropertyLessCache($style);
		});
	}

	public function rebuildPropertyLessCache(\XF\Entity\Style $style)
	{
		$fileName = 'style_properties.less';
		$finalOutput = $this->getLessCacheFileValue($style->designer_mode);

		if ($finalOutput)
		{
			$this->designerOutput->writeSpecialFile($style->designer_mode, $fileName, $finalOutput);
		}
		else
		{
			$this->designerOutput->deleteSpecialFile($style->designer_mode, $fileName);
		}
	}

	public function getLessCacheFileValue($designerMode)
	{
		$finder = \XF::finder(StylePropertyFinder::class)
			->with('Style', true)
			->where([
				'Style.designer_mode' => $designerMode,
			])
			->order('property_name');
		$properties = $finder->fetch();

		$value = [];
		$css = [];
		$cssValue = [];

		$prefix = 'xf-';

		foreach ($properties AS $property)
		{
			if ($property->property_type == 'css')
			{
				$output = $this->compileLessCacheCssProperty($property, $prefix, $cssValue);
				if ($output)
				{
					$css[] = $output;
				}
			}
			else
			{
				$output = $this->compileLessCacheValueProperty($property, $prefix);
				if ($output)
				{
					$value[] = $output;
				}
			}
		}

		if (!$value && !$css)
		{
			return false;
		}

		$definitions = trim(
			implode("\n", $value)
			. "\n\n"
			. implode("\n\n", $css)
			. "\n\n"
			. implode("\n", $cssValue)
		);

		return <<<EOF
// ################## THIS IS A GENERATED FILE ##################
// DO NOT EDIT DIRECTLY. EDIT THE STYLE PROPERTIES IN THE CONTROL PANEL.

{$definitions}

EOF;
	}

	protected function compileLessCacheValueProperty(\XF\Entity\StyleProperty $property, $prefix)
	{
		$value = $property->getVariationValue(Style::VARIATION_DEFAULT);
		if (!is_scalar($value))
		{
			return '';
		}

		$value = $this->getScalarCacheValueOutput($value, $property);
		return "@{$prefix}{$property->property_name}: {$value};";
	}

	protected function compileLessCacheCssProperty(\XF\Entity\StyleProperty $property, $prefix, ?array &$valueOutput = null)
	{
		$name = $property->property_name;

		$propertyRebuilder = \XF::service(RebuildService::class);
		$value = $propertyRebuilder->standardizeLessCacheValue($property->property_value, $property->css_components);

		if (is_array($valueOutput))
		{
			foreach ($value AS $subKey => $subValue)
			{
				if ($subKey == 'extra')
				{
					continue;
				}

				$subValue = $this->getScalarCacheValueOutput($subValue, $property);
				$valueOutput[] = "@{$prefix}{$name}--{$subKey}: {$subValue};";
			}
		}

		/** @var Style $style */
		$style = \XF::app()->get('style.fallback');
		$value = $style->compileCssPropertyValue($value);

		return ".{$prefix}{$name}()\n{\n{$value}\n}";
	}

	protected function getScalarCacheValueOutput($value, ?\XF\Entity\StyleProperty $property = null)
	{
		if (is_bool($value))
		{
			return $value ? 'true' : 'false';
		}

		$value = trim($value);

		if ($property && preg_match('/%ASSET:([a-zA-Z0-9_]+)%/', $value, $match))
		{
			$effectiveAssets = $property->Style->effective_assets;
			$value = $effectiveAssets[$match[1]] ?? $value;
			if (strpos($value, 'data://') === 0)
			{
				$dataPath = substr($value, 7); // remove data://
				$value = \XF::app()->applyExternalDataUrl($dataPath);
			}
		}

		if (!strlen($value))
		{
			return "~''";
		}

		if (preg_match('#/[a-z0-9_.:-]*/#i', $value))
		{
			return "~'{$value}'";
		}

		if (preg_match('#[?]#', $value))
		{
			return "~'{$value}'";
		}

		return $value;
	}

	public function convertTemplateNameToFile($type, $name)
	{
		if (!strpos($name, '.'))
		{
			$name = "$name.html";
		}

		return $type . '/' . $name;
	}
}
