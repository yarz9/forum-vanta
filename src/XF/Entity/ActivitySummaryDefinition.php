<?php

namespace XF\Entity;

use XF\Behavior\DevOutputWritable;
use XF\Finder\ActivitySummarySectionFinder;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\ActivitySummaryRepository;
use XF\Repository\AddOnRepository;

/**
 * COLUMNS
 * @property string $definition_id
 * @property string $definition_class
 * @property string $addon_id
 *
 * GETTERS
 * @property-read Phrase $title
 * @property-read Phrase $description
 *
 * RELATIONS
 * @property-read AddOn|null $AddOn
 * @property-read \XF\Entity\Phrase|null $MasterTitle
 * @property-read \XF\Entity\Phrase|null $MasterDescription
 */
class ActivitySummaryDefinition extends Entity
{
	public function isActive()
	{
		$addOn = $this->AddOn;
		return $addOn ? $addOn->active : false;
	}

	public function getTitlePhraseName()
	{
		return 'activity_summary_def.' . $this->definition_id;
	}

	public function getDescriptionPhraseName()
	{
		return 'activity_summary_def_desc.' . $this->definition_id;
	}

	/**
	 * @return Phrase
	 */
	public function getTitle()
	{
		return \XF::phrase($this->getTitlePhraseName());
	}

	/**
	 * @return Phrase
	 */
	public function getDescription()
	{
		return \XF::phrase($this->getDescriptionPhraseName());
	}

	public function getMasterTitlePhrase()
	{
		$phrase = $this->MasterTitle;
		if (!$phrase)
		{
			$phrase = $this->_em->create(\XF\Entity\Phrase::class);
			$phrase->title = $this->_getDeferredValue(function () { return $this->getTitlePhraseName(); });
			$phrase->language_id = 0;
			$phrase->addon_id = $this->_getDeferredValue(function () { return $this->addon_id; });
		}

		return $phrase;
	}

	public function getMasterDescriptionPhrase()
	{
		$phrase = $this->MasterDescription;
		if (!$phrase)
		{
			$phrase = $this->_em->create(\XF\Entity\Phrase::class);
			$phrase->title = $this->_getDeferredValue(function () { return $this->getDescriptionPhraseName(); });
			$phrase->language_id = 0;
			$phrase->addon_id = $this->_getDeferredValue(function () { return $this->addon_id; });
		}

		return $phrase;
	}

	protected function _preSave()
	{
		$definitionClass = \XF::stringToClass($this->definition_class, '%s\ActivitySummary\%s');
		if (!class_exists($definitionClass))
		{
			$this->error(\XF::phrase('invalid_class_x', ['class' => $definitionClass]), 'definition_class');
		}
	}

	protected function _postSave()
	{
		if ($this->isUpdate())
		{
			if ($this->isChanged('addon_id') || $this->isChanged('definition_id'))
			{
				$writeDevOutput = $this->getBehavior(DevOutputWritable::class)->getOption('write_dev_output');

				/** @var Phrase $titlePhrase */
				$titlePhrase = $this->getExistingRelation('MasterTitle');
				if ($titlePhrase)
				{
					$titlePhrase->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', $writeDevOutput);

					$titlePhrase->addon_id = $this->addon_id;
					$titlePhrase->title = $this->getTitlePhraseName();
					$titlePhrase->save();
				}

				/** @var Phrase $descriptionPhrase */
				$descriptionPhrase = $this->getExistingRelation('MasterDescription');
				if ($descriptionPhrase)
				{
					$descriptionPhrase->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', $writeDevOutput);

					$descriptionPhrase->addon_id = $this->addon_id;
					$descriptionPhrase->title = $this->getDescriptionPhraseName();
					$descriptionPhrase->save();
				}
			}

			if ($this->isChanged('definition_id'))
			{
				$finder = $this->finder(ActivitySummarySectionFinder::class)->where('definition_id', $this->getExistingValue('definition_id'));

				foreach ($finder->fetch() AS $activitySummary)
				{
					$activitySummary->definition_id = $this->definition_id;
					$activitySummary->save();
				}
			}
		}
	}

	protected function _postDelete()
	{
		$writeDevOutput = $this->getBehavior(DevOutputWritable::class)->getOption('write_dev_output');

		$titlePhrase = $this->MasterTitle;
		if ($titlePhrase)
		{
			$titlePhrase->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', $writeDevOutput);

			$titlePhrase->delete();
		}
		$descriptionPhrase = $this->MasterDescription;
		if ($descriptionPhrase)
		{
			$descriptionPhrase->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', $writeDevOutput);

			$descriptionPhrase->delete();
		}

		$finder = $this->finder(ActivitySummarySectionFinder::class)->where('definition_id', $this->definition_id);

		foreach ($finder->fetch() AS $activitySummary)
		{
			$activitySummary->delete();
		}
	}

	protected function _setupDefaults()
	{
		/** @var AddOnRepository $addOnRepo */
		$addOnRepo = $this->_em->getRepository(AddOnRepository::class);
		$this->addon_id = $addOnRepo->getDefaultAddOnId();
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_activity_summary_definition';
		$structure->shortName = 'XF:ActivitySummaryDefinition';
		$structure->primaryKey = 'definition_id';
		$structure->columns = [
			'definition_id' => ['type' => self::STR, 'maxLength' => 50, 'match' => self::MATCH_ALPHANUMERIC, 'required' => true],
			'definition_class' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
			'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'default' => ''],
		];
		$structure->behaviors = [
			'XF:DevOutputWritable' => [],
		];
		$structure->getters = [
			'title' => false,
			'description' => false,
		];
		$structure->relations = [
			'AddOn' => [
				'entity' => 'XF:AddOn',
				'type' => self::TO_ONE,
				'conditions' => 'addon_id',
				'primary' => true,
			],
			'MasterTitle' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'activity_summary_def.', '$definition_id'],
				],
			],
			'MasterDescription' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'activity_summary_def_desc.', '$definition_id'],
				],
			],
		];
		$structure->options = [];

		return $structure;
	}

	/**
	 * @return ActivitySummaryRepository
	 */
	protected function getActivitySummaryRepo()
	{
		return $this->repository(ActivitySummaryRepository::class);
	}
}
