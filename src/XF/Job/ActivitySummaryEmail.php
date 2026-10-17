<?php

namespace XF\Job;

use XF\ActivitySummary\Instance;
use XF\Entity\ActivitySummarySection;
use XF\Entity\User;
use XF\Finder\ActivitySummarySectionFinder;
use XF\Mvc\Entity\ArrayCollection;
use XF\Repository\ActivitySummaryRepository;
use XF\Timer;

use function count;

class ActivitySummaryEmail extends AbstractJob
{
	protected $defaultData = [
		'test_mode' => false,

		'section_ids' => [],
		'section_data' => [],

		'user_ids' => [],
		'last_user_id' => 0,
	];

	/**
	 * @var ArrayCollection|ActivitySummarySection[]
	 */
	protected $sections;

	public function run($maxRunTime)
	{
		$timer = new Timer($maxRunTime);

		if (!$this->data['section_ids'] || !$this->data['user_ids'])
		{
			return $this->complete();
		}

		if ($this->sections === null)
		{
			$this->sections = $this->app->finder(ActivitySummarySectionFinder::class)
				->whereIds($this->data['section_ids'])
				->order('display_order')
				->fetch();
		}
		if (!$this->sections || !count($this->sections))
		{
			return $this->complete();
		}

		$this->preFetchSectionData();

		foreach ($this->data['user_ids'] AS $key => $userId)
		{
			unset($this->data['user_ids'][$key]);
			$this->data['last_user_id'] = $userId;

			/** @var User $user */
			$user = $this->app->em()->find(User::class, $userId, ['PermissionCombination']);

			if (!$user)
			{
				continue;
			}

			if (!$user->canReceiveActivitySummaryEmail() && !$this->data['test_mode'])
			{
				continue;
			}

			$this->generateAndSendEmail($user);

			if ($timer->limitExceeded())
			{
				break;
			}
		}

		return $this->resume();
	}

	protected function preFetchSectionData()
	{
		foreach ($this->sections AS $sectionId => $section)
		{
			$handler = $section->handler;
			if (!$handler)
			{
				continue;
			}

			if (isset($this->data['section_data'][$sectionId]))
			{
				$handler->setupDataFromJob($this->data['section_data'][$sectionId]);
			}

			$handler->cacheDataIfNeeded();

			if (!isset($this->data['section_data'][$sectionId]))
			{
				$this->data['section_data'][$sectionId] = $handler->getDataForJob();
			}
		}
	}

	protected function generateAndSendEmail(User $user)
	{
		$instance = $this->generateEmailData($user);

		if ($instance->canSendActivitySummary())
		{
			$this->app->mailer()->newMail()
				->setTemplate('activity_summary', [
					'renderedSections' => $instance->getRenderedSections(),
					'displayValues' => $instance->getDisplayValues(),
				])
				->setToUser($user)
				->send();

			$user->fastUpdate('last_summary_email_date', time());
		}
	}

	/**
	 * @param User $user
	 *
	 * @return Instance
	 */
	protected function generateEmailData(User $user)
	{
		$instance = new Instance($user);

		foreach ($this->sections AS $sectionId => $section)
		{
			$handler = $section->handler;
			if (!$handler)
			{
				continue;
			}

			$html = $handler->render($instance);
			if ($html)
			{
				$instance->addRenderedSection($html);
			}

			if ($section->show_value)
			{
				$total = $handler->getTotal($instance);
				if ($total)
				{
					$instance->addDisplayValue($handler->getTitle(), $total);
				}
			}
		}

		/** @var ActivitySummaryRepository $repo */
		$repo = $this->app->repository(ActivitySummaryRepository::class);

		$repo->addInstanceSpecificDisplayValues($instance);
		$globalDisplayValues = $repo->getGlobalDisplayValues();

		$instance->addDisplayValues($globalDisplayValues);

		return $instance;
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('sending');
		$typePhrase = \XF::phrase('activity_summary_email');
		return sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, $this->data['last_user_id']);
	}

	public function canCancel()
	{
		return false;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}
