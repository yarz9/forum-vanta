<?php

namespace XF\Service\Poll;

use XF\App;
use XF\Entity\Poll;
use XF\Mvc\Entity\Entity;
use XF\Poll\AbstractHandler;
use XF\Poll\ResponseEditor;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class EditorService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected $contentType;

	/** @var Entity */
	protected $content;

	/** @var Poll */
	protected $poll;

	/** @var AbstractHandler */
	protected $handler;

	/** @var ResponseEditor */
	protected $responseEditor;

	protected $maxResponses = 0;

	public function __construct(App $app, Poll $poll)
	{
		parent::__construct($app);
		$this->poll = $poll;
		$this->contentType = $poll->content_type;
		$this->content = $poll->Content;
		$this->handler = $poll->Handler;
		$this->responseEditor = $poll->getResponseEditor();

		$this->maxResponses = $this->app->options()->pollMaximumResponses;
	}

	public function getPoll()
	{
		return $this->poll;
	}

	public function getContent()
	{
		return $this->content;
	}

	public function setMaxResponses($max)
	{
		$this->maxResponses = $max;
	}

	public function getMaxResponses()
	{
		return $this->maxResponses;
	}

	public function setQuestion($question)
	{
		$this->poll->question = $question;
	}

	public function updateExistingResponses(array $existingResponses)
	{
		$this->responseEditor->updateResponses($existingResponses);
	}

	public function addResponses(array $responses)
	{
		$this->responseEditor->addResponses($responses);
	}

	public function setMaxVotes($type, $value = null)
	{
		$this->poll->setMaxVotes($type, $value);
	}

	public function setCloseDateRelative($value, $unit)
	{
		$this->poll->setCloseDateRelative($value, $unit);
	}

	public function removeCloseDate()
	{
		$this->poll->close_date = 0;
	}

	public function setClose($close, $value, $unit)
	{
		if (!$close)
		{
			$this->poll->close_date = 0;
		}
		else
		{
			if (!$value)
			{
				return;
			}

			$this->poll->close_date = min(
				2 ** 32 - 1,
				strtotime("+$value $unit")
			);
		}
	}

	public function setPublicVotes($public)
	{
		$this->poll->public_votes = $public;
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

		$poll->save();
		$this->responseEditor->saveChanges();

		if ($this->content->User && $this->content->User->user_id != \XF::visitor()->user_id)
		{
			$this->app->logger()->logModeratorAction($this->contentType, $this->content, 'poll_edit');
		}

		return $poll;
	}
}
