<?php

namespace XF\Service\Style;

use XF\Behavior\DevOutputWritable;
use XF\Entity\Style;
use XF\Entity\StyleProperty;
use XF\Entity\StylePropertyGroup;
use XF\Entity\Template;
use XF\Finder\StylePropertyFinder;
use XF\Finder\StylePropertyGroupFinder;
use XF\Finder\TemplateFinder;
use XF\Repository\StylePropertyRepository;
use XF\Repository\StyleRepository;
use XF\Service\AbstractService;
use XF\Util\File;
use XF\Util\Xml;

use function count;

class ImportService extends AbstractService
{
	/**
	 * @var Style|null
	 */
	protected $overwriteStyle;

	/**
	 * @var Style|null
	 */
	protected $parentStyle;

	/**
	 * @var ArchiveImportService
	 */
	protected $archiveImporter;

	public function setArchiveImporter(ArchiveImportService $archiveImporter)
	{
		$this->archiveImporter = $archiveImporter;
	}

	public function setOverwriteStyle(Style $style)
	{
		$this->overwriteStyle = $style;
		$this->parentStyle = null;
	}

	public function getOverwriteStyle()
	{
		return $this->overwriteStyle;
	}

	public function setParentStyle(?Style $style = null)
	{
		$this->parentStyle = $style;
		$this->overwriteStyle = null;
	}

	public function getParentStyle()
	{
		return $this->parentStyle;
	}

	public function isValidXml($rootElement, &$error = null)
	{
		if (!($rootElement instanceof \SimpleXMLElement))
		{
			$error = \XF::phrase('please_upload_valid_style_xml_file');
			return false;
		}

		if ($rootElement->getName() != 'style' || (string) $rootElement['title'] === '')
		{
			$error = \XF::phrase('please_upload_valid_style_xml_file');
			return false;
		}

		if ((string) $rootElement['export_version'] != (string) ExportService::EXPORT_VERSION_ID)
		{
			$error = \XF::phrase('this_style_xml_file_was_not_built_for_this_version_of_xenforo');
			return false;
		}

		return true;
	}

	public function isValidConfiguration(\SimpleXMLElement $document, &$errors = null)
	{
		$errors = [];

		$addOnId = (string) $document['addon_id'];

		if ($addOnId && $addOnId != 'XF')
		{
			$addOn = $this->app->addOnManager()->getById($addOnId);
			if (!$addOn)
			{
				$errors['addon_id'] = \XF::phrase('xml_file_relates_add_on_not_installed_install_first');
				$expectedVersionId = null;
			}
			else
			{
				$expectedVersionId = $addOn->version_id;
			}
		}
		else
		{
			$expectedVersionId = \XF::$versionId;
		}

		$baseVersionId = (int) $document['base_version_id'];

		if ($expectedVersionId && $baseVersionId)
		{
			if ($baseVersionId > $expectedVersionId)
			{
				$errors['version_id'] = \XF::phrase('xml_file_based_on_newer_version_than_installed');
			}
		}

		if ($this->overwriteStyle)
		{
			$title = (string) $document['title'];
			if ($title != $this->overwriteStyle->title)
			{
				$errors['title'] = \XF::phrase('title_of_style_importing_differs_overwriting_is_correct');
			}
		}

		return (count($errors) == 0);
	}

	/**
	 * Returns whether the asset paths associated with this style XML are writable. Only apples to paths
	 * that refer to areas within the XF root (and not the data:// paths). This is primarily called when
	 * importing a style archive into its original path.
	 *
	 * @param \SimpleXMLElement $document Style XML root
	 * @param array $failed List of paths that were unwritable
	 *
	 * @return bool
	 */
	public function validateAssetPathsWritable(\SimpleXMLElement $document, &$failed = []): bool
	{
		$failed = [];

		$assets = $this->getAssetValues($document->assets);
		foreach ($assets AS $path)
		{
			if (!$path || preg_match('#^(https?://|data://|/|\\\\)#i', $path))
			{
				continue;
			}

			$fullPath = \XF::getRootDirectory() . '/' . $path;
			if (!File::isWritable($fullPath))
			{
				$failed[] = $path;
			}
		}

		return count($failed) == 0;
	}

	public function importFromXml(\SimpleXMLElement $document)
	{
		$db = $this->db();
		$db->beginTransaction();

		$addOnId = (string) $document['addon_id'];

		$style = $this->getTargetStyle($document);

		$this->importAssets($style, $document->assets, $addOnId);
		$this->importPropertyGroups($style, $document->properties, $addOnId);
		$this->importProperties($style, $document->properties, $addOnId);
		$this->importTemplates($style, $document->templates, $addOnId);

		/** @var StyleRepository $styleRepo */
		$styleRepo = $this->repository(StyleRepository::class);
		$styleRepo->triggerStyleDataRebuild();

		$db->commit();

		return $style;
	}

