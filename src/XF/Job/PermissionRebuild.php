<?php

namespace XF\Job;

use XF\Entity\PermissionCombination;
use XF\Phrase;
use XF\Repository\PermissionCombinationRepository;

class PermissionRebuild extends AbstractRebuildJob
{
	protected $defaultData = [
		'cleaned' => false,
	];

	/**
	 * @param float $maxRunTime
	 */
	public function run($maxRunTime)
	{
		if (!$this->data['cleaned'])
		{
			/** @var PermissionCombinationRepository $combinationRepo */
			$combinationRepo = $this->app->repository(PermissionCombinationRepository::class);
			$combinationRepo->deleteUnusedPermissionCombinations();

			$this->data['cleaned'] = true;
		}

		return parent::run($maxRunTime);
	}

	/**
	 * @param int $start
	 * @param int $batch
	 *
	 * @return int[]
	 */
	protected function getNextIds($start, $batch): array
	{
		$db = $this->app->db();

		$nextIds = $db->fetchAllColumn(
			$db->limit(
				'SELECT permission_combination_id
					FROM xf_permission_combination
					WHERE permission_combination_id > ?
					ORDER BY permission_combination_id',
				$batch
			),
			$start
		);
		if (!$nextIds)
		{
			// there are situations where we run this job but not with this unique key, so this is unnecessary
			$this->app->jobManager()->cancelUniqueJob('permissionRebuild');
		}

		return $nextIds;
	}

	/**
	 * @param int $id
	 */
	protected function rebuildById($id)
	{
		/** @var PermissionCombination $combination */
		$combination = $this->app->find(PermissionCombination::class, $id);
		if (!$combination)
		{
			return;
		}

		$this->app->permissionBuilder()->rebuildCombination($combination);
	}

	protected function getStatusType(): Phrase
	{
		return \XF::phrase('permissions');
	}

	/**
	 * @return bool
	 */
	public function canCancel()
	{
		return false;
	}

	/**
	 * @return bool
	 */
	public function canTriggerByChoice()
	{
		return false;
	}
}
