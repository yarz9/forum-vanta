<?php

namespace XF\AddOn;

use XF\AddOn\DataType\AbstractDataType;
use XF\AddOn\DataType\ActivitySummaryDefinition;
use XF\AddOn\DataType\AdminNavigation;
use XF\AddOn\DataType\AdminPermission;
use XF\AddOn\DataType\AdvertisingPosition;
use XF\AddOn\DataType\ApiScope;
use XF\AddOn\DataType\BbCode;
use XF\AddOn\DataType\BbCodeMediaSite;
use XF\AddOn\DataType\ClassExtension;
use XF\AddOn\DataType\CodeEvent;
use XF\AddOn\DataType\CodeEventListener;
use XF\AddOn\DataType\ContentTypeField;
use XF\AddOn\DataType\CronEntry;
use XF\AddOn\DataType\HelpPage;
use XF\AddOn\DataType\MemberStat;
use XF\AddOn\DataType\Navigation;
use XF\AddOn\DataType\Option;
use XF\AddOn\DataType\OptionGroup;
use XF\AddOn\DataType\Permission;
use XF\AddOn\DataType\PermissionInterfaceGroup;
use XF\AddOn\DataType\Phrase;
use XF\AddOn\DataType\Route;
use XF\AddOn\DataType\StyleProperty;
use XF\AddOn\DataType\StylePropertyGroup;
use XF\AddOn\DataType\Template;
use XF\AddOn\DataType\TemplateModification;
use XF\AddOn\DataType\WidgetDefinition;
use XF\AddOn\DataType\WidgetPosition;
use XF\Finder\AddOnFinder;
use XF\Job\AddOnData;
use XF\Job\AddOnUninstallData;
use XF\Job\Atomic;
use XF\Mvc\Entity\Manager;
use XF\Repository\ClassExtensionRepository;
use XF\Repository\CodeEventListenerRepository;
use XF\Repository\ForumTypeRepository;
use XF\Repository\PaymentRepository;
use XF\Repository\RouteRepository;
use XF\Repository\ThreadTypeRepository;
use XF\Util\File;

class DataManager
{
	/**
	 * @var Manager
	 */
	protected $em;

	/**
	 * @var DataType\AbstractDataType[]|null
	 */
	protected $types;

	public function __construct(Manager $em)
	{
		$this->em = $em;
	}

	public function exportAddOn(AddOn $addOn, array &$containers = [], array &$emptyContainers = [])
	{
		$document = new \DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$root = $document->createElement('addon');
		$document->appendChild($root);

		$addOnId = $addOn->addon_id;

		foreach ($this->getDataTypeHandlers() AS $handler)
		{
			$containerName = $handler->getContainerTag();
			$container = $document->createElement($containerName);
			$handler->exportAddOnData($addOnId, $container);
			$root->appendChild($container);
			$containers[] = $containerName;
		}

		return $document;
	}

	public function enqueueImportAddOnData(AddOn $addOn)
	{
		return \XF::app()->jobManager()->enqueueUnique($this->getImportDataJobId($addOn), AddOnData::class, [
			'addon_id' => $addOn->addon_id,
		]);
	}

	public function getImportDataJobId(AddOn $addOn)
	{
		return 'addOnData-' . $addOn->addon_id;
	}

	protected function checkComposerAutoloadPath(string $file, string $path, bool $allowThrow = true): bool
	{
		$path = rtrim($path, \XF::$DS) . \XF::$DS;

		if (!file_exists($path . $file))
		{
			if ($allowThrow)
			{
				if (\XF::$debugMode)
				{
					throw new \InvalidArgumentException(
						"Missing $file at " . File::stripRootPathPrefix($path) . ". This may not be a valid composer directory."
					);
				}
				else
				{
					\XF::logError(
						'Error registering composer autoload directory: ' . File::stripRootPathPrefix($path . $file)
					);
				}
			}
			return false;
		}

		return true;
	}

	public function rebuildActiveAddOnCache()
	{
		$activeAddOns = [];
		$addOnsComposer = [];

		// cached add-on entities can end up being saved here so clear entity cache
		$this->em->clearEntityCache(\XF\Entity\AddOn::class);

		$addOnManager = \XF::app()->addOnManager();

		$addOns = $this->em->getFinder(AddOnFinder::class)->where('active', 1)->fetch();
		foreach ($addOns AS $addOn)
		{
			$activeAddOns[$addOn->addon_id] = $addOn->version_id;

			$addOnClass = $addOnManager->getById($addOn->addon_id);
			if ($addOnClass)
			{
				$addOnId = $addOn->addon_id;
				$autoloadPath = $addOnClass->composer_autoload;

				if (!$autoloadPath)
				{
					continue;
				}

				$addOnAutoload = $addOnManager->getAddOnPath($addOnId) . \XF::$DS . $autoloadPath;

				if (!$this->checkComposerAutoloadPath('installed.json', $addOnAutoload))
				{
					continue;
				}

				$data = [
					'autoload_path' => $autoloadPath . \XF::$DS,
					'namespaces' => false,
					'psr4' => false,
					'classmap' => false,
					'files' => false,
				];

				$hasData = false;

				if ($this->checkComposerAutoloadPath('autoload_namespaces.php', $addOnAutoload))
				{
					$data['namespaces'] = true;
					$hasData = true;
				}
				if ($this->checkComposerAutoloadPath('autoload_psr4.php', $addOnAutoload))
				{
					$data['psr4'] = true;
					$hasData = true;
				}
				if ($this->checkComposerAutoloadPath('autoload_classmap.php', $addOnAutoload))
				{
					$data['classmap'] = true;
					$hasData = true;
				}
				if ($this->checkComposerAutoloadPath('autoload_files.php', $addOnAutoload, false))
				{
					$data['files'] = true;
					$hasData = true;
				}

				if ($hasData)
				{
					$addOnsComposer[$addOnId] = $data;
				}
			}
		}

		\XF::registry()->set('addOns', $activeAddOns);
		\XF::registry()->set('addOnsComposer', $addOnsComposer);

		return $activeAddOns;
	}

