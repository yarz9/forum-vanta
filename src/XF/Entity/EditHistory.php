<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Repository\EditHistoryRepository;

/**
 * COLUMNS
 * @property int|null $edit_history_id
 * @property string $content_type
 * @property int $content_id
 * @property int $edit_user_id
 * @property int $edit_date
 * @property string $old_text
 *
 * GETTERS
 * @property-read Entity|null $Content
 *
 * RELATIONS
 * @property-read User|null $User
 */
class EditHistory extends Entity
{
	public function getHandler()
	{
		return $this->getEditHistoryRepo()->getEditHistoryHandler($this->content_type);
	}

	/**
	 * @return Entity|null
	 */
	public function getContent()
	{
		$handler = $this->getHandler();
		return $handler ? $handler->getContent($this->content_id) : null;
	}

	public function setContent(?Entity $content = null)
	{
		$this->_getterCache['Content'] = $content;
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_edit_history';
		$structure->shortName = 'XF:EditHistory';
		$structure->primaryKey = 'edit_history_id';
		$structure->columns = [
			'edit_history_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'content_type' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
			'content_id' => ['type' => self::UINT, 'required' => true],
			'edit_user_id' => ['type' => self::UINT, 'required' => true],
			'edit_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'old_text' => ['type' => self::STR, 'required' => true],
		];
		$structure->getters = [
			'Content' => true,
		];
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [['user_id', '=', '$edit_user_id']],
				'primary' => true,
			],
		];

		return $structure;
	}

	/**
	 * @return EditHistoryRepository
	 */
	protected function getEditHistoryRepo()
	{
		return $this->repository(EditHistoryRepository::class);
	}
}
