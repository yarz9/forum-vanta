<?php

namespace XF\Job;

use XF\Repository\ThreadRepository;

class Thread extends AbstractRebuildJob
{
	protected $defaultData = [
		'position_rebuild' => false,
	];

	protected function getNextIds($start, $batch)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			"
				SELECT thread_id
				FROM xf_thread
				WHERE thread_id > ?
				ORDER BY thread_id
			",
			$batch
		), $start);
	}

	protected function rebuildById($id)
	{
		/** @var \XF\Entity\Thread $thread */
		$thread = $this->app->em()->find(\XF\Entity\Thread::class, $id);
		if (!$thread)
		{
			return;
		}

		$thread->rebuildCounters();
		$thread->save();

		/** @var ThreadRepository $threadRepo */
		$threadRepo = $this->app->repository(ThreadRepository::class);

		if ($this->data['position_rebuild'])
		{
			$threadRepo->rebuildThreadUserPostCounters($id);
			$threadRepo->rebuildThreadPostPositions($id);
		}

		$thread->rebuildThreadFieldValuesCache();
	}

	protected function getStatusType()
	{
		return \XF::phrase('threads');
	}
}
