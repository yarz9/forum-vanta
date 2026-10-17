<?php

namespace XF\Job;

use XF\Entity\EditHistory;
use XF\Finder\EditHistoryFinder;
use XF\Mvc\Entity\Finder;
use XF\Repository\EditHistoryRepository;

class UserRevertMessageEdit extends AbstractJob
{
	protected $defaultData = [
		'userId' => null,
		'cutOff' => null,
		'last' => null,
		'lastId' => null,
		'count' => 0,
		'total' => 0,
	];

	public function run($maxRunTime)
	{
		$startTime = microtime(true);

		if (!$this->data['userId'] || $this->data['cutOff'] === null)
		{
			return $this->complete();
		}

		if (!$this->data['last'])
		{
			$this->data['last'] = \XF::$time;
		}

		/** @var Finder $historyFinder */
		$historyFinder = $this->app->finder(EditHistoryFinder::class)
			->where('edit_user_id', $this->data['userId'])
			->where('edit_date', '>=', $this->data['cutOff'])
			->order('edit_date', 'DESC')
			->order('edit_history_id', 'DESC');

		if ($this->data['lastId'])
		{
			$historyFinder->whereOr(
				['edit_date', '<', $this->data['last']],
				[
					['edit_date', '=', $this->data['last']],
					['edit_history_id', '<', $this->data['lastId']],
				]
			);
		}
		else
		{
			$historyFinder->where('edit_date', '<=', $this->data['last']);
		}

		$count = $historyFinder->total();
		if (!$count)
		{
			return $this->complete();
		}

		if (!$this->data['total'])
		{
			$this->data['total'] = $count;
		}

		$continue = false;

		/** @var EditHistoryRepository $historyRepo */
		$historyRepo = $this->app->repository(EditHistoryRepository::class);

		/** @var EditHistory $edit */
		foreach ($historyFinder->fetch(500) AS $edit)
		{
			$historyRepo->revertToHistory($edit);

			$this->data['count']++;
			$this->data['last'] = $edit->edit_date;
			$this->data['lastId'] = $edit->edit_history_id;

			if ($maxRunTime && microtime(true) - $startTime > $maxRunTime)
			{
				$continue = true;
				break;
			}
		}

		if (!$continue)
		{
			return $this->complete();
		}

		return $this->resume();
	}

	public function getStatusMessage()
	{
		return sprintf('%s... %s/%s', \XF::phrase('reverting_edits'), $this->data['count'], $this->data['total']);
	}

	public function canCancel()
	{
		return true;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}
