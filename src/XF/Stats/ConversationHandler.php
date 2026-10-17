<?php

namespace XF\Stats;

class ConversationHandler extends AbstractHandler
{
	public function getStatsTypes()
	{
		return [
			'conversation' => \XF::phrase('direct_messages'),
			'conversation_message' => \XF::phrase('direct_message_replies'),
		];
	}

	public function getData($start, $end)
	{
		$db = $this->db();

		$conversations = $db->fetchPairs(
			$this->getBasicDataQuery('xf_conversation_master', 'start_date'),
			[$start, $end]
		);

		$conversationMessages = $db->fetchPairs(
			$this->getBasicDataQuery('xf_conversation_message', 'message_date'),
			[$start, $end]
		);

		return [
			'conversation' => $conversations,
			'conversation_message' => $conversationMessages,
		];
	}
}
