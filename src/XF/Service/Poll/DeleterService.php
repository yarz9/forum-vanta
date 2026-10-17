<?php

namespace XF\Service\Poll;

use XF\App;
use XF\Entity\Poll;
use XF\Service\AbstractService;

class DeleterService extends AbstractService
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

	public function delete()
	{
		$poll = $this->poll;
		$content = $poll->Content;
		$contentType = $poll->content_type;

		$poll->delete();

		if ($content && isset($content->User) && $content->User && $content->User->user_id != \XF::visitor()->user_id)
		{
			$this->app->logger()->logModeratorAction($contentType, $content, 'poll_delete');
		}

		return $poll;
	}
}
