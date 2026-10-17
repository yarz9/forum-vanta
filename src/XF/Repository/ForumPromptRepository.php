<?php

namespace XF\Repository;

use XF\Entity\AbstractPrompt;
use XF\Entity\Forum;

class ForumPromptRepository extends AbstractPromptMap
{
	protected function getMapEntityIdentifier()
	{
		return 'XF:ForumPrompt';
	}

	protected function getAssociations(AbstractPrompt $prompt)
	{
		return $prompt->getRelation('ForumPrompts');
	}

	protected function updateAssociationCache(array $cache)
	{
		$nodeIds = array_keys($cache);
		$forums = $this->em->findByIds(Forum::class, $nodeIds);

		foreach ($forums AS $forum)
		{
			/** @var Forum $forum */
			$forum->prompt_cache = $cache[$forum->node_id];
			$forum->saveIfChanged();
		}
	}
}
