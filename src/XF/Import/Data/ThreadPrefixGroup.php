<?php

namespace XF\Import\Data;

/**
 * @mixin \XF\Entity\ThreadPrefixGroup
 */
class ThreadPrefixGroup extends AbstractEmulatedData
{
	protected $title = '';

	public function getImportType()
	{
		return 'thread_prefix_group';
	}

	public function getEntityShortName()
	{
		return 'XF:ThreadPrefixGroup';
	}

	public function setTitle($title)
	{
		$this->title = $title;
	}

	protected function postSave($oldId, $newId)
	{
		/** @var \XF\Entity\ThreadPrefixGroup $group */
		$group = $this->em()->find(\XF\Entity\ThreadPrefixGroup::class, $newId);
		if ($group)
		{
			$this->insertMasterPhrase($group->getPhraseName(), $this->title);

			$this->em()->detachEntity($group);
		}
	}
}
