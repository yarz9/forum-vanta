<?php

namespace XF\DevelopmentOutput;

use XF\Finder\OptionFinder;
use XF\Mvc\Entity\Entity;
use XF\Repository\OptionRepository;
use XF\Util\Json;

use function count;

class Option extends AbstractHandler
{
	protected function getTypeDir()
	{
		return 'options';
	}

	public function export(Entity $option)
	{
		if (!$this->isRelevant($option))
		{
			return true;
		}

		// if the only change is the option value, we don't need to write this
		$newValues = $option->getNewValues();
		if (count($newValues) == 1 && $option->isChanged('option_value'))
		{
			return true;
		}

		$fileName = $this->getFileName($option);

		$keys = [
			'edit_format',
			'edit_format_params',
			'data_type',
			'sub_options',
			'validation_class',
			'validation_method',
			'advanced',
		];
		$json = $this->pullEntityKeys($option, $keys);
		$json['default_value'] = $option->getValue('default_value');

		$relations = [];
		foreach ($option->Relations AS $relation)
		{
			$relations[$relation->group_id] = $relation->display_order;
		}
		$json['relations'] = $relations;

		$this->queueOptionCacheRebuild($option->addon_id);

		return $this->developmentOutput->writeFile($this->getTypeDir(), $option->addon_id, $fileName, Json::jsonEncodePretty($json));
	}

	public function delete(Entity $entity, $new = true)
	{
		$return = parent::delete($entity, $new);
		if ($return)
		{
			$addOnId = $new
				? $entity->getValue('addon_id')
				: $entity->getExistingValue('addon_id');
			$this->queueOptionCacheRebuild($addOnId);
		}

		return $return;
	}

	public function import($name, $addOnId, $contents, array $metadata, array $options = [])
	{
		$json = json_decode($contents, true);

		$option = $this->getEntityForImport($name, $addOnId, $json, $options);
		$option->setOption('verify_validation_callback', false);

		$relations = $json['relations'];
		unset($json['relations']);

		$option->bulkSetIgnore($json);
		$option->option_id = $name;
		$option->addon_id = $addOnId;
		$option->save();
		// this will update the metadata itself

		if (!empty($relations))
		{
			$option->updateRelations($relations);
		}

		$this->queueOptionCacheRebuild($option->addon_id);

		return $option;
	}

	protected function queueOptionCacheRebuild(string $addOnId): void
	{
		\XF::runOnce('optionCacheRebuild-' . $addOnId, function () use ($addOnId)
		{
			$this->rebuildOptionCache($addOnId);
		});
	}

	public function rebuildOptionCache(string $addOnId): void
	{
		$fileName = 'option_hint.php';
		$finalOutput = $this->getOptionCacheFileValue($addOnId);

		if ($finalOutput)
		{
			$this->developmentOutput->writeSpecialFile($addOnId, $fileName, $finalOutput);
		}
		else
		{
			$this->developmentOutput->deleteSpecialFile($addOnId, $fileName);
		}
	}

	public function getOptionCacheFileValue(string $addOnId): ?string
	{
		$optionRepo = \XF::repository(OptionRepository::class);
		$finder = \XF::finder(OptionFinder::class)
			->where('addon_id', $addOnId)
			->order('option_id');

		return $optionRepo->getOptionCacheFileValue($finder);
	}
}
