<?php

namespace XF\Repository;

use XF\Entity\ConversationMaster;
use XF\Entity\ConversationUser;
use XF\Entity\User;
use XF\Finder\ConversationMasterFinder;
use XF\Finder\ConversationUserFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Util\Arr;

use function count, intval, is_string;

class ConversationRepository extends Repository
{
	public function findUserConversations(User $user, $forList = true)
	{
		/** @var ConversationUserFinder $finder */
		$finder = $this->finder(ConversationUserFinder::class);
		$finder->forUser($user, $forList)
			->setDefaultOrder('last_message_date', 'desc');

		return $finder;
	}

	public function findUserConversationsForPopupList(User $user, $unread, $cutOff = null)
	{
		$finder = $this->findUserConversations($user);
		$finder->where('is_unread', $unread ? 1 : 0);

		if ($cutOff)
		{
			$finder->where('last_message_date', '>', $cutOff);
		}

		return $finder;
	}

	public function getUserConversationsForPopup(User $user, $maxLimit, $cutOff, array $extraWith = [])
	{
		$unreadFinder = $this->findUserConversationsForPopupList($user, true)->with($extraWith);
		$unread = $unreadFinder->fetch($maxLimit);
		$totalUnread = $unread->count();

		if ($totalUnread < $maxLimit)
		{
			$readFinder = $this->findUserConversationsForPopupList($user, false, $cutOff)->with($extraWith);
			$read = $readFinder->fetch($maxLimit - $totalUnread)->toArray();
		}
		else
		{
			$read = [];
		}

		return [
			'unread' => $unread->toArray(),
			'read' => $read,
		];
	}

	public function markUserConversationRead(ConversationUser $userConv, $newRead = null)
	{
		if ($newRead === null)
		{
			$newRead = \XF::$time;
		}

		if (!$userConv->Master)
		{
			return;
		}

		$markRecipient = ($userConv->Recipient && $newRead > $userConv->Recipient->last_read_date);
		$markUser = ($userConv->is_unread && $newRead >= $userConv->Master->last_message_date);

		if ($markRecipient || $markUser)
		{
			$this->db()->beginTransaction();

			if ($markRecipient)
			{
				$userConv->Recipient->last_read_date = $newRead;
				$userConv->Recipient->save(false, false);
			}

			if ($markUser)
			{
				$userConv->is_unread = false;
				$userConv->save(false, false);
			}

			$this->db()->commit();
		}
	}

	public function markUserConversationUnread(ConversationUser $userConv)
	{
		if (!$userConv->Master)
		{
			return;
		}

		$markRecipient = ($userConv->Recipient && $userConv->Recipient->last_read_date > 0);
		$markUser = ($userConv->is_unread ? false : true);

		if ($markRecipient || $markUser)
		{
			$this->db()->beginTransaction();

			if ($markRecipient)
			{
				$userConv->Recipient->last_read_date = 0;
				$userConv->Recipient->save(false, false);
			}

			if ($markUser)
			{
				$userConv->is_unread = true;
				$userConv->save(false, false);
			}

			$this->db()->commit();
		}
	}

	/**
	 * @param User $user
	 *
	 * @return Finder
	 */
	public function findConversationsStartedByUser(User $user)
	{
		return $this->finder(ConversationMasterFinder::class)
			->where('user_id', $user->user_id)
			->setDefaultOrder('start_date', 'DESC');
	}

	public function getValidatedRecipients($recipients, User $from, &$error = null, $checkPrivacy = true)
	{
		$error = null;

		if (is_string($recipients))
		{
			$recipients = Arr::stringToArray($recipients, '#\s*,\s*#');
		}
		else if ($recipients instanceof User)
		{
			$recipients = [$recipients];
		}

		if (!count($recipients))
		{
			return [];
		}

		if ($recipients instanceof AbstractCollection)
		{
			$first = $recipients->first();
		}
		else
		{
			$first = reset($recipients);
		}

		if ($first instanceof User)
		{
			$type = 'user';
		}
		else
		{
			$type = 'name';
		}

		foreach ($recipients AS $k => $recipient)
		{
			if ($type == 'user' && !($recipient instanceof User))
			{
				throw new \InvalidArgumentException("Recipient at key $k must be a user entity");
			}
		}

		if ($type == 'name')
		{
			/** @var UserRepository $userRepo */
			$userRepo = $this->repository(UserRepository::class);
			$users = $userRepo->getUsersByNames($recipients, $notFound, ['Privacy']);

			if ($notFound)
			{
				$error = \XF::phraseDeferred(
					'the_following_recipients_could_not_be_found_x',
					['names' => implode(', ', $notFound)]
				);
			}
		}
		else
		{
			$users = $recipients;
		}

		$newRecipients = [];
		$cantStart = [];

		foreach ($users AS $user)
		{
			if ($checkPrivacy && !$from->canStartConversationWith($user))
			{
				$cantStart[$user->user_id] = $user->username;
				continue;
			}

			$newRecipients[$user->user_id] = $user;
		}

		if ($cantStart)
		{
			$error = \XF::phraseDeferred(
				'you_may_not_send_a_direct_message_to_the_following_recipients_x',
				['names' => implode(', ', $cantStart)]
			);
		}

		return $newRecipients;
	}

