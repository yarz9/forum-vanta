<?php

namespace XF\Service\Poll;

use XF\App;
use XF\Entity\Poll;
use XF\Repository\PollRepository;
use XF\Service\AbstractService;

class ResetterService extends AbstractService
{
	/** @var Poll */
	protected $poll;

	public function __construct(App $app, Poll $poll)
	{
		parent::__construct($app);
		$this->poll = $poll;
	}

	public function getPoll()
	{
		return $this->poll;
	}

	public function reset()
	{
		$poll = $this->poll;
		$content = $poll->Content;
		$contentType = $poll->content_type;

		$this->repository(PollRepository::class)->resetPollVotes($poll);

		$this->app->logger()->logModeratorAction($contentType, $content, 'poll_reset');
	}
}
