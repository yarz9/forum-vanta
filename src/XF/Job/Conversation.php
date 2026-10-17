<?php

namespace XF\Job;

use XF\Entity\ConversationMaster;

class Conversation extends AbstractRebuildJob
{
	protected function getNextIds($start, $batch)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			"
				SELECT conversation_id
				FROM xf_conversation_master
				WHERE conversation_id > ?
				ORDER BY conversation_id
			",
			$batch
		), $start);
	}

	protected function rebuildById($id)
	{
		/** @var ConversationMaster $conversation */
		$conversation = $this->app->em()->find(ConversationMaster::class, $id);
		if ($conversation)
		{
			$conversation->rebuildCounters();
			$conversation->save();

		}
	}

	protected function getStatusType()
	{
		return \XF::phrase('direct_messages');
	}
}
