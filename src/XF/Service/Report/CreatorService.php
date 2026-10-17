<?php

namespace XF\Service\Report;

use XF\App;
use XF\Entity\Forum;
use XF\Entity\Report;
use XF\Entity\ReportComment;
use XF\Entity\User;
use XF\Finder\ReportFinder;
use XF\Mvc\Entity\Entity;
use XF\Report\AbstractHandler;
use XF\Repository\ReportRepository;
use XF\Repository\ThreadRepository;
use XF\Repository\ThreadWatchRepository;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

use function count, in_array, intval;

class CreatorService extends AbstractService
{
	use ValidateAndSavableTrait;

	/** @var Report */
	protected $report;

	/** @var AbstractHandler */
	protected $handler;

	/** @var Entity */
	protected $content;

	/** @var \XF\Service\Thread\CreatorService */
	protected $threadCreator;

	/** @var ReportComment */
	protected $comment;

	/** @var CommentPreparerService */
	protected $commentPreparer;

	/** @var User */
	protected $user;

	public function __construct(App $app, $contentType, Entity $content)
	{
		parent::__construct($app);

		$this->user = \XF::visitor();

		$this->createReport($contentType, $content);
		$this->setupComment();
		$this->setDefaults();
	}

	public function createReport($contentType, Entity $content)
	{
		$contentId = $content->getIdentifierValues();
		if (!$contentId || count($contentId) != 1)
		{
			throw new \InvalidArgumentException("Entity does not have an ID or does not have a simple key");
		}
		$contentId = intval(reset($contentId));

		$report = $this->finder(ReportFinder::class)
			->where('content_type', $contentType)
			->where('content_id', $contentId)
			->fetchOne();

		if (!$report)
		{
			$report = $this->em()->create(Report::class);
			$report->content_type = $contentType;
			$report->content_id = $contentId;
		}

		/** @var ReportRepository $reportRepo */
		$reportRepo = $this->repository(ReportRepository::class);
		$handler = $reportRepo->getReportHandler($contentType, true);
		if (!$handler)
		{
			throw new \LogicException("Cannot find report handler for '$contentType'");
		}

		$handler->setupReportEntityContent($report, $content);
		$this->report = $report;
		$this->content = $content;

		if ($forumId = $this->app->options()->reportIntoForumId)
		{
			$forum = $this->em()->find(Forum::class, $forumId, 'Node');
			if ($forum)
			{
				$this->sendReportIntoForum($forum);
			}
		}
	}

	protected function setDefaults()
	{
		$time = \XF::$time;
		$user = $this->user;

		if (!$this->report->report_id)
		{
			$this->report->first_report_date = $time;
		}

		$this->report->last_modified_date = $time;
		$this->report->last_modified_user_id = $user->user_id;
		$this->report->last_modified_username = $user->username;
		if (!in_array($this->report->report_state, ['assigned', 'open']))
		{
			$this->report->report_state = 'open';
			$this->comment->state_change = 'open';
		}

		$this->commentPreparer->setUser($user);
	}

	protected function setupComment()
	{
		$this->comment = $this->report->getNewComment();
		$this->comment->is_report = true;
		$this->commentPreparer = $this->service(CommentPreparerService::class, $this->comment);

		$this->report->addCascadedSave($this->comment);
	}

	public function sendReportIntoForum(Forum $forum)
	{
		/** @var \XF\Service\Thread\CreatorService $threadCreator */
		$threadCreator = $this->service(\XF\Service\Thread\CreatorService::class, $forum);
		$threadCreator->setIsAutomated();
		if ($forum->default_prefix_id)
		{
			$threadCreator->setPrefix($forum->default_prefix_id);
		}
		$this->threadCreator = $threadCreator;

		return $threadCreator;
	}

	public function getThreadCreator()
	{
		return $this->threadCreator;
	}

	public function getCommentPreparer()
	{
		return $this->commentPreparer;
	}

	public function setMessage($message, $format = true)
	{
		if ($this->threadCreator)
		{
			$report = $this->report;
			$handler = $report->getHandler();

			$threadCreator = $this->threadCreator;

			$params = $handler->getContentForThreadReport($report, $message);

			$title = \XF::phrase('reported_thread_title', $params)->render('raw');
			$messageContent = \XF::phrase('reported_thread_message', $params)->render('raw');

			$threadCreator->setContent($title, $messageContent);
		}

		return $this->commentPreparer->setMessage($message, $format);
	}

	protected function _validate()
	{
		if ($this->threadCreator)
		{
			$success = $this->threadCreator->validate($errors);
			return $success ? [] : $errors;
		}

		$this->report->preSave();
		return $this->report->getErrors();
	}

	protected function _save()
	{
		if ($this->threadCreator)
		{
			$threadCreator = $this->threadCreator;

			$thread = $threadCreator->save();
			\XF::asVisitor($this->user, function () use ($thread)
			{
				$this->repository(ThreadRepository::class)->markThreadReadByVisitor($thread, $thread->post_date);

				if ($thread->canView())
				{
					$this->repository(ThreadWatchRepository::class)->autoWatchThread($thread, $this->user);
				}
			});

			return $thread;
		}

		$report = $this->report;

		$db = $this->db();
		$db->beginTransaction();

		// comment will also be saved now if applicable
		$report->save(true, false);
		$report->fastUpdate('report_count', $report->report_count + 1);

		\XF::runOnce('reportCountsRebuild', function ()
		{
			$this->repository(ReportRepository::class)->rebuildReportCounts();
		});

		$db->commit();

		return $report;
	}

	public function sendNotifications()
	{
		if ($this->threadCreator)
		{
			$this->threadCreator->sendNotifications();
			return;
		}

		/** @var NotifierService $notifier */
		$notifier = $this->service(NotifierService::class, $this->report, $this->comment);
		$notifier->notifyCreate();

		// TODO: send tagging notifications
	}
}
