<?php

namespace XF\Service\Conversation;

use XF\App;
use XF\Entity\ConversationMaster;
use XF\Entity\User;
use XF\Repository\ConversationRepository;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

use function count, intval, is_int;

class InviterService extends AbstractService
{
	use ValidateAndSavableTrait;

	/** @var  ConversationMaster */
	protected $conversation;

	/** @var User */
	protected $from;

	/** @var ConversationRepository */
	protected $conversationRepo;

	protected $recipients = [];
	protected $notifyUsers = [];
	protected $errors = [];

	protected $autoSendNotifications = true;

	protected $overrideMaxAllowed = null;

	public function __construct(App $app, ConversationMaster $conversation, User $from)
	{
		parent::__construct($app);

		$this->conversation = $conversation;
		$this->from = $from;
		$this->conversationRepo = $this->repository(ConversationRepository::class);
	}

	public function getConversation()
	{
		return $this->conversation;
	}

	public function setAutoSendNotifications($send)
	{
		$this->autoSendNotifications = (bool) $send;
	}

	public function overrideMaxAllowed($override)
	{
		if ($override !== null)
		{
			$override = intval($override);
		}
		$this->overrideMaxAllowed = $override;
	}

	public function setRecipients($recipients, $checkPrivacy = true, $triggerErrors = true)
	{
		$this->recipients = $this->conversationRepo->getValidatedRecipients(
			$recipients,
			$this->from,
			$error,
			$checkPrivacy
		);

		if ($triggerErrors)
		{
			if ($error)
			{
				$this->errors = [$error];
			}
			else
			{
				if (is_int($this->overrideMaxAllowed))
				{
					$maxAllowed = $this->overrideMaxAllowed;
				}
				else
				{
					$maxAllowed = $this->conversation->getRemainingRecipientsCount($this->from);
				}

				if ($maxAllowed > -1 && count($this->recipients) > $maxAllowed)
				{
					$error = \XF::phrase(
						'you_may_only_invite_x_members_to_join_this_direct_message',
						['count' => $maxAllowed]
					);
					$this->errors = [$error];
				}
			}
		}
	}

	public function setRecipientsTrusted($recipients)
	{
		$this->setRecipients($recipients, false, false);
	}

	public function getRecipients()
	{
		return $this->recipients;
	}

	protected function _validate()
	{
		return $this->errors;
	}

	protected function _save()
	{
		if (!$this->recipients)
		{
			return true;
		}

		$this->notifyUsers = $this->conversationRepo->insertRecipients(
			$this->conversation,
			$this->recipients,
			$this->from
		);

		if ($this->autoSendNotifications)
		{
			$this->sendNotifications();
		}

		return true;
	}

	public function sendNotifications()
	{
		/** @var NotifierService $notifier */
		$notifier = $this->service(NotifierService::class, $this->conversation);
		$notifier->notifyInvite($this->notifyUsers, $this->from);
	}
}
