<?php

namespace XF\Import\Data;

use XF\Criteria\PageCriteria;
use XF\Criteria\UserCriteria;
use XF\Repository\NoticeRepository;

/**
 * @mixin \XF\Entity\Notice
 */
class Notice extends AbstractEmulatedData
{
	public function getImportType()
	{
		return 'notice';
	}

	public function getEntityShortName()
	{
		return 'XF:Notice';
	}

	public function setPageCriteria(array $criteria)
	{
		$pageCriteria = $this->app()->criteria(PageCriteria::class, $this->reformatCriteria($criteria));
		$this->page_criteria = $pageCriteria->getCriteria();
	}

	public function setUserCriteria(array $criteria)
	{
		$userCriteria = $this->app()->criteria(UserCriteria::class, $this->reformatCriteria($criteria));
		$this->user_criteria = $userCriteria->getCriteria();
	}

	/**
	 * Reformats criteria from [$rule => $data] to [$rule => ['rule' => $rule, 'data' => $data]]
	 *
	 * @param array $criteria
	 *
	 * @return array
	 */
	protected function reformatCriteria(array $criteria)
	{
		$c = [];

		foreach ($criteria AS $rule => $data)
		{
			$c[$rule] = ['rule' => $rule, 'data' => $data];
		}

		return $c;
	}

	protected function preSave($oldId)
	{
		$this->forceNotEmpty('message', $oldId);
	}

	protected function postSave($oldId, $newId)
	{
		/** @var NoticeRepository $repo */
		$repo = $this->repository(NoticeRepository::class);

		\XF::runOnce('noticeCacheRebuild', function () use ($repo)
		{
			$repo->rebuildNoticeCache();
		});
	}
}
