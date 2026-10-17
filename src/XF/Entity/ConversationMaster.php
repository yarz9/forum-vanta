<?php

namespace XF\Entity;

use XF\Api\Result\EntityResult;
use XF\Behavior\IndexableContainer;
use XF\Draft;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\FinderCollection;
use XF\Mvc\Entity\Structure;
use XF\Repository\AttachmentRepository;
use XF\Repository\ConversationRepository;
use XF\Repository\ReactionRepository;

/**
 * COLUMNS
 * @property int|null $conversation_id
 * @property string $title
 * @property string $title_
 * @property int $user_id
 * @property string $username
 * @property int $start_date
 * @property bool $open_invite
 * @property bool $conversation_open
 * @property int $reply_count
 * @property int $recipient_count
 * @property int $first_message_id
 * @property int $last_message_date
 * @property int $last_message_id
 * @property int $last_message_user_id
 * @property string $last_message_username
 * @property array $recipients
 *
 * GETTERS
 * @property-read array $message_ids
 * @property-read array $last_message_cache
 * @property-read Draft $draft_reply
 * @property-read list<int> $recipient_user_ids
 * @property-read list<int> $active_recipient_user_ids
 *
 * RELATIONS
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XF\Entity\ConversationRecipient> $Recipients
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XF\Entity\ConversationUser> $Users
 * @property-read ConversationMessage|null $FirstMessage
 * @property-read ConversationMessage|null $LastMessage
 * @property-read User|null $LastMessageUser
 * @property-read User|null $Starter
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XF\Entity\Draft> $DraftReplies
 */
class ConversationMaster extends Entity implements LinkableInterface, ViewableInterface
{
	public function canEdit()
	{
		return ($this->user_id == \XF::visitor()->user_id);
	}

	public function canInvite()
	{
		$visitor = \XF::visitor();

		if ($visitor->hasPermission('conversation', 'alwaysInvite'))
		{
			return true;
		}

		if (!$this->open_invite && !$this->canEdit())
		{
			return false;
		}

		$remaining = $this->getRemainingRecipientsCount();
		return ($remaining == -1 || $remaining > 0);
	}

	public function canReply()
	{
		// if a conversation isn't open, we want the creator to know by not showing the reply box
		return (\XF::visitor()->user_state == 'valid' && $this->conversation_open);
	}

	public function canView(&$error = null)
	{
		return isset($this->Users[\XF::visitor()->user_id]);
	}

	public function canUploadAndManageAttachments()
	{
		$visitor = \XF::visitor();

		return ($visitor->user_id && $visitor->hasPermission('conversation', 'uploadAttachment'));
	}

	public function canUploadVideos()
	{
		$options = $this->app()->options();

		if (empty($options->allowVideoUploads['enabled']))
		{
			return false;
		}

		$visitor = \XF::visitor();

		return $visitor->hasPermission('conversation', 'uploadVideo');
	}

	/**
	 * @return Draft
	 */
	public function getDraftReply()
	{
		return Draft::createFromEntity($this, 'DraftReplies');
	}

	public function getRemainingRecipientsCount(?User $user = null)
	{
		$maxRecipients = $this->getMaximumAllowedRecipients($user);
		if ($maxRecipients == -1 || !$this->exists())
		{
			return $maxRecipients;
		}
		else
		{
			$remaining = ($maxRecipients - $this->recipient_count + 1); // +1 represents self; self doesn't count
			return max(0, $remaining);
		}
	}

	public function getMaximumAllowedRecipients(?User $user = null)
	{
		$user = $user ?: \XF::visitor();

		if ($user->hasPermission('conversation', 'alwaysInvite'))
		{
			return -1; // unlimited
		}

		return $user->hasPermission('conversation', 'maxRecipients');
	}

	public function getNewMessage(?User $user = null)
	{
		$message = $this->_em->create(ConversationMessage::class);

		$message->conversation_id = $this->_getDeferredValue(function ()
		{
			return $this->conversation_id;
		}, 'save');

		if ($user)
		{
			$message->user_id = $user->user_id;
			$message->username = $user->username;
		}

		return $message;
	}

