<?php

namespace XF\Repository;

use XF\Entity\AbstractField;
use XF\Entity\Forum;

class ForumFieldRepository extends AbstractFieldMap
{
	protected function getMapEntityIdentifier()
	{
		return 'XF:ForumField';
	}

	protected function getAssociationsForField(AbstractField $field)
	{
		return $field->getRelation('ForumFields');
	}

	protected function updateAssociationCache(array $cache)
	{
		$nodeIds = array_keys($cache);
		$forums = $this->em->findByIds(Forum::class, $nodeIds);

		foreach ($forums AS $forum)
		{
			/** @var Forum $forum */
			$forum->field_cache = $cache[$forum->node_id];
			$forum->saveIfChanged();
		}
	}
}
