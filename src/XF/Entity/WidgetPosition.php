<?php

namespace XF\Entity;

use XF\Behavior\DevOutputWritable;
use XF\Finder\WidgetFinder;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\AddOnRepository;
use XF\Repository\WidgetRepository;

/**
 * COLUMNS
 * @property string|null $position_id
 * @property string $addon_id
 * @property bool $active
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
class WidgetPosition extends Entity
{
	public function getTitlePhraseName()
	{
		return 'widget_pos.' . $this->position_id;
	}

	public function getDescriptionPhraseName()
	{
		return 'widget_pos_desc.' . $this->position_id;
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

	protected function _postSave()
	{
		if ($this->isUpdate())
		{
			if ($this->isChanged('addon_id') || $this->isChanged('position_id'))
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

			if ($this->isChanged('position_id'))
			{
				$oldPositionId = $this->getExistingValue('position_id');
				$newPositionId = $this->get('position_id');

				$finder = $this->finder(WidgetFinder::class);
				$finder->where('positions', 'LIKE', $finder->escapeLike($oldPositionId, '%"?"%'));

				foreach ($finder->fetch() AS $widget)
				{
					$positions = $widget->positions;
					$positions[$newPositionId] = $positions[$oldPositionId];

					unset($positions[$oldPositionId]);
					ksort($positions);

					$widget->positions = $positions;

					$widget->save();
				}
			}
		}

		$this->rebuildWidgetPositionCache();
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

		$finder = $this->finder(WidgetFinder::class);
		$finder->where('positions', 'LIKE', $finder->escapeLike($this->position_id, '%"?"%'));

		/** @var Widget $widget */
		foreach ($finder->fetch() AS $widget)
		{
			$positions = $widget->positions;
			unset($positions[$this->position_id]);

			if ($positions)
			{
				$widget->positions = $positions;
				$widget->save();
			}
			else
			{
				$widget->delete(false);
			}
		}

		$this->rebuildWidgetPositionCache();
	}

	protected function _setupDefaults()
	{
		/** @var AddOnRepository $addOnRepo */
		$addOnRepo = $this->_em->getRepository(AddOnRepository::class);
		$this->addon_id = $addOnRepo->getDefaultAddOnId();
	}

	protected function rebuildWidgetPositionCache()
	{
		\XF::runOnce('widgetPositionCacheRebuild', function ()
		{
			$this->getWidgetRepo()->rebuildWidgetPositionCache();
		});
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_widget_position';
		$structure->shortName = 'XF:WidgetPosition';
		$structure->primaryKey = 'position_id';
		$structure->columns = [
			'position_id' => ['type' => self::STR, 'maxLength' => 50, 'nullable' => true,
				'required' => 'please_enter_widget_position_key',
				'unique' => 'widget_position_keys_must_be_unique',
				'match' => self::MATCH_ALPHANUMERIC,
			],
			'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'default' => ''],
			'active' => ['type' => self::BOOL, 'default' => true],
		];
		$structure->behaviors = [
			'XF:DevOutputWritable' => [],
		];
		$structure->getters = [
			'title' => true,
			'description' => true,
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
					['title', '=', 'widget_pos.', '$position_id'],
				],
			],
			'MasterDescription' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'widget_pos_desc.', '$position_id'],
				],
			],
		];
		$structure->options = [];

		return $structure;
	}

	/**
	 * @return WidgetRepository
	 */
	protected function getWidgetRepo()
	{
		return $this->repository(WidgetRepository::class);
	}
}