	public function importAssets(Style $style, \SimpleXMLElement $container, $addOnId)
	{
		$assets = $this->getAssetValues($container);

		$archiveImporter = $this->archiveImporter;
		if ($archiveImporter)
		{
			$assets = $archiveImporter->copyAssetFiles($style, $assets);
		}

		$style->assets = $assets;
		$style->save(true, false);
	}

	protected function getAssetValues(\SimpleXMLElement $container): array
	{
		$assets = [];

		if (!$container->asset)
		{
			return $assets;
		}

		foreach ($container->asset AS $xmlAsset)
		{
			$key = (string) $xmlAsset['key'];
			$path = (string) $xmlAsset['path'];

			$path = str_replace('\\', '/', $path);
			if (strpos($path, '../') !== false)
			{
				continue;
			}

			$assets[$key] = $path;
		}

		return $assets;
	}

	public function importTemplates(Style $style, \SimpleXMLElement $container, $addOnId)
	{
		$styleId = $style->style_id;
		$existingTemplates = $this->getExistingTemplates($style);

		foreach ($container->template AS $xmlTemplate)
		{
			$title = (string) $xmlTemplate['title'];
			$type = (string) $xmlTemplate['type'];
			$key = "$type-$title";

			$template = $existingTemplates[$key] ?? $this->em()->create(Template::class);

			$template->title = $title;
			$template->style_id = $styleId;
			$template->type = $type;
			$this->setupTemplateImport($template, $xmlTemplate);

			$template->save(true, false);

			unset($existingTemplates[$key]);
		}

		// removed templates
		foreach ($existingTemplates AS $existingTemplate)
		{
			if ($addOnId && $existingTemplate->addon_id !== $addOnId)
			{
				// wouldn't be covered so leave it
				continue;
			}

			$this->setTemplateOptions($existingTemplate);
			$existingTemplate->delete(true, false);
		}
	}

	/**
	 * @param Style $style
	 *
	 * @return Template[]
	 */
	protected function getExistingTemplates(Style $style)
	{
		/** @var TemplateFinder $templateFinder */
		$templateFinder = $this->finder(TemplateFinder::class);
		$templateFinder->where('style_id', $style->style_id)
			->orderTitle();

		$output = [];
		foreach ($templateFinder->fetch() AS $template)
		{
			$output["{$template->type}-{$template->title}"] = $template;
		}

		return $output;
	}

	protected function setupTemplateImport(Template $template, \SimpleXMLElement $xmlTemplate)
	{
		$this->setTemplateOptions($template);

		$template->template = Xml::processSimpleXmlCdata($xmlTemplate);
		$template->addon_id = (string) $xmlTemplate['addon_id'];
		$template->version_id = (int) $xmlTemplate['version_id'];
		$template->version_string = (string) $xmlTemplate['version_string'];
	}

	protected function setTemplateOptions(Template $template)
	{
		$template->setOption('recompile', false);
		$template->setOption('test_compile', false);
		$template->setOption('rebuild_map', false);
		$template->setOption('check_duplicate', false);

		$template->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', false);
	}

	public function importPropertyGroups(Style $style, \SimpleXMLElement $container, $addOnId)
	{
		$styleId = $style->style_id;
		$existingGroups = $this->getExistingGroups($style);

		foreach ($container->group AS $xmlGroup)
		{
			$groupName = (string) $xmlGroup['group_name'];

			$group = $existingGroups[$groupName] ?? $this->em()->create(StylePropertyGroup::class);

			$group->group_name = $groupName;
			$group->style_id = $styleId;
			$this->setupPropertyGroupImport($group, $xmlGroup);

			$group->save(true, false);

			unset($existingGroups[$groupName]);
		}

		// removed groups
		foreach ($existingGroups AS $existingGroup)
		{
			if ($addOnId && $existingGroup->addon_id !== $addOnId)
			{
				// wouldn't be covered so leave it
				continue;
			}

			$existingGroup->delete(true, false);
		}
	}

	protected function setupPropertyGroupImport(StylePropertyGroup $group, \SimpleXMLElement $xmlGroup)
	{
		$group->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', false);

		$group->title = (string) $xmlGroup['title'];
		$group->description = (string) $xmlGroup['description'];
		$group->display_order = (int) $xmlGroup['display_order'];
		$group->addon_id = (string) $xmlGroup['addon_id'];
	}

	/**
	 * @param Style $style
	 *
	 * @return StylePropertyGroup[]
	 */
	protected function getExistingGroups(Style $style)
	{
		$finder = $this->finder(StylePropertyGroupFinder::class)
			->where('style_id', $style->style_id)
			->keyedBy('group_name');

		return $finder->fetch();
	}