	public function insertRecipients(
		ConversationMaster $conversation,
		array $recipientUsers,
		?User $from = null
	)
	{
		$existingRecipients = $conversation->Recipients->populate();
		$insertedActiveUsers = [];
		$inserted = 0;
		$fromUserId = $from ? $from->user_id : null;

		$this->db()->beginTransaction();

		/** @var User $user */
		foreach ($recipientUsers AS $user)
		{
			if ($fromUserId && $user->isIgnoring($fromUserId))
			{
				$state = 'deleted_ignored';
			}
			else
			{
				$state = 'active';
			}

			if (isset($existingRecipients[$user->user_id]))
			{
				$recipient = $existingRecipients[$user->user_id];

				if ($recipient->recipient_state != 'deleted')
				{
					// keep the current state regardless of the new state unless in an unignored deleted state
					continue;
				}
				else if ($state != 'active')
				{
					// if we're in a unignored deleted state, don't allow us to go to anything other than active
					continue;
				}
			}
			else
			{
				$recipient = $conversation->getNewRecipient($user);
			}

			if ($fromUserId && $user->user_id == $fromUserId)
			{
				// if inserting by self, that would imply they're creating a conversation, so mark it read
				$recipient->last_read_date = $conversation->last_message_date;
			}

			$recipient->recipient_state = $state;

			if ($recipient->isInsert())
			{
				$inserted++; // need to update recipient count and cache

				if ($recipient->recipient_state == 'active')
				{
					$insertedActiveUsers[$user->user_id] = $user;
				}
			}

			$recipient->save(true, false);
		}

		if ($inserted)
		{
			$this->rebuildConversationRecipientCache($conversation);
		}

		$this->db()->commit();

		return $insertedActiveUsers;
	}

	public function getConversationRecipientCache(ConversationMaster $conversation, &$total = 0)
	{
		$cache = $this->db()->fetchAllKeyed("
			SELECT recipient.user_id, COALESCE(user.username, '') AS username
			FROM xf_conversation_recipient AS recipient
			LEFT JOIN xf_user AS user ON (recipient.user_id = user.user_id)
			WHERE recipient.conversation_id = ?
			ORDER BY user.username
		", 'user_id', $conversation->conversation_id);

		$total = count($cache);

		unset($cache[$conversation->user_id]);

		return $cache;
	}

	public function rebuildConversationRecipientCache(ConversationMaster $conversation)
	{
		$cache = $this->getConversationRecipientCache($conversation, $recipientTotal);
		$conversation->fastUpdate([
			'recipient_count' => $recipientTotal,
			'recipients' => $cache,
		]);
		$conversation->clearCache('Recipients');
		$conversation->clearCache('Users');

		return $cache;
	}

	public function updateRecipientCacheForUserChange($oldUserId, $newUserId, $oldUsername, $newUsername)
	{
		// note that xf_conversation_recipient must already be updated
		$oldFind = '"' . intval($oldUserId) . '":' . '{"user_id":' . intval($oldUserId) . ',"username":"' . $oldUsername . '"}';
		$newReplace = '"' . intval($newUserId) . '":' . '{"user_id":' . intval($newUserId) . ',"username":"' . $newUsername . '"}';

		$this->db()->query("
			UPDATE (
				SELECT conversation_id
				FROM xf_conversation_recipient
				WHERE user_id = ?
			) AS temp
			INNER JOIN xf_conversation_master AS master ON (master.conversation_id = temp.conversation_id)
			SET master.recipients = REPLACE(master.recipients, ?, ?)
		", [$newUserId, $oldFind, $newReplace]);
	}

	public function findRecipientsForList(ConversationMaster $conversation)
	{
		$finder = $conversation->getRelationFinder('Recipients');
		$finder->with('User')->order('User.username');

		return $finder;
	}
}
