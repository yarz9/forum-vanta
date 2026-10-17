<?php

namespace XF\Repository;

use XF\Entity\ConversationMaster;
use XF\Entity\ConversationMessage;
use XF\Entity\ConversationUser;
use XF\Finder\ConversationMessageFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class ConversationMessageRepository extends Repository
{
	public function findMessagesForConversationView(ConversationMaster $conversation)
	{
		/** @var ConversationMessageFinder $finder */
		$finder = $this->finder(ConversationMessageFinder::class);
		$finder
			->inConversation($conversation)
			->order('message_date')
			->with('full');

		return $finder;
	}

	public function findNewestMessagesInConversation($conversation, $lastDate)
	{
		/** @var ConversationMessageFinder $finder */
		$finder = $this->finder(ConversationMessageFinder::class);
		$finder
			->inConversation($conversation)
			->order('message_date', 'DESC')
			->where('message_date', '>', $lastDate)
			->with('full');

		return $finder;
	}

	public function findNextMessageInConversation(ConversationMaster $conversation, $newerThan)
	{
		/** @var ConversationMessageFinder $finder */
		$finder = $this->finder(ConversationMessageFinder::class);
		$finder
			->inConversation($conversation)
			->order('message_date')
			->where('message_date', '>', $newerThan)
			->limit(1);

		return $finder;
	}

	public function getFirstUnreadMessageInConversation(ConversationUser $userConv, array $with = [])
	{
		if (!$userConv->isUnread())
		{
			return null;
		}

		$lastReadDate = $userConv->Recipient->last_read_date;
		$conversation = $userConv->Master;

		return $this->findNextMessageInConversation($conversation, $lastReadDate)->with($with)->fetchOne();
	}

	/**
	 * @param ConversationMaster $conversation
	 *
	 * @return Finder
	 */
	public function findLatestMessage(ConversationMaster $conversation)
	{
		/** @var ConversationMessageFinder $finder */
		$finder = $this->finder(ConversationMessageFinder::class);
		$finder
			->inConversation($conversation)
			->order('message_date', 'DESC')
			->limit(1);

		return $finder;
	}

	/**
	 * @param ConversationMaster $conversation
	 *
	 * @param ConversationMessage $message
	 *
	 * @return Finder
	 */
	public function findEarlierMessages(ConversationMaster $conversation, ConversationMessage $message)
	{
		/** @var ConversationMessageFinder $finder */
		$finder = $this->finder(ConversationMessageFinder::class);
		$finder->inConversation($conversation)
			->earlierThan($message);

		return $finder;
	}
}
