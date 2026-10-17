<?php

namespace XF\Service\Conversation;

use XF\App;
use XF\Entity\ConversationMaster;
use XF\Entity\ConversationMessage;
use XF\Entity\User;
use XF\PrintableException;
use XF\Repository\ConversationRepository;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class ReplierService extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var ConversationMaster
	 */
	protected $conversation;

	/**
	 * @var ConversationMessage
	 */
	protected $message;

	/**
	 * @var User
	 */
	protected $user;

	/**
	 * @var MessageManagerService
	 */
	protected $messageManager;

	protected $autoSpamCheck = true;
	protected $autoSendNotifications = true;
	protected $performValidations = true;

	public function __construct(App $app, ConversationMaster $conversation, User $user)
	{
		parent::__construct($app);

		$this->conversation = $conversation;
		$this->user = $user;
		$this->message = $conversation->getNewMessage($user);
		$this->messageManager = $this->service(MessageManagerService::class, $this->message);

		$this->setupDefaults();
	}

	protected function setupDefaults()
	{
	}

	public function getConversation()
	{
		return $this->conversation;
	}

	public function getMessage()
	{
		return $this->message;
	}

	public function getMessageManager()
	{
		return $this->messageManager;
	}

	public function setLogIp($logIp)
	{
		$this->messageManager->setLogIp($logIp);
	}

	public function setAutoSpamCheck($check)
	{
		$this->autoSpamCheck = (bool) $check;
	}

	public function setPerformValidations($perform)
	{
		$this->performValidations = (bool) $perform;
	}

	public function getPerformValidations()
	{
		return $this->performValidations;
	}

	public function setIsAutomated()
	{
		$this->setLogIp(false);
		$this->setAutoSpamCheck(false);
		$this->setPerformValidations(false);
	}

	public function setAutoSendNotifications($send)
	{
		$this->autoSendNotifications = (bool) $send;
	}

	public function setMessageContent($message, $format = true)
	{
		return $this->messageManager->setMessage($message, $format, $this->performValidations);
	}

	public function setAttachmentHash($hash)
	{
		$this->messageManager->setAttachmentHash($hash);
	}

	public function checkForSpam()
	{
		if ($this->user->isSpamCheckRequired())
		{
			$this->messageManager->checkForSpam();
		}
	}

	protected function finalSetup()
	{
		$this->message->message_date = time();

		if ($this->autoSpamCheck)
		{
			$this->checkForSpam();
		}
	}

	protected function _validate()
	{
		$this->finalSetup();

		$this->message->preSave();
		return $this->message->getErrors();
	}

	protected function _save()
	{
		$message = $this->message;

		$db = $this->db();
		$db->beginTransaction();

		$convLatest = $this->db()->fetchRow("
			SELECT *
			FROM xf_conversation_master
			WHERE conversation_id = ?
			FOR UPDATE
		", $message->conversation_id);

		if (!$convLatest)
		{
			throw new PrintableException(\XF::phrase('requested_direct_message_not_found'));
		}

		// Ensure our conversation entity has the latest data to make sure things like reply count are correct
		$forceUpdateColumns = [
			'first_message_id',
			'reply_count',
			'last_message_date',
			'last_message_id',
			'last_message_user_id',
			'last_message_username',
		];
		foreach ($forceUpdateColumns AS $forceUpdateColumn)
		{
			$this->conversation->setAsSaved($forceUpdateColumn, $convLatest[$forceUpdateColumn]);
		}

		$message->save(true, false);

		$this->messageManager->afterInsert();
		$this->markReadIfNeeded();

		$db->commit();

		if ($this->autoSendNotifications)
		{
			$this->sendNotifications();
		}

		return $message;
	}

	protected function markReadIfNeeded()
	{
		$userConv = $this->conversation->Users[$this->user->user_id];
		if ($userConv && $userConv->is_unread)
		{
			/** @var ConversationRepository $convRepo */
			$convRepo = $this->repository(ConversationRepository::class);
			$convRepo->markUserConversationRead($userConv, $this->message->message_date);
		}
	}

	public function sendNotifications()
	{
		/** @var NotifierService $notifier */
		$notifier = $this->service(NotifierService::class, $this->conversation);
		$notifier->notifyReply($this->message);
	}
}
