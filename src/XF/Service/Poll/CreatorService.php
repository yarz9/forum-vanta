<?php

namespace XF\Service\Poll;

use XF\App;
use XF\Entity\Poll;
use XF\Mvc\Entity\Entity;
use XF\Poll\AbstractHandler;
use XF\Poll\ResponseEditor;
use XF\Repository\PollRepository;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class CreatorService extends AbstractService
{
	use ValidateAndSavableTrait;

	/** @var Entity */
	protected $content;

	/** @var Poll */
	protected $poll;

	/** @var ResponseEditor */
	protected $responseEditor;

	/** @var AbstractHandler */
	protected $handler;

	protected $maxResponses;

	public function __construct(App $app, $contentType, Entity $content)
	{
		parent::__construct($app);
		$this->setContent($contentType, $content);

		$this->maxResponses = $this->app->options()->pollMaximumResponses;
	}

	protected function setContent($contentType, Entity $content)
	{
		$this->content = $content;

		/** @var Poll $poll */
		$poll = $this->em()->create(Poll::class);
		$poll->content_type = $contentType;

		// might be created before the content has been made
		$id = $content->getEntityId();
		if (!$id)
		{
			$id = $poll->em()->getDeferredValue(function () use ($content)
			{
				return $content->getEntityId();
			}, 'save');
		}

		$poll->content_id = $id;

		$this->handler = $this->repository(PollRepository::class)->getPollHandler($contentType, true);
		$this->poll = $poll;
		$this->responseEditor = $poll->getResponseEditor();
	}

	public function getContent()
	{
		return $this->content;
	}

	public function getPoll()
	{
		return $this->poll;
	}

	public function setQuestion($question)
	{
		$this->poll->question = $question;
	}

	public function addResponses(array $responses)
	{
		$this->responseEditor->addResponses($responses);
	}

	public function setMaxResponses($max)
	{
		$this->maxResponses = $max;
	}

	public function getMaxResponses()
	{
		return $this->maxResponses;
	}

	public function setMaxVotes($type, $value = null)
	{
		$this->poll->setMaxVotes($type, $value);
	}

	public function setCloseDateRelative($value, $unit)
	{
		$this->poll->setCloseDateRelative($value, $unit);
	}

	public function setOptions(array $options)
	{
		$this->poll->bulkSet($options);
	}

	protected function _validate()
	{
		$this->poll->preSave();

		$errors = $this->poll->getErrors();

		$responseError = $this->responseEditor->getResponseCountErrorMessage($this->maxResponses);
		if ($responseError)
		{
			$errors['responses'] = $responseError;
		}

		return $errors;
	}

	protected function _save()
	{
		$poll = $this->poll;
		$content = $poll->Content;
		$contentType = $poll->content_type;

		$poll->save();

		if ($content->User && $content->User->user_id != \XF::visitor()->user_id)
		{
			$this->app->logger()->logModeratorAction($contentType, $content, 'poll_create');
		}

		$this->responseEditor->saveChanges();

		return $poll;
	}
}
