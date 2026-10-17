<?php

namespace XF\Service\User;

use XF\App;
use XF\Entity\User;
use XF\Entity\Warning;
use XF\Entity\WarningDefinition;
use XF\Mvc\Entity\Entity;
use XF\Repository\WarningRepository;
use XF\Service\AbstractService;
use XF\Service\Conversation\CreatorService;
use XF\Service\StructuredText\PreparerService;
use XF\Service\ValidateAndSavableTrait;
use XF\Warning\AbstractHandler;

use function intval, strlen, strval;

class WarnService extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var User
	 */
	protected $user;

	/**
	 * @var User
	 */
	protected $warningBy;

	/**
	 * @var AbstractHandler
	 */
	protected $handler;

	/**
	 * @var Entity
	 */
	protected $content;

	/**
	 * @var WarningDefinition|null
	 */
	protected $definition;

	/**
	 * @var Warning
	 */
	protected $warning;

	protected $conversationTitle;
	protected $conversationMessage;
	protected $conversationOptions = [];

	protected $contentAction;
	protected $contentActionOptions = [];

	public function __construct(App $app, User $user, $contentType, Entity $content, User $warningBy)
	{
		parent::__construct($app);

		$this->user = $user;
		$this->warningBy = $warningBy;
		$this->handler = $this->repository(WarningRepository::class)->getWarningHandler($contentType, true);
		$this->content = $content;

		$warning = $this->em()->create(Warning::class);
		$warning->content_type = $this->handler->getContentType();
		$warning->content_id = $this->content->getEntityId();
		$warning->content_title = $this->handler->getStoredTitle($this->content);
		$warning->user_id = $this->user->user_id;
		$warning->warning_user_id = $warningBy->user_id;

		$this->warning = $warning;
	}

	public function setFromDefinition(WarningDefinition $definition, $points = null, $expiry = null)
	{
		$this->definition = $definition;
		$this->warning->warning_definition_id = $definition->warning_definition_id;
		$this->warning->title = strval($definition->title);
		$this->warning->extra_user_group_ids = $this->definition->extra_user_group_ids;

		if ($points === null || !$definition->is_editable)
		{
			$points = $definition->points_default;
		}

		if ($expiry === null || !$definition->is_editable)
		{
			if ($definition->expiry_type == 'never')
			{
				$expiry = 0;
			}
			else
			{
				$expiry = strtotime('+' . $definition->expiry_default . ' ' . $definition->expiry_type);
			}
		}

		// if the expiry is too far in the future, just make it actually be permanent
		if ($expiry >= 2 ** 32 - 1)
		{
			$expiry = 0;
		}

		$this->warning->points = max(0, intval($points));
		$this->warning->expiry_date = max(0, intval($expiry));

		return $this;
	}

	public function setFromCustom($title, $points, $expiry)
	{
		$this->definition = null;
		$this->warning->warning_definition_id = 0;
		$this->warning->title = $title;

		// if the expiry is too far in the future, just make it actually be permanent
		if ($expiry >= 2 ** 32 - 1)
		{
			$expiry = 0;
		}

		$this->warning->points = max(0, intval($points));
		$this->warning->expiry_date = max(0, intval($expiry));
		$this->warning->extra_user_group_ids = [];

		return $this;
	}

	public function setNotes($notes)
	{
		$preparer = $this->getStructuredTextPreparer();
		$this->warning->notes = $preparer->prepare($notes);

		$preparer->pushEntityErrorIfInvalid($this->warning, 'notes');

		return $this;
	}

	public function withConversation($title, $message, array $options = [])
	{
		if (!strlen($title) || !strlen($message))
		{
			throw new \InvalidArgumentException("Must specify a title and message to send a conversation");
		}

		$this->conversationTitle = $title;
		$this->conversationMessage = $message;
		$this->conversationOptions = $options;

		return $this;
	}

	public function withContentAction($action, array $options = [])
	{
		$this->contentAction = $action;
		$this->contentActionOptions = $options;

		return $this;
	}

	protected function _validate()
	{
		$this->warning->preSave();
		return $this->warning->getErrors();
	}

	protected function _save()
	{
		if ($this->warning->isUpdate())
		{
			throw new \LogicException("This warning has already been saved");
		}

		$warning = $this->warning;

		$warning->save();

		if ($this->conversationTitle)
		{
			$this->sendConversation($warning);
		}

		if ($this->contentAction)
		{
			$this->handler->takeContentAction(
				$this->content,
				$this->contentAction,
				$this->contentActionOptions
			);
		}

		return $warning;
	}

	/**
	 * @return CreatorService
	 */
	protected function setupConversation(Warning $warning)
	{
		/** @var CreatorService $creator */
		$creator = $this->service(CreatorService::class, $this->warningBy);
		$creator->setRecipientsTrusted($this->user);
		$creator->setContent($this->conversationTitle, $this->conversationMessage);
		$creator->setOptions($this->conversationOptions);
		$creator->setIsAutomated();

		return $creator;
	}

	protected function sendConversation(Warning $warning)
	{
		$creator = $this->setupConversation($warning);
		if ($creator->validate($errors))
		{
			return $creator->save();
		}
		else
		{
			return null;
		}
	}

	protected function getStructuredTextPreparer($format = true)
	{
		/** @var PreparerService $preparer */
		$preparer = $this->service(PreparerService::class, 'warning', $this->warning);
		$preparer->setConstraint('maxLength', 0);
		//$preparer->disableFilter('mentions');
		if (!$format)
		{
			$preparer->disableAllFilters();
		}

		return $preparer;
	}
}
