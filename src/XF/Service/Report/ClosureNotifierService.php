<?php

namespace XF\Service\Report;

use XF\App;
use XF\Entity\Report;
use XF\Entity\User;
use XF\Phrase;
use XF\Repository\UserAlertRepository;
use XF\Service\AbstractService;

class ClosureNotifierService extends AbstractService
{
	/**
	 * @var Report
	 */
	protected $report;

	protected $notifyUserIds = [];

	protected $alertComment = '';
	protected $alertType = null;

	public function __construct(App $app, Report $report, ?array $notifyUserIds = null)
	{
		parent::__construct($app);
		$this->report = $report;
		$this->alertType = $this->report->report_state;
		$this->setNotifyUserIds($notifyUserIds);
	}

	public function getReport()
	{
		return $this->report;
	}

	public function setNotifyUserIds(?array $notifyUserIds = null)
	{
		if ($notifyUserIds === null)
		{
			$notifyUserIds = $this->determineNotifiableUserIds();
		}

		$this->notifyUserIds = $notifyUserIds;
	}

	public function getNotifyUserIds()
	{
		return $this->notifyUserIds;
	}

	public function setAlertComment($comment)
	{
		$this->alertComment = $comment;
	}

	public function getAlertComment()
	{
		return $this->alertComment;
	}

	public function setAlertType($type)
	{
		$this->alertType = $type;
	}

	public function getAlertType()
	{
		return $this->alertType;
	}

	public function notify()
	{
		$users = $this->app->em()->findByIds(User::class, $this->notifyUserIds, ['Profile', 'Option']);
		foreach ($users AS $user)
		{
			$this->sendClosureNotification($user);
		}
		$this->notifyUserIds = [];
	}

	protected function sendClosureNotification(User $user)
	{
		$report = $this->report;

		$title = $report->title;
		if ($title instanceof Phrase)
		{
			$title = $title->render('raw');
		}
		$link = $report->link;

		/** @var UserAlertRepository $alertRepo */
		$alertRepo = $this->repository(UserAlertRepository::class);
		$alertSent = $alertRepo->alertFromUser(
			$user,
			null,
			'user',
			$user->user_id,
			"report_{$this->alertType}",
			[
				'comment' => $this->alertComment,
				'title' => $title,
				'link' => $link,
			]
		);

		return $alertSent;
	}

	public function determineNotifiableUserIds()
	{
		$reportId = $this->report->report_id;
		$db = $this->db();

		$closeStates = ['resolved', 'rejected'];
		$lastCloseDate = $db->fetchOne(
			'SELECT comment_date
				FROM xf_report_comment
				WHERE report_id = ?
					AND state_change IN (' . $db->quote($closeStates) . ')
				ORDER BY comment_date DESC
				LIMIT 1 OFFSET 1',
			[$reportId]
		) ?: 0;


		return $db->fetchAllColumn(
			'SELECT DISTINCT user_id
				FROM xf_report_comment
				WHERE report_id = ?
					AND comment_date > ?
					AND is_report = 1
					AND user_id <> 0',
			[$reportId, $lastCloseDate]
		);
	}
}
