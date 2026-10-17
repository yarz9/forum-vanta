<?php

namespace XF\DevelopmentOutput;

use XF\Finder\StylePropertyFinder;
use XF\Mvc\Entity\Entity;
use XF\Service\StyleProperty\RebuildService;
use XF\Style;
use XF\Util\Json;

use function is_array, is_bool, is_scalar, strlen;

class StyleProperty extends AbstractHandler
{
	protected function getTypeDir()
	{
		return 'style_properties';
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

	public function export(Entity $property)
	{
		if (!$this->isRelevant($property))
		{
			return true;
		}

		$fileName = $this->getFileName($property);

		$json = $this->getJsonStructure($property);

		$this->queuePropertyLessCacheRebuild($property->addon_id);

		return $this->developmentOutput->writeFile(
			$this->getTypeDir(),
			$property->addon_id,
			$fileName,
			Json::jsonEncodePretty($json)
		);
	}

	public function delete(Entity $entity, $new = true)
	{
		$return = parent::delete($entity, $new);
		if ($return)
		{
			$addOnId = $new ? $entity->getValue('addon_id') : $entity->getExistingValue('addon_id');
			$this->queuePropertyLessCacheRebuild($addOnId);
		}

		return $return;
	}

	public function import($name, $addOnId, $contents, array $metadata, array $options = [])
	{
		$json = $this->decodeJson($contents);

		/** @var \XF\Entity\StyleProperty $property */
		$property = $this->getEntityForImport($name, $addOnId, $json, $options);
		$property->setOption('update_phrase', false);

		$property->bulkSetIgnore($json);
		$property->property_name = $name;
		$property->style_id = 0;
		$property->addon_id = $addOnId;
		$property->save();
		// this will update the metadata itself

		return $property;
	}

	protected function getEntityForImport($name, $addOnId, $json, array $options)
	{
		/** @var \XF\Entity\StyleProperty $property */
		$property = \XF::em()->getFinder(StylePropertyFinder::class)->where([
			'property_name' => $name,
			'style_id' => 0,
		])->fetchOne();
		if (!$property)
		{
			$property = \XF::em()->create(\XF\Entity\StyleProperty::class);
		}

		$property = $this->prepareEntityForImport($property, $options);

		return $property;
	}

	protected function getFileName(Entity $entity, $new = true)
	{
		$id = $new ? $entity->getValue('property_name') : $entity->getExistingValue('property_name');
		return "{$id}.json";
	}

	protected function isRelevant(Entity $entity, $new = true)
	{
		$styleId = $new ? $entity->getValue('style_id') : $entity->getExistingValue('style_id');
		return parent::isRelevant($entity, $new) && !$styleId;
	}

	protected function queuePropertyLessCacheRebuild($addOnId)
	{
		\XF::runOnce('stylePropLessCacheRebuild-' . $addOnId, function () use ($addOnId)
		{
			$this->rebuildPropertyLessCache($addOnId);
		});
	}

	public function rebuildPropertyLessCache($addOnId)
	{
		$fileName = 'style_properties.less';
		$finalOutput = $this->getLessCacheFileValue($addOnId);

		if ($finalOutput)
		{
			$this->developmentOutput->writeSpecialFile($addOnId, $fileName, $finalOutput);
		}
		else
		{
			$this->developmentOutput->deleteSpecialFile($addOnId, $fileName);
		}
	}

	public function getLessCacheFileValue($addOnId)
	{
		$finder = \XF::finder(StylePropertyFinder::class)
			->where([
				'addon_id' => $addOnId,
				'style_id' => 0,
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

		$value = $this->getScalarCacheValueOutput($value);
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

				$subValue = $this->getScalarCacheValueOutput($subValue);
				$valueOutput[] = "@{$prefix}{$name}--{$subKey}: {$subValue};";
			}
		}

		/** @var Style $style */
		$style = \XF::app()->get('style.fallback');
		$value = $style->compileCssPropertyValue($value);

		return ".{$prefix}{$name}()\n{\n{$value}\n}";
	}

	protected function getScalarCacheValueOutput($value)
	{
		if (is_bool($value))
		{
			return $value ? 'true' : 'false';
		}

		$value = trim($value);

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
}
