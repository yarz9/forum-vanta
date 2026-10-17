<?php

namespace XF\AddOn\DataType;

use XF\Behavior\DevOutputWritable;
use XF\Entity\AddOn;
use XF\Entity\Option;
use XF\Repository\WidgetRepository;

class WidgetDefinition extends AbstractDataType
{
	public function getShortName()
	{
		return 'XF:WidgetDefinition';
	}

	public function getContainerTag()
	{
		return 'widget_definitions';
	}

	public function getChildTag()
	{
		return 'widget_definition';
	}

	public function exportAddOnData($addOnId, \DOMElement $container)
	{
		$entries = $this->finder()
			->where('addon_id', $addOnId)
			->order('definition_id')->fetch();

		$doc = $container->ownerDocument;

		foreach ($entries AS $entry)
		{
			$node = $doc->createElement($this->getChildTag());

			$this->exportMappedAttributes($node, $entry);

			$container->appendChild($node);
		}

		return $entries->count() ? true : false;
	}

	public function importAddOnData($addOnId, \SimpleXMLElement $container, $start = 0, $maxRunTime = 0)
	{
		$startTime = microtime(true);

		$entries = $this->getEntries($container, $start);
		if (!$entries)
		{
			return false;
		}

		$ids = $this->pluckXmlAttribute($entries, 'definition_id');
		$existing = $this->findByIds($ids);

		$i = 0;
		$last = 0;
		foreach ($entries AS $entry)
		{
			$id = $ids[$i++];

			if ($i <= $start)
			{
				continue;
			}

			/** @var Option $entity */
			$entity = $existing[$id] ?? $this->create();

			$entity->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', false);
			$this->importMappedAttributes($entry, $entity);

			$entity->addon_id = $addOnId;

			$entity->save(true, false);

			if ($this->resume($maxRunTime, $startTime))
			{
				$last = $i;
				break;
			}
		}
		return ($last ?: false);
	}

	public function deleteOrphanedAddOnData($addOnId, \SimpleXMLElement $container)
	{
		$this->deleteOrphanedSimple($addOnId, $container, 'definition_id');
	}

	public function rebuildActiveChange(AddOn $addOn, array &$jobList)
	{
		\XF::runOnce('rebuild_active_' . $this->getContainerTag(), function ()
		{
			/** @var WidgetRepository $repo */
			$repo = $this->em->getRepository(WidgetRepository::class);
			$repo->rebuildWidgetDefinitionCache();
		});
	}

	protected function getMappedAttributes()
	{
		return [
			'definition_id',
			'definition_class',
		];
	}
}
