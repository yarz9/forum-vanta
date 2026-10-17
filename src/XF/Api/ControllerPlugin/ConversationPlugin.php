<?php

namespace XF\Api\ControllerPlugin;

use XF\Entity\ConversationUser;
use XF\Finder\ConversationUserFinder;
use XF\Mvc\Reply\Exception;

class ConversationPlugin extends AbstractPlugin
{
	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return ConversationUser
	 *
	 * @throws Exception
	 */
	public function assertViewableUserConversation($id, $with = 'api')
	{
		$visitor = \XF::visitor();

		/** @var ConversationUserFinder $finder */
		$finder = $this->finder(ConversationUserFinder::class);
		$finder->forUser($visitor, false);
		$finder->where('conversation_id', $id);
		$finder->with($with);

		/** @var ConversationUser $conversation */
		$conversation = $finder->fetchOne();
		if (!$conversation || !$conversation->Master)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_direct_message_not_found')));
		}

		return $conversation;
	}
}
