<?php

namespace XF\Service\Report;

use XF\App;
use XF\Entity\Moderator;
use XF\Entity\Report;
use XF\Entity\ReportComment;
use XF\Entity\User;
use XF\Repository\ReportRepository;
use XF\Repository\UserAlertRepository;
use XF\Service\AbstractService;

class NotifierService extends AbstractService
{
	/**
	 * @var Report
	 */
	protected $report;

	/**
	 * @var ReportComment
	 */
	protected $comment;

	/**
	 * @var list<int>
	 */
	protected $notifyMentioned = [];

	/**
	 * @var list<int>
	 */
	protected $notifyCreated = [];

	/**
	 * @var array<int, true>
	 */
	protected $usersAlerted = [];

	/**
	 * @var array<int, true>
	 */
	protected $usersEmailed = [];

	public function __construct(App $app, Report $report, ReportComment $comment)
	{
		parent::__construct($app);
		$this->report = $report;
		$this->comment = $comment;
	}

	/**
	 * @return Report
	 */
	public function getReport()
	{
		return $this->report;
	}

	/**
	 * @return ReportComment
	 */
	public function getComment()
	{
		return $this->comment;
	}

	/**
	 * @param list<int> $mentioned
	 */
	public function setNotifyMentioned(array $mentioned)
	{
		$this->notifyMentioned = array_unique($mentioned);
	}

	/**
	 * @return list<int>
	 */
	public function getNotifyMentioned()
	{
		return $this->notifyMentioned;
	}

	/**
	 * @param list<int> $mentioned
	 */
	public function setNotifyCreated(array $userIds): void
	{
		$this->notifyCreated = array_unique($userIds);
	}

	/**
	 * @return list<int>
	 */
	public function getNotifyCreated()
	{
		return $this->notifyCreated;
	}

	public function notifyMentioned()
	{
		$notifiableUsers = $this->getUsersForMentionedNotification();

		$mentionUsers = $this->getNotifyMentioned();
		foreach ($mentionUsers AS $k => $userId)
		{
			if (isset($notifiableUsers[$userId]))
			{
				$user = $notifiableUsers[$userId];
				if (\XF::asVisitor($user, function () { return $this->report->canView(); }))
				{
					$this->sendMentionNotification($user);
				}
			}
			unset($mentionUsers[$k]);
		}
		$this->notifyMentioned = [];
	}

	/**
	 * @return array<int, User>
	 */
	protected function getUsersForMentionedNotification()
	{
		$userIds = $this->getNotifyMentioned();

		$users = $this->app->em()->findByIds(User::class, $userIds, ['Profile', 'Option']);
		if (!$users->count())
		{
			return [];
		}

		$users = $users->toArray();
		foreach ($users AS $k => $user)
		{
			if (!\XF::asVisitor($user, function () { return $this->report->canView(); }))
			{
				unset($users[$k]);
			}
		}

		return $users;
	}

	/**
	 * @return bool
	 */
	protected function sendMentionNotification(User $user)
	{
		$comment = $this->comment;

		if (empty($this->usersAlerted[$user->user_id]) && ($user->user_id != $comment->user_id))
		{
			$alertRepo = $this->app->repository(UserAlertRepository::class);
			$alerted = $alertRepo->alert(
				$user,
				$comment->user_id,
				$comment->username,
				'report',
				$comment->report_id,
				'mention',
				[
					'comment' => $comment->toArray(),
				],
				['autoRead' => false]
			);
			if ($alerted)
			{
				$this->usersAlerted[$user->user_id] = true;
				return true;
			}
		}

		return false;
	}

	public function notifyCreate()
	{
		$notifiableModerators = $this->getUsersForCreatedNotification();

		foreach ($notifiableModerators AS $moderator)
		{
			$this->sendCreateNotification($moderator);
		}
	}

	/**
	 * @return array<int, Moderator>
	 */
	protected function getUsersForCreatedNotification(): array
	{
		$reportRepo = $this->repository(ReportRepository::class);
		$moderators = $reportRepo->getModeratorsWhoCanHandleReport(
			$this->report,
			true
		);

		return $moderators->toArray();
	}

	protected function sendCreateNotification(Moderator $moderator): void
	{
		if (
			!empty($this->usersEmailed[$moderator->User->user_id]) ||
			!$moderator->notify_report
		)
		{
			return;
		}

		$mailer = $this->app->mailer();
		$mailer->newMail()
			->setToUser($moderator->User)
			->setTemplate(
				'report_create',
				$this->getCreateNotificationTemplateParams($moderator)
			)
			->queue();

		$this->usersEmailed[$moderator->User->user_id] = true;
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function getCreateNotificationTemplateParams(Moderator $moderator): array
	{
		return [
			'receiver' => $moderator->User,
			'reporter' => $this->comment->User,
			'comment' => $this->comment,
			'report' => $this->report,
			'message' => $this->report->getContentMessage(),
		];
	}
}
