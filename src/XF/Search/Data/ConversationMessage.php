<?php

namespace XF\Search\Data;

use XF\Http\Request;
use XF\Mvc\Entity\Entity;
use XF\Repository\UserRepository;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use XF\Search\Query\KeywordQuery;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\Query;
use XF\Search\Query\SqlConstraint;
use XF\Search\Query\SqlOrder;
use XF\Search\Query\TableReference;
use XF\Search\Query\TypeMetadataConstraint;
use XF\Util\Arr;

/**
 * @extends AbstractData<\XF\Entity\ConversationMessage>
 */
class ConversationMessage extends AbstractData
{
	public function getIndexData(Entity $entity): ?IndexRecord
	{
		if (!$entity->Conversation)
		{
			return null;
		}

		if ($entity->isFirstMessage())
		{
			$conversation = $entity->Conversation;
			$handler = $this->searcher->handler('conversation');
			return $handler->getIndexData($conversation);
		}

		return IndexRecord::create('conversation_message', $entity->message_id, [
			'message' => $entity->message_,
			'date' => $entity->message_date,
			'user_id' => $entity->user_id,
			'discussion_id' => $entity->conversation_id,
			'metadata' => $this->getMetaData($entity),
		]);
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function getMetaData(\XF\Entity\ConversationMessage $entity): array
	{
		$conversation = $entity->Conversation;
		$recipients = $conversation->recipient_user_ids;
		$activeRecipients = $conversation->active_recipient_user_ids;

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
		return $entity->message_date;
	}

	public function getTemplateData(Entity $entity, array $options = []): array
	{
		return [
			'message' => $entity,
			'options' => $options,
		];
	}

	public function getEntityWith($forView = false): array
	{
		$with = ['Conversation'];

		if ($forView)
		{
			$with[] =  'User';
			$with[] = 'Conversation.Users|' . \XF::visitor()->user_id;
		}

		return $with;
	}

	public function getSearchableContentTypes(): array
	{
		return ['conversation', 'conversation_message'];
	}

	public function getSearchFormTab(): ?array
	{
		if (!\XF::visitor()->user_id)
		{
			return null;
		}

		return [
			'title' => \XF::phrase('search_direct_messages'),
			'order' => 1100,
		];
	}

	public function applyTypeConstraintsFromInput(
		Query $query,
		Request $request,
		array &$urlConstraints
	): void
	{
		$recipients = $request->filter('c.recipients', 'str');
		if ($recipients)
		{
			$recipients = Arr::stringToArray($recipients, '/,\s*/');
			if ($recipients)
			{
				/** @var UserRepository $userRepo */
				$userRepo = \XF::repository(UserRepository::class);
				$matchedUsers = $userRepo->getUsersByNames($recipients, $notFound);
				if ($notFound)
				{
					$query->error(
						'users',
						\XF::phrase('following_members_not_found_x', [
							'members' => implode(', ', $notFound),
						])
					);
				}
				else
				{
					$query->withMetadata('recipients', $matchedUsers->keys());
					$urlConstraints['recipients'] = implode(', ', $recipients);
				}
			}
		}

		$minReplyCount = $request->filter('c.min_reply_count', 'uint');
		if ($minReplyCount)
		{
			$query->withSql(new SqlConstraint(
				'conversation.reply_count >= %s',
				$minReplyCount,
				$this->getConversationQueryTableReference()
			));
		}
		else
		{
			unset($urlConstraints['min_reply_count']);
		}

		$conversationId = $request->filter('c.conversation', 'uint');
		if ($conversationId)
		{
			$query->withMetadata('conversation', $conversationId);

			if ($query instanceof KeywordQuery)
			{
				$query->inTitleOnly(false);
			}
		}
		else
		{
			unset($urlConstraints['conversation']);
		}
	}

	public function getGroupByType(): string
	{
		return 'conversation';
	}

	public function getTypeOrder($order): ?SqlOrder
	{
		if ($order === 'replies')
		{
			return new SqlOrder(
				'conversation.reply_count DESC',
				$this->getConversationQueryTableReference()
			);
		}

		return null;
	}

	protected function getConversationQueryTableReference(): TableReference
	{
		return new TableReference(
			'conversation',
			'xf_conversation_master',
			'conversation.conversation_id = search_index.discussion_id'
		);
	}

	public function getTypePermissionConstraints(
		Query $query,
		$isOnlyType
	): array
	{
		$userId = \XF::visitor()->user_id;
		if (!$userId)
		{
			return [];
		}


		if (!$isOnlyType)
		{
			return [];
		}

		$recipientConstraint = new MetadataConstraint(
			'active_recipients',
			$userId
		);

		return [$recipientConstraint];
	}

	public function getTypePermissionTypeConstraints(
		Query $query,
		bool $isOnlyType
	): array
	{
		$userId = \XF::visitor()->user_id;
		if (!$userId)
		{
			$typeConstraint = new TypeMetadataConstraint(
				$this->getSearchableContentTypes(),
				TypeMetadataConstraint::MATCH_NONE
			);

			return [$typeConstraint];
		}


		if ($isOnlyType)
		{
			return [];
		}

		$typeConstraint = new TypeMetadataConstraint(
			$this->getSearchableContentTypes()
		);
		$recipientConstraint = new MetadataConstraint(
			'active_recipients',
			$userId
		);
		$typeConstraint->addMetadataConstraint($recipientConstraint);

		return [$typeConstraint];
	}

	public function canIncludeInResults(Entity $entity, array $resultIds): bool
	{
		if (
			isset($resultIds['conversation-' . $entity->conversation_id]) &&
			$entity->isFirstMessage()
		)
		{
			return false;
		}

		return true;
	}
}
