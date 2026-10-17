<?php

namespace XF\Entity;

use XF\Behavior\DevOutputWritable;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\AddOnRepository;
use XF\Repository\IconRepository;
use XF\Repository\OptionRepository;

/**
 * COLUMNS
 * @property string $group_id
 * @property string $icon
 * @property int $display_order
 * @property bool $debug_only
 * @property bool $advanced
 * @property string $addon_id
 *
 * GETTERS
 * @property-read ArrayCollection $Options
 * @property-read Phrase $title
 * @property-read Phrase $description
 *
 * RELATIONS
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XF\Entity\OptionGroupRelation> $Relations
 * @property-read AddOn|null $AddOn
 * @property-read \XF\Entity\Phrase|null $MasterTitle
 * @property-read \XF\Entity\Phrase|null $MasterDescription
 */
class OptionGroup extends Entity
{
	public function canEdit()
	{
		return \XF::$developmentMode;
	}

	/**
	 * @return ArrayCollection
	 */
	public function getOptions()
	{
		return $this->getOptionRepo()->findOptionsInGroup($this)->fetch();
	}

	/**
	 * @return Phrase
	 */
	public function getTitle()
	{
		return \XF::phrase($this->getPhraseName(true));
	}

	/**
	 * @return Phrase
	 */
	public function getDescription()
	{
		return \XF::phrase($this->getPhraseName(false));
	}

	public function getPhraseName($title)
	{
		return 'option_group' . ($title ? '' : '_description') . '.' . $this->group_id;
	}

	public function getMasterPhrase($title)
	{
		$phrase = $title ? $this->MasterTitle : $this->MasterDescription;
		if (!$phrase)
		{
			$phrase = $this->_em->create(\XF\Entity\Phrase::class);
			$phrase->addon_id = $this->_getDeferredValue(function () { return $this->addon_id; });
			$phrase->title = $this->_getDeferredValue(function () use ($title) { return $this->getPhraseName($title); });
			$phrase->language_id = 0;
		}

		return $phrase;
	}

	protected function _postSave()
	{
		if ($this->isUpdate())
		{
			if ($this->isChanged('addon_id') || $this->isChanged('group_id'))
			{
				$writeDevOutput = $this->getBehavior(DevOutputWritable::class)->getOption('write_dev_output');

				/** @var Phrase $titlePhrase */
				$titlePhrase = $this->getExistingRelation('MasterTitle');
				if ($titlePhrase)
				{
					$titlePhrase->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', $writeDevOutput);

					$titlePhrase->addon_id = $this->addon_id;
					$titlePhrase->title = $this->getPhraseName(true);
					$titlePhrase->save();
				}

				/** @var Phrase $descriptionPhrase */
				$descriptionPhrase = $this->getExistingRelation('MasterDescription');
				if ($descriptionPhrase)
				{
					$descriptionPhrase->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', $writeDevOutput);

					$descriptionPhrase->addon_id = $this->addon_id;
					$descriptionPhrase->title = $this->getPhraseName(false);
					$descriptionPhrase->save();
				}
			}

			if ($this->isChanged('group_id'))
			{
				$this->db()->update(
					'xf_option_group_relation',
					['group_id' => $this->group_id],
					'group_id = ?',
					$this->getExistingValue('group_id')
				);
				foreach ($this->getOptionRepo()->findOptionsInGroup($this)->fetch() AS $option)
				{
					\XF::app()->developmentOutput()->export($option);
				}
			}
		}

		if ($this->isChanged('icon'))
		{
			$iconRepo = $this->repository(IconRepository::class);
			$iconRepo->enqueueUsageAnalyzer('option_group');
		}

		$this->rebuildOptionCache();
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

		$db = $this->db();

		/** @var AbstractCollection $options */
		$options = $this->Options;

		$optionIds = $options->keys();
		if ($optionIds)
		{
			$multiGroupOptionIds = $db->fetchPairs('
				SELECT DISTINCT option_id, 1
				FROM xf_option_group_relation
				WHERE option_id IN (' . $db->quote($optionIds) . ')
					AND group_id <> ?
			', $this->group_id);

			foreach ($options AS $option)
			{
				/** @var $option Option */
				if (isset($multiGroupOptionIds[$option->option_id]))
				{
					continue;
				}

				$option->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', $writeDevOutput);

				$option->delete();
			}
		}

		$db->delete('xf_option_group_relation', 'group_id = ?', $this->group_id);

		$this->rebuildOptionCache();
	}

	protected function rebuildOptionCache()
	{
		$repo = $this->getOptionRepo();

		\XF::runOnce('optionCacheRebuild', function () use ($repo)
		{
			$repo->rebuildOptionCache();
		});
	}

	protected function _setupDefaults()
	{
		/** @var AddOnRepository $addOnRepo */
		$addOnRepo = $this->_em->getRepository(AddOnRepository::class);
		$this->addon_id = $addOnRepo->getDefaultAddOnId();
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_option_group';
		$structure->shortName = 'XF:OptionGroup';
		$structure->primaryKey = 'group_id';
		$structure->columns = [
			'group_id' => ['type' => self::STR, 'maxLength' => 50,
				'required' => 'please_enter_valid_option_group_id',
				'unique' => 'option_group_ids_must_be_unique',
				'match' => self::MATCH_ALPHANUMERIC,
			],
			'icon' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
			'display_order' => ['type' => self::UINT, 'default' => 1],
			'debug_only' => ['type' => self::BOOL, 'default' => false],
			'advanced' => ['type' => self::BOOL, 'default' => false],
			'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'default' => ''],
		];
		$structure->behaviors = [
			'XF:DevOutputWritable' => [],
		];
		$structure->getters = [
			'Options' => true,
			'title' => true,
			'description' => true,
		];
		$structure->relations = [
			'Relations' => [
				'entity' => 'XF:OptionGroupRelation',
				'type' => self::TO_MANY,
				'conditions' => 'group_id',
				'key' => 'option_id',
			],
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
					['title', '=', 'option_group.', '$group_id'],
				],
			],
			'MasterDescription' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'option_group_description.', '$group_id'],
				],
			],
		];
		$structure->options = [];

		return $structure;
	}

	/**
	 * @return OptionRepository
	 */
	protected function getOptionRepo()
	{
		return $this->_em->getRepository(OptionRepository::class);
	}
}
