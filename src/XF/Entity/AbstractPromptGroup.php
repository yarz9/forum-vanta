<?php

namespace XF\Entity;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\AbstractPrompt;

/**
 * COLUMNS
 * @property int|null $prompt_group_id
 * @property int $display_order
 *
 * GETTERS
 * @property-read Phrase|string $title
 *
 * RELATIONS
 * @property-read Phrase|null $MasterTitle
 * @property-read AbstractCollection|AbstractPrompt[] $Prompts
 */
abstract class AbstractPromptGroup extends Entity
{
	abstract protected function getClassIdentifier();

	protected static function getContentType()
	{
		throw new \LogicException('The content type must be overridden.');
	}

	public function getPhraseName()
	{
		return static::getContentType() . '_prompt_group.' . $this->prompt_group_id;
	}

	/**
	 * @return Phrase|string
	 */
	public function getTitle()
	{
		return $this->prompt_group_id ? \XF::phrase($this->getPhraseName(), [], false) : '';
	}

	public function getMasterPhrase()
	{
		$phrase = $this->MasterTitle;
		if (!$phrase)
		{
			$phrase = $this->_em->create(\XF\Entity\Phrase::class);
			$phrase->title = $this->_getDeferredValue(function () { return $this->getPhraseName(); }, 'save');
			$phrase->language_id = 0;
			$phrase->addon_id = '';
		}

		return $phrase;
	}

	protected function _postSave()
	{
		if ($this->isChanged('display_order'))
		{
			$this->rebuildPromptCaches();
		}
	}

	protected function _postDelete()
	{
		if ($this->MasterTitle)
		{
			$this->MasterTitle->delete();
		}

		if ($this->Prompts)
		{
			foreach ($this->Prompts AS $prompt)
			{
				$prompt->prompt_group_id = 0;
				$prompt->save();
			}
		}

		$this->rebuildPromptCaches();
	}

	protected function rebuildPromptCaches()
	{
		$repo = $this->getPromptRepo();

		\XF::runOnce(static::getContentType() . 'PromptGroupCaches', function () use ($repo)
		{
			$repo->rebuildPromptMaterializedOrder();
		});
	}

	protected static function setupDefaultStructure(Structure $structure, $table, $shortName, $promptShortName)
	{
		$structure->table = $table;
		$structure->shortName = $shortName;
		$structure->primaryKey = 'prompt_group_id';
		$structure->columns = [
			'prompt_group_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'display_order' => ['type' => self::UINT, 'forced' => true, 'default' => 1],
		];
		$structure->getters = [
			'title' => true,
		];
		$contentType = static::getContentType();
		$structure->relations = [
			'MasterTitle' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', $contentType . '_prompt_group.', '$prompt_group_id'],
				],
			],
			'Prompts' => [
				'entity' => $promptShortName,
				'type' => self::TO_MANY,
				'conditions' => 'prompt_group_id',
			],
		];
	}

	/**
	 * @return AbstractPrompt
	 */
	protected function getPromptRepo()
	{
		return $this->repository($this->getClassIdentifier());
	}
}