	public function getNewRecipient(User $user)
	{
		$recipient = $this->_em->create(ConversationRecipient::class);
		$recipient->conversation_id = $this->conversation_id;
		$recipient->user_id = $user->user_id;

		return $recipient;
	}

	/**
	 * @return array
	 */
	public function getMessageIds()
	{
		return $this->db()->fetchAllColumn("
			SELECT message_id
			FROM xf_conversation_message
			WHERE conversation_id = ?
			ORDER BY message_date
		", $this->conversation_id);
	}

	/**
	 * @return array
	 */
	public function getLastMessageCache()
	{
		return [
			'message_id' => $this->last_message_id,
			'user_id' => $this->last_message_user_id,
			'username' => $this->last_message_username,
			'message_date' => $this->last_message_date,
		];
	}

	/**
	 * @return list<int>
	 */
	public function getRecipientUserIds(): array
	{
		return $this->db()->fetchAllColumn(
			'SELECT user_id
				FROM xf_conversation_recipient
				WHERE conversation_id = ?',
			[$this->conversation_id]
		);
	}

	/**
	 * @return list<int>
	 */
	public function getActiveRecipientUserIds(): array
	{
		return $this->db()->fetchAllColumn(
			'SELECT user_id
				FROM xf_conversation_recipient
				WHERE conversation_id = ? AND recipient_state = ?',
			[$this->conversation_id, 'active']
		);
	}

	public function messageAdded(ConversationMessage $message)
	{
		if (!$this->first_message_id)
		{
			$this->first_message_id = $message->message_id;
		}
		else
		{
			$this->reply_count++;
		}

		if ($message->message_date >= $this->last_message_date)
		{
			$this->last_message_date = $message->message_date;
			$this->last_message_id = $message->message_id;
			$this->last_message_user_id = $message->user_id;
			$this->last_message_username = $message->username;

			foreach ($this->Recipients AS $recipient)
			{
				if ($recipient->recipient_state == 'deleted_ignored')
				{
					continue;
				}

				/** @var ConversationUser $conversationUser */
				$conversationUser = $recipient->getRelationOrDefault('ConversationUser');

				if ($recipient->recipient_state == 'deleted')
				{
					$conversationUser->conversation_id = $this->conversation_id;
					$conversationUser->owner_user_id = $recipient->user_id;
					$conversationUser->is_starred = false;
				}

				$recipient->recipient_state = 'active';

				$conversationUser->reply_count = $this->reply_count;
				$conversationUser->last_message_date = $message->message_date;
				$conversationUser->last_message_id = $message->message_id;
				$conversationUser->last_message_user_id = $message->user_id;
				$conversationUser->last_message_username = $message->username;
				$conversationUser->is_unread = true;

				$recipient->save(); // saves $conversationUser too
			}
		}

		unset($this->_getterCache['message_ids']);
	}

	public function recipientAdded(
		ConversationRecipient $recipient,
		ConversationUser $user
	): void
	{
		/** @var FinderCollection $recipients */
		$recipients = $this->Recipients;
		$recipients->forceCache($recipient);

		/** @var FinderCollection $users */
		$users = $this->Users;
		$users->forceCache($user);

		unset($this->_getterCache['recipient_user_ids']);
		unset($this->_getterCache['active_recipient_user_ids']);

		$indexableContainer = $this->getBehavior(IndexableContainer::class);
		$indexableContainer->triggerReindex();
	}

	public function recipientRemoved(ConversationRecipient $recipient)
	{
		/** @var FinderCollection $recipients */
		$recipients = $this->Recipients;
		$recipients->forceCache($recipient);

		/** @var FinderCollection $users */
		$users = $this->Users;
		$users->forceRemove($recipient->user_id);

		unset($this->_getterCache['recipient_user_ids']);
		unset($this->_getterCache['active_recipient_user_ids']);

		if (!$users->count())
		{
			$this->delete();
			return;
		}

		$indexableContainer = $this->getBehavior(IndexableContainer::class);
		$indexableContainer->triggerReindex();
	}

	public function rebuildCounters()
	{
		$cache = $this->repository(ConversationRepository::class)->getConversationRecipientCache($this, $recipientTotal);
		$this->recipient_count = $recipientTotal;
		$this->recipients = $cache;

		$messageCount = $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_conversation_message
			WHERE conversation_id = ?
		", $this->conversation_id);

		if (!$messageCount)
		{
			// there are no messages in this conversation -- this should never happen,
			// but bail out to prevent an error.
			return;
		}

		$this->reply_count = max(0, $messageCount - 1);

		$lastMessage = $this->db()->fetchRow("
			SELECT message_id, message_date, user_id, username
			FROM xf_conversation_message
			WHERE conversation_id = ?
			ORDER BY message_date DESC
			LIMIT 1
		", $this->conversation_id);

		$this->last_message_date = $lastMessage['message_date'];
		$this->last_message_id = $lastMessage['message_id'];
		$this->last_message_user_id = $lastMessage['user_id'];
		$this->last_message_username = $lastMessage['username'];

		$userMappedColumns = [
			'reply_count' => $this->reply_count,
			'last_message_date' => $this->last_message_date,
			'last_message_id' => $this->last_message_id,
			'last_message_user_id' => $this->last_message_user_id,
			'last_message_username' => $this->last_message_username,
		];

		if ($this->isChanged(array_keys($userMappedColumns)))
		{
			$this->db()->update(
				'xf_conversation_user',
				$userMappedColumns,
				'conversation_id = ?',
				$this->conversation_id
			);
		}

		$this->clearCache('Recipients');
		$this->clearCache('Users');
	}

	protected function _postDelete()
	{
		$db = $this->db();

		$db->query("
			UPDATE xf_user AS user
			INNER JOIN xf_conversation_user AS cuser ON
				(cuser.owner_user_id = user.user_id AND cuser.conversation_id = ? AND cuser.is_unread = 1)
			SET user.conversations_unread = user.conversations_unread - 1
			WHERE user.conversations_unread > 0
		", $this->conversation_id);

		$db->delete('xf_conversation_recipient', 'conversation_id = ?', $this->conversation_id);
		$db->delete('xf_conversation_user', 'conversation_id = ?', $this->conversation_id);

		$messageIds = $this->message_ids;
		if ($messageIds)
		{
			$db->delete('xf_conversation_message', 'conversation_id = ?', $this->conversation_id);

			/** @var AttachmentRepository $attachRepo */
			$attachRepo = $this->repository(AttachmentRepository::class);
			$attachRepo->fastDeleteContentAttachments('conversation_message', $messageIds);

			/** @var ReactionRepository $reactionRepo */
			$reactionRepo = $this->repository(ReactionRepository::class);
			$reactionRepo->fastDeleteReactions('conversation_message', $messageIds);
		}
	}

	public function rebuildRecipientCache()
	{
		$this->repository(ConversationRepository::class)->rebuildConversationRecipientCache($this);
	}

	/**
	 * @param EntityResult $result
	 * @param int $verbosity
	 * @param array $options
	 *
	 * @api-type Conversation
	 *
	 * @api-out str $username Name of the user that started the conversation
	 * @api-out object $recipients Key-value pair of recipient user IDs and names
	 * @api-out bool $is_starred True if the viewing user starred the conversation
	 * @api-out bool $is_unread <cond> If accessing as a user, true if this conversation is unread
	 * @api-out bool $can_edit
	 * @api-out bool $can_reply
	 * @api-out bool $can_invite
	 * @api-out bool $can_upload_attachment
	 * @api-out string $view_url
	 */
	protected function setupApiResultData(
		EntityResult $result,
		$verbosity = self::VERBOSITY_NORMAL,
		array $options = []
	)
	{
		$result->username = $this->Starter ? $this->Starter->username : $this->username;

		$visitor = \XF::visitor();

		$recipients = [];
		foreach ($this->recipients AS $recipient)
		{
			$recipients[$recipient['user_id']] = $recipient['username'];
		}
		$result->recipients = $recipients;

		if ($visitor->user_id)
		{
			$userConv = $this->Users[$visitor->user_id];
			$result->is_starred = $userConv ? $userConv->is_starred : false;
			$result->is_unread = $userConv ? $userConv->isUnread() : false;
		}

		$result->can_edit = $this->canEdit();
		$result->can_reply = $this->canReply();
		$result->can_invite = $this->canInvite();
		$result->can_upload_attachment = $this->canUploadAndManageAttachments();

		$result->view_url = $this->getContentUrl(true);
	}

	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null)
	{
		$route = $canonical ? 'canonical:direct-messages' : 'direct-messages';
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	public function getContentPublicRoute()
	{
		return 'direct-messages';
	}

	public function getContentTitle(string $context = '')
	{
		return \XF::phrase('direct_message_x', ['title' => $this->title]);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_conversation_master';
		$structure->shortName = 'XF:ConversationMaster';
		$structure->primaryKey = 'conversation_id';
		$structure->contentType = 'conversation';
		$structure->columns = [
			'conversation_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'title' => ['type' => self::STR, 'maxLength' => 150,
				'required' => 'please_enter_valid_title',
				'censor' => true,
				'api' => true,
			],
			'user_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
			'username' => ['type' => self::STR, 'maxLength' => 50, 'required' => true, 'api' => true],
			'start_date' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'open_invite' => ['type' => self::BOOL, 'default' => false, 'api' => true],
			'conversation_open' => ['type' => self::BOOL, 'default' => true, 'api' => true],
			'reply_count' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'recipient_count' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'first_message_id' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'last_message_date' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'last_message_id' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'last_message_user_id' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'last_message_username' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
			'recipients' => ['type' => self::JSON_ARRAY, 'default' => []],
		];
		$structure->behaviors = [
			'XF:Indexable' => [
				'checkForUpdates' => [
					'title',
					'user_id',
					'start_date',
					'first_message_id',
				],
			],
			'XF:IndexableContainer' => [
				'childContentType' => 'conversation_message',
				'childIds' => function ($conversation)
				{
					/** @var static $conversation */
					return $conversation->message_ids;
				},
				'checkForUpdates' => false,
			],
		];
		$structure->getters = [
			'message_ids' => true,
			'last_message_cache' => true,
			'draft_reply' => true,
			'recipient_user_ids' => true,
			'active_recipient_user_ids' => true,
		];
		$structure->relations = [
			'Recipients' => [
				'entity' => 'XF:ConversationRecipient',
				'type' => self::TO_MANY,
				'conditions' => 'conversation_id',
				'key' => 'user_id',
			],
			'Users' => [
				'entity' => 'XF:ConversationUser',
				'type' => self::TO_MANY,
				'conditions' => 'conversation_id',
				'key' => 'owner_user_id',
			],
			'FirstMessage' => [
				'entity' => 'XF:ConversationMessage',
				'type' => self::TO_ONE,
				'conditions' => [
					['message_id', '=', '$first_message_id'],
				],
				'primary' => true,
			],
			'LastMessage' => [
				'entity' => 'XF:ConversationMessage',
				'type' => self::TO_ONE,
				'conditions' => [
					['message_id', '=', '$last_message_id'],
				],
				'primary' => true,
			],
			'LastMessageUser' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [
					['user_id', '=', '$last_message_user_id'],
				],
				'primary' => true,
			],
			'Starter' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
				'api' => true,
			],
			'DraftReplies' => [
				'entity' => 'XF:Draft',
				'type' => self::TO_MANY,
				'conditions' => [
					['draft_key', '=', 'conversation-reply-', '$conversation_id'],
				],
				'key' => 'user_id',
			],
		];
		$structure->withAliases = [
			'api' => ['Starter'],
		];

		return $structure;
	}
}
