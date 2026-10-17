<?php

namespace XF\Spam\Cleaner;

use XF\Finder\ConversationMasterFinder;

class Conversation extends AbstractHandler
{
	public function canCleanUp(array $options = [])
	{
		return !empty($options['delete_conversations']);
	}

	public function cleanUp(array &$log, &$error = null)
	{
		$conversationsFinder = \XF::app()->finder(ConversationMasterFinder::class);
		$conversations = $conversationsFinder->where('user_id', $this->user->user_id)->fetch();

		foreach ($conversations AS $conversation)
		{
			$conversation->delete();
		}

		$log['conversation'] = [
			'count' => $conversations->count(),
		];

		return true;
	}

	public function restore(array $log, &$error = null)
	{
		return true;
	}
}
