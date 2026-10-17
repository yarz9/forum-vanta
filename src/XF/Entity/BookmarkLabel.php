<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;
use XF\Mvc\Entity\Structure;
use XF\Repository\BookmarkRepository;

/**
 * COLUMNS
 * @property int|null $label_id
 * @property string $label
 * @property string $label_url
 * @property int $user_id
 * @property int $use_count
 * @property int $last_use_date
 *
 * RELATIONS
 * @property-read User|null $User
 */
class BookmarkLabel extends Entity
{
	protected function _preSave()
	{
		if ($this->label && !$this->label_url)
		{
			$this->setTrusted('label_url', $this->getBookmarkRepo()->generateLabelUrlVersion($this->label));
		}

		if ($this->isChanged(['label', 'user_id']))
		{
			$existingLabel = $this->em()->findOne(BookmarkLabel::class, [
				'user_id' => $this->user_id,
				'label' => $this->label,
			]);
			if ($existingLabel)
			{
				$this->error(\XF::phrase('labels_must_be_unique'), 'label');
			}
		}
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_bookmark_label';
		$structure->shortName = 'XF:BookmarkLabel';
		$structure->primaryKey = 'label_id';
		$structure->columns = [
			'label_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'label' => ['type' => self::STR, 'required' => true, 'maxLength' => 100],
			'label_url' => ['type' => self::STR, 'required' => true, 'maxLength' => 100],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'use_count' => ['type' => self::UINT, 'forced' => true, 'default' => 0],
			'last_use_date' => ['type' => self::UINT, 'default' => 0],
		];
		$structure->getters = [];
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
		];
		$structure->options = [];

		return $structure;
	}

	/**
	 * @return Repository|BookmarkRepository
	 */
	protected function getBookmarkRepo()
	{
		return $this->repository(BookmarkRepository::class);
	}
}
