<?php

namespace XF\Search\Data;

use XF\Mvc\Entity\Entity;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;

/**
 * @extends AbstractData<\XF\Entity\Thread>
 * @implements AutoCompletableInterface<\XF\Entity\Thread>
 */
class Thread extends AbstractData implements AutoCompletableInterface
{
	use AutoCompletableTrait;

	public function getEntityWith($forView = false)
	{
		$get = ['Forum', 'FirstPost'];
		if ($forView)
		{
			$get[] = 'User';

			$visitor = \XF::visitor();
			$get[] = 'Forum.Node.Permissions|' . $visitor->permission_combination_id;
		}

		return $get;
	}

	public function getIndexData(Entity $entity)
	{
		if (!$entity->Forum || $entity->discussion_type == 'redirect')
		{
			return null;
		}

		$firstPost = $entity->FirstPost;

		$index = IndexRecord::create('thread', $entity->thread_id, [
			'title' => $entity->title_,
			'message' => $firstPost ? $firstPost->message_ : '',
			'date' => $entity->post_date,
			'user_id' => $entity->user_id,
			'discussion_id' => $entity->thread_id,
			'metadata' => $this->getMetaData($entity),
		]);

		if (!$entity->isVisible())
		{
			$index->setHidden();
		}

		if ($entity->tags)
		{
			$index->indexTags($entity->tags);
		}

		return $index;
	}

	protected function getMetaData(\XF\Entity\Thread $entity)
	{
		$metadata = [
			'node' => $entity->node_id,
			'thread' => $entity->thread_id,
			'thread_type' => $entity->discussion_type,
		];
		if ($entity->prefix_id)
		{
			$metadata['prefix'] = $entity->prefix_id;
		}

		return $metadata;
	}

	public function setupMetadataStructure(MetadataStructure $structure)
	{
		$structure->addField('node', MetadataStructure::INT);
		$structure->addField('thread', MetadataStructure::INT);
		$structure->addField('prefix', MetadataStructure::INT);
		$structure->addField('thread_type', MetadataStructure::KEYWORD);
	}

	public function getResultDate(Entity $entity)
	{
		return $entity->post_date;
	}

	public function getTemplateData(Entity $entity, array $options = [])
	{
		return [
			'thread' => $entity,
			'options' => $options,
		];
	}

	public function canUseInlineModeration(Entity $entity, &$error = null)
	{
		return $entity->canUseInlineModeration($error);
	}

	public function getAutoCompleteResult(
		Entity $entity,
		array $options = []
	): ?array
	{
		if (!$entity->FirstPost)
		{
			return null;
		}

		return $this->getSimpleAutoCompleteResult(
			$entity->title,
			$entity->getContentUrl(),
			$entity->FirstPost->message,
			$entity->User
		);
	}
}
