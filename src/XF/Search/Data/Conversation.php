<?php

namespace XF\Search\Data;

use XF\Entity\ConversationMaster;
use XF\Mvc\Entity\Entity;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;

/**
 * @extends AbstractData<ConversationMaster>
 * @implements AutoCompletableInterface<ConversationMaster>
 */
class Conversation extends AbstractData implements AutoCompletableInterface
{
	use AutoCompletableTrait;

	public function getIndexData(Entity $entity): ?IndexRecord
	{
		$firstMessage = $entity->FirstMessage;

		return IndexRecord::create('conversation', $entity->conversation_id, [
			'title' => $entity->title_,
			'message' => $firstMessage ? $firstMessage->message_ : '',
			'date' => $entity->start_date,
			'user_id' => $entity->user_id,
			'discussion_id' => $entity->conversation_id,
			'metadata' => $this->getMetaData($entity),
		]);
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function getMetaData(ConversationMaster $entity): array
	{
		$recipients = $entity->recipient_user_ids;
		$activeRecipients = $entity->active_recipient_user_ids;

		return [
			'conversation' => $entity->conversation_id,
			'recipients'   => $recipients,
			'active_recipients' => $activeRecipients,
		];
	}

	public function setupMetadataStructure(MetadataStructure $structure): void
	{
		$structure->addField('conversation', MetadataStructure::INT);
		$structure->addField('recipients', MetadataStructure::INT);
		$structure->addField('active_recipients', MetadataStructure::INT);
	}

	public function getResultDate(Entity $entity): int
	{
		return $entity->start_date;
	}

	public function getTemplateData(Entity $entity, array $options = []): array
	{
		return [
			'conversation' => $entity,
			'options' => $options,
		];
	}

	public function getEntityWith($forView = false): array
	{
		$with = ['FirstMessage'];

		if ($forView)
		{
			$with[] = 'Starter';
			$with[] = 'Users|' . \XF::visitor()->user_id;
		}

		return $with;
	}

	public function canUseInlineModeration(Entity $entity, &$error = null): bool
	{
		return true;
	}

	public function getAutoCompleteResult(
		Entity $entity,
		array $options = []
	): ?array
	{
		if (!$entity->FirstMessage)
		{
			return null;
		}

		return $this->getSimpleAutoCompleteResult(
			$entity->title,
			$entity->getContentUrl(),
			$entity->FirstMessage->message,
			$entity->Starter
		);
	}
}