	public function importProperties(Style $style, \SimpleXMLElement $container, $addOnId)
	{
		$styleId = $style->style_id;
		$existingProperties = $this->getExistingProperties($style);
		$parentProperties = $this->getParentProperties($style);

		foreach ($container->property AS $xmlProperty)
		{
			$propertyName = (string) $xmlProperty['property_name'];

			if ($existingProperties[$propertyName] ?? null)
			{
				$property = $existingProperties[$propertyName];
			}
			else if ($parentProperties[$propertyName] ?? null)
			{
				$parentProperty = $parentProperties[$propertyName];
				$property = $parentProperty->getPropertyCopyInStyle($style);
			}
			else
			{
				$property = $this->em()->create(StyleProperty::class);
				$property->property_name = $propertyName;
				$property->style_id = $styleId;
			}

			if ($parentProperties[$propertyName] ?? null)
			{
				$this->setPropertyOptions($property);

				$value = json_decode((string) $xmlProperty->value, true);
				$valueHasVariations = (bool) (int) $xmlProperty['has_variations'];
				$this->setPropertyValue($property, $value, $valueHasVariations);
			}
			else
			{
				$this->setupPropertyImport($property, $xmlProperty);
			}

			$property->save(true, false);

			unset($existingProperties[$propertyName]);
		}

		// removed properties
		foreach ($existingProperties AS $existingProperty)
		{
			if ($addOnId && $existingProperty->addon_id !== $addOnId)
			{
				// wouldn't be covered so leave it
				continue;
			}

			$this->setPropertyOptions($existingProperty);
			$existingProperty->delete(false, false);
		}
	}

	protected function setupPropertyImport(StyleProperty $property, \SimpleXMLElement $xmlProperty)
	{
		$this->setPropertyOptions($property);

		$property->group_name = (string) $xmlProperty['group_name'];
		$property->title = (string) $xmlProperty['title'];
		$property->description = (string) $xmlProperty['description'];
		$property->property_type = (string) $xmlProperty['property_type'];
		$property->value_type = (string) $xmlProperty['value_type'];
		$property->has_variations = (bool) (int) $xmlProperty['has_variations'];
		$property->depends_on = (string) $xmlProperty['depends_on'];
		$property->value_group = (string) $xmlProperty['value_group'];
		$property->display_order = (int) $xmlProperty['display_order'];
		$property->addon_id = (string) $xmlProperty['addon_id'];

		$cssComponents = (string) $xmlProperty['css_components'];
		$property->css_components = $cssComponents ? explode(',', $cssComponents) : [];

		$property->value_parameters = (string) $xmlProperty->value_parameters;

		$value = json_decode((string) $xmlProperty->value, true);
		$valueHasVariations = (bool) (int) $xmlProperty['has_variations'];
		$this->setPropertyValue($property, $value, $valueHasVariations);
	}

	protected function setPropertyOptions(StyleProperty $property)
	{
		$property->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', false);

		$property->setOption('rebuild_map', false);
		$property->setOption('rebuild_style', false);
	}

	/**
	 * @param string|array $value
	 */
	protected function setPropertyValue(
		StyleProperty $property,
		$value,
		bool $valueHasVariations
	): void
	{
		if ($property->property_type === 'value')
		{
			if ($property->has_variations && !$valueHasVariations)
			{
				$value = [\XF\Style::VARIATION_DEFAULT => $value];
			}
			else if (!$property->has_variations && $valueHasVariations)
			{
				$value = $value[\XF\Style::VARIATION_DEFAULT] ?? '';
			}
		}

		$property->property_value = $value;
	}

	/**
	 * @param Style $style
	 *
	 * @return StyleProperty[]
	 */
	protected function getExistingProperties(Style $style)
	{
		$finder = $this->finder(StylePropertyFinder::class)
			->where('style_id', $style->style_id)
			->keyedBy('property_name');

		return $finder->fetch();
	}

	/**
	 * @return array<string, StyleProperty>
	 */
	protected function getParentProperties(Style $style): array
	{
		if ($style->parent_id)
		{
			$parent = $style->Parent;
		}
		else
		{
			$styleRepo = $this->repository(StyleRepository::class);
			$parent = $styleRepo->getMasterStyle();
		}

		$propertyRepo = $this->repository(StylePropertyRepository::class);
		return $propertyRepo->getEffectivePropertiesInStyle($parent);
	}

	protected function getTargetStyle(\SimpleXMLElement $document)
	{
		if ($this->overwriteStyle)
		{
			return $this->overwriteStyle;
		}
		else
		{
			$style = $this->em()->create(Style::class);
			$style->title = (string) $document['title'];
			$style->description = (string) $document['description'];
			$style->parent_id = $this->parentStyle ? $this->parentStyle->style_id : 0;
			$style->enable_variations = (string) $document['enable_variations'];
			$style->user_selectable = (string) $document['user_selectable'];

			$style->save(true, false);

			return $style;
		}
	}
}
