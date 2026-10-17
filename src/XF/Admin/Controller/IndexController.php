<?php

namespace XF\Admin\Controller;

use XF\AdminNavigation;
use XF\Finder\EmailBounceLogFinder;
use XF\Finder\ModeratorLogFinder;
use XF\Finder\PaymentProviderLogFinder;
use XF\Finder\SpamCleanerLogFinder;
use XF\Finder\SpamTriggerLogFinder;
use XF\Install\Helper;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Reply\AbstractReply;
use XF\Repository\AddOnRepository;
use XF\Repository\ErrorLogRepository;
use XF\Repository\FileCheckRepository;
use XF\Repository\SessionActivityRepository;
use XF\Repository\StatsRepository;
use XF\Repository\TemplateRepository;
use XF\Repository\UpgradeCheckRepository;
use XF\Service\Stats\GrapherService;
use XF\Stats\Grouper\AbstractGrouper;
use XF\Util\Php;

class IndexController extends AbstractController
{
	/**
	 * @return AbstractReply
	 */
	public function actionIndex()
	{
		// TODO: put these bits and pieces into configurable / selectable widgets
		$visitor = \XF::visitor();

		$upgradeCheckRepo = \XF::repository(UpgradeCheckRepository::class);
		$upgradeCheck = $upgradeCheckRepo->canCheckForUpgrades()
			? $upgradeCheckRepo->getLatestUpgradeCheck()
			: null;

		$showUnicodeWarning = (
			$visitor->hasAdminPermission('serverInfo') &&
			\XF::db()->getSchemaManager()->hasUnicodeMismatch($mismatchType) &&
			$mismatchType === 'loose'
		);

		$outdatedTemplates = 0;
		if ($visitor->hasAdminPermission('style'))
		{
			$templateRepo = \XF::repository(TemplateRepository::class);
			$outdatedTemplates = $templateRepo->countOutdatedTemplates();
		}

		$serverErrorLogs = false;
		if ($visitor->hasAdminPermission('viewLogs'))
		{
			$errorLogRepo = \XF::repository(ErrorLogRepository::class);
			$serverErrorLogs = $errorLogRepo->hasErrorsInLog();
		}

		$hasProcessingAddOn = false;
		if ($visitor->hasAdminPermission('addOn'))
		{
			$addOnRepo = \XF::repository(AddOnRepository::class);
			$hasProcessingAddOn = $addOnRepo->hasAddOnsBeingProcessed();
		}

		$legacyConfig = (
			$visitor->hasAdminPermission('serverInfo') &&
			file_exists(\XF::app()->container('config.legacyFile'))
		);

		$hasStoppedJobs = false;
		$hasStoppedManualJobs = false;
		if ($visitor->hasAdminPermission('serverInfo'))
		{
			$jobManager = \XF::app()->jobManager();
			$hasStoppedJobs = $jobManager->hasStoppedJobs();
			$hasStoppedManualJobs = $jobManager->hasStoppedManualJobs();
		}

		$isImportRunning = false;
		if ($visitor->hasAdminPermission('import'))
		{
			$importManager = \XF::app()->import()->manager();
			$isImportRunning = $importManager->isImportRunning();
		}

		$fileChecks = \XF::em()->getEmptyCollection();
		if ($visitor->hasAdminPermission('checksAndTests'))
		{
			$fileCheckRepo = \XF::repository(FileCheckRepository::class);
			$fileCheckFinder = $fileCheckRepo->findFileChecksForList();
			$fileChecks = $fileCheckFinder->fetch(5);
		}

		$requirementErrors = [];
		if ($visitor->hasAdminPermission('serverInfo'))
		{
			$installHelper = new Helper(\XF::app());
			$requirementErrors = $installHelper->getRequirementErrors();
		}

		$stats = [];
		if ($visitor->hasAdminPermission('viewStatistics'))
		{
			/** @var AbstractGrouper $grouper */
			$grouper = \XF::app()->create('stats.grouper', 'daily');
			$statsRepo = \XF::repository(StatsRepository::class);

			foreach ($this->getDashboardStatGraphs() AS $statDisplayTypes)
			{
				$now = \XF::$time;
				$start = $now - 30 * 86400;
				$end = $now - ($now % 86400) - 1; // yesterday
				$grapher = \XF::service(
					GrapherService::class,
					$start,
					$end,
					$statDisplayTypes
				);

				$stats[] = [
					'data' => $grapher->getGroupedData($grouper),
					'phrases' => $statsRepo->getStatsTypePhrases($statDisplayTypes),
				];
			}
		}

		/** @var AdminNavigation $nav */
		$nav = \XF::app()['navigation.admin'];
		$navigation = $nav->getTree();

		$envReport = $visitor->hasAdminPermission('serverInfo')
			? $envReport = Php::getEnvironmentReport()
			: [];

		$logCounts = [];
		if ($visitor->hasAdminPermission('viewLogs'))
		{
			$cutOffs = [
				'day' => \XF::$time - 86400,
				'week' => \XF::$time - 86400 * 7,
				'month' => \XF::$time - 86400 * 30,
			];

			foreach ($this->getLogSummaryTypes() AS $logKey => $logData)
			{
				$values = [];
				foreach ($cutOffs AS $cutOffType => $cutOffDate)
				{
					$finder = \XF::finder($logData['finder'])
						->where($logData['date'], '>=', $cutOffDate);

					if (!empty($logData['where']))
					{
						$finder->where($logData['where']);
					}

					$values[$cutOffType] = $finder->total();
				}

				$logCounts[$logKey] = $values;
			}
		}

		$staffOnline = [];
		if ($visitor->hasAdminPermission('viewOnlineStaff'))
		{
			$activityRepo = \XF::repository(SessionActivityRepository::class);
			$staffOnline = $activityRepo->getOnlineStaffList();
		}

		$installedAddOns = [];
		if ($visitor->hasAdminPermission('addOn'))
		{
			$installedAddOns = \XF::app()->addOnManager()->getInstalledAddOns();
			foreach ($installedAddOns AS $id => $addOn)
			{
				if ($id === 'XF' || $addOn->canUpgrade() || $addOn->isLegacy())
				{
					continue;
				}

				if (!$addOn->isInstalled())
				{
					continue;
				}

				$installedAddOns[$id] = $addOn;
			}
		}

		$viewParams = [
			'upgradeCheck' => $upgradeCheck,
			'showUnicodeWarning' => $showUnicodeWarning,
			'outdatedTemplates' => $outdatedTemplates,
			'serverErrorLogs' => $serverErrorLogs,
			'hasProcessingAddOn' => $hasProcessingAddOn,
			'legacyConfig' => $legacyConfig,
			'hasStoppedJobs' => $hasStoppedJobs,
			'hasStoppedManualJobs' => $hasStoppedManualJobs,
			'isImportRunning' => $isImportRunning,
			'fileChecks' => $fileChecks,
			'requirementErrors' => $requirementErrors,
			'stats' => $stats,
			'navigation' => $navigation,
			'envReport' => $envReport,
			'logCounts' => $logCounts,
			'staffOnline' => $staffOnline,
			'installedAddOns' => $installedAddOns,
		];
		return $this->view('XF:Index', 'index', $viewParams);
	}

	/**
	 * @return list<list<string>>
	 */
	protected function getDashboardStatGraphs()
	{
		return [
			['post', 'thread'],
			['user_registration', 'user_activity'],
		];
	}

	/**
	 * @return array<string, array{
	 *     finder: class-string<Finder>,
	 *     date: string,
	 *     where?: list<list<string>>
	 * }>
	 */
	protected function getLogSummaryTypes()
	{
		return [
			'moderator' => [
				'finder' => ModeratorLogFinder::class,
				'date' => 'log_date',
			],
			'spamTrigger' => [
				'finder' => SpamTriggerLogFinder::class,
				'date' => 'log_date',
			],
			'spamCleaner' => [
				'finder' => SpamCleanerLogFinder::class,
				'date' => 'application_date',
			],
			'emailBounce' => [
				'finder' => EmailBounceLogFinder::class,
				'date' => 'log_date',
			],
			'payment' => [
				'finder' => PaymentProviderLogFinder::class,
				'date' => 'log_date',
				'where' => [
					['log_type', '=', 'payment'],
				],
			],
		];
	}
}
