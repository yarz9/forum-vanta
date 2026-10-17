<?php

namespace XF\Job;

use XF\Repository\PollRepository;

class Poll extends AbstractRebuildJob
{
	protected function getNextIds($start, $batch)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			"
				SELECT poll_id
				FROM xf_poll
				WHERE poll_id > ?
				ORDER BY poll_id
			",
			$batch
		), $start);
	}

	protected function rebuildById($id)
	{
		$this->app->repository(PollRepository::class)->rebuildPollData($id);
	}

	protected function getStatusType()
	{
		return \XF::phrase('polls');
	}
}