	public function triggerRebuildActiveChange(\XF\Entity\AddOn $addOn)
	{
		$atomicJobs = $this->onActiveChange($addOn);

		$addOnHandler = new AddOn($addOn, \XF::app()->addOnManager());
		$addOnHandler->onActiveChange($addOn->active, $atomicJobs);

		if ($atomicJobs)
		{
			\XF::app()->jobManager()->enqueueUnique(
				'addOnActive' . $addOn->addon_id,
				Atomic::class,
				['execute' => $atomicJobs]
			);
		}
	}

	protected function onActiveChange(\XF\Entity\AddOn $addOn): array
	{
		$atomicJobs = [];

		foreach ($this->getDataTypeHandlers() AS $handler)
		{
			$handler->rebuildActiveChange($addOn, $atomicJobs);
		}

		\XF::runOnce('rebuild_addon_active', function ()
		{
			/** @var ForumTypeRepository $forumTypeRepo */
			$forumTypeRepo = $this->em->getRepository(ForumTypeRepository::class);
			$forumTypeRepo->rebuildForumTypeCache();

			/** @var ThreadTypeRepository $threadTypeRepo */
			$threadTypeRepo = $this->em->getRepository(ThreadTypeRepository::class);
			$threadTypeRepo->rebuildThreadTypeCache();

			/** @var PaymentRepository $paymentRepo */
			$paymentRepo = $this->em->getRepository(PaymentRepository::class);
			$paymentRepo->rebuildPaymentProviderCache();
		});

		return $atomicJobs;
	}

	public function triggerRebuildProcessingChange(\XF\Entity\AddOn $addOn)
	{
		// Note: These rebuilds will not take effect until the next request.

		$this->em->getRepository(ClassExtensionRepository::class)->rebuildExtensionCache();
		$this->em->getRepository(CodeEventListenerRepository::class)->rebuildListenerCache();
		$this->em->getRepository(RouteRepository::class)->rebuildRouteCaches();
	}

	public function updateRelatedIds(\XF\Entity\AddOn $addOn, $oldId)
	{
		if ($oldId == $addOn->addon_id)
		{
			return;
		}

		$newId = $addOn->addon_id;

		$db = $this->em->getDb();
		$db->beginTransaction();

		foreach ($this->getDataTypeHandlers() AS $handler)
		{
			$handler->updateAddOnId($oldId, $newId);
		}

		$db->commit();
	}

	public function enqueueRemoveAddOnData($id)
	{
		return \XF::app()->jobManager()->enqueueUnique($id . 'AddOnUnInstall', AddOnUninstallData::class, [
			'addon_id' => $id,
		]);
	}

	public function finalizeRemoveAddOnData($addOnId)
	{
		$simpleCache = \XF::app()->simpleCache();
		$simpleCache->deleteSet($addOnId);

		$this->rebuildActiveAddOnCache();
	}

	/**
	 * @template T of AbstractDataType
	 *
	 * @param class-string<T> $class
	 *
	 * @return T
	 */
	public function getDataTypeHandler($class)
	{
		$class = \XF::stringToClass($class, '%s\AddOn\DataType\%s');
		$class = \XF::extendClass($class);

		return new $class($this->em);
	}

	/**
	 * @return AbstractDataType[]
	 */
	public function getDataTypeHandlers()
	{
		if ($this->types)
		{
			return $this->types;
		}

		$objects = [];
		foreach ($this->getDataTypeClasses() AS $typeClass)
		{
			$class = \XF::stringToClass($typeClass, '%s\AddOn\DataType\%s');
			$class = \XF::extendClass($class);
			$objects[$typeClass] = new $class($this->em);
		}

		$this->types = $objects;

		return $objects;
	}

	public function getDataTypeClasses()
	{
		return [
			ActivitySummaryDefinition::class,
			AdminNavigation::class,
			AdminPermission::class,
			AdvertisingPosition::class,
			ApiScope::class,
			BbCode::class,
			BbCodeMediaSite::class,
			ClassExtension::class,
			CodeEvent::class,
			CodeEventListener::class,
			ContentTypeField::class,
			CronEntry::class,
			HelpPage::class,
			MemberStat::class,
			Navigation::class,
			Option::class,
			OptionGroup::class,
			Permission::class,
			PermissionInterfaceGroup::class,
			Phrase::class,
			Route::class,
			StyleProperty::class,
			StylePropertyGroup::class,
			Template::class,
			TemplateModification::class,
			WidgetDefinition::class,
			WidgetPosition::class,
		];
	}
}
