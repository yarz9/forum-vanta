<?php

namespace XF\Repository;

use XF\Entity\Report;
use XF\Finder\ReportFinder;
use XF\Mvc\Entity\Repository;
use XF\Report\AbstractHandler;

class ReportRepository extends Repository
{
	protected $handlerCache = [];

	/**
	 * @param array $state
	 *
	 * @return ReportFinder
	 */
	public function findReports($state = ['open', 'assigned'], $timeFrame = null)
	{
		$finder = $this->finder(ReportFinder::class)
			->with('User');

		$finder->inTimeFrame($timeFrame)
			->order('last_modified_date', 'desc');

		if ($state)
		{
			$finder->where('report_state', $state);
		}

		return $finder;
	}

	/**
	 * @param $type
	 * @param bool $throw
	 *
	 * @return AbstractHandler|null
	 */
	public function getReportHandler($type, $throw = false)
	{
		if (isset($this->handlerCache[$type]))
		{
			return $this->handlerCache[$type];
		}

		$handlerClass = \XF::app()->getContentTypeFieldValue($type, 'report_handler_class');
		if (!$handlerClass)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("No report handler for '$type'");
			}
			return null;
		}

		if (!class_exists($handlerClass))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("Report handler for '$type' does not exist: $handlerClass");
			}
			return null;
		}

		$handlerClass = \XF::extendClass($handlerClass);
		$handler = new $handlerClass($type);

		$this->handlerCache[$type] = $handler;

		return $handler;
	}

	public function getModeratorsWhoCanHandleReport(Report $report, $notifiableOnly = false)
	{
		/** @var ModeratorRepository $moderatorRepo */
		$moderatorRepo = $this->repository(ModeratorRepository::class);

		$moderators = $moderatorRepo->findModeratorsForList()->with('User.PermissionCombination')->fetch();

		if ($moderators->count())
		{
			foreach ($moderators AS $id => $moderator)
			{
				$canView = \XF::asVisitor(
					$moderator->User,
					function () use ($report) { return $report->canView(); }
				);
				if (!$canView)
				{
					unset($moderators[$id]);
				}

				if ($notifiableOnly && !$moderator->notify_report)
				{
					unset($moderators[$id]);
				}
			}
		}

		return $moderators;
	}

	public function rebuildReportCounts()
	{
		$cache = [
			'total' => $this->db()->fetchOne("SELECT COUNT(*) FROM xf_report WHERE report_state IN('open', 'assigned')"),
			'lastModified' => time(),
		];

		\XF::registry()->set('reportCounts', $cache);
		return $cache;
	}
}
