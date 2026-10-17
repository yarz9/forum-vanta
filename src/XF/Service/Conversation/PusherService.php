<?php

namespace XF\Service\Conversation;

use XF\Entity\ConversationMessage;
use XF\Entity\User;
use XF\Service\AbstractService;
use XF\Service\PusherTrait;
use XF\Service\PushNotificationService;

class PusherService extends AbstractService
{
	use PusherTrait;

	/**
	 * @var ConversationMessage
	 */
	protected $message;

	/**
	 * @var string
	 */
	protected $actionType;

	/**
	 * @var User
	 */
	protected $sender;

	protected function setInitialProperties(ConversationMessage $message, $actionType, User $sender)
	{
		$this->message = $message;
		$this->actionType = $actionType;
		$this->sender = $sender;
	}

	protected function getNotificationTitle()
	{
		switch ($this->actionType)
		{
			case 'reply':
				$name = 'reply_to_direct_message_at_x';
				break;

			default:
				$name = 'new_direct_message_at_x';
				break;
		}

		$phrase = $this->language->phrase($name, ['boardTitle' => $this->app->options()->boardTitle]);

		return $phrase->render('raw');
	}

	protected function getNotificationBody()
	{
		$phrase = $this->language->phrase('push_direct_message_' . $this->actionType, [
			'boardTitle' => $this->app->options()->boardTitle,
			'title' => $this->message->Conversation->title,
			'sender' => $this->sender->username,
		]);

		return $phrase->render('raw');
	}

	public function getNotificationUrl()
	{
		return $this->app->router('public')->buildLink(
			'canonical:direct-messages/unread',
			$this->message->Conversation
		);
	}

	protected function setAdditionalOptions(PushNotificationService $pushNotification)
	{
		$user = $this->message->User;
		if ($user)
		{
			$this->setUserOptions($pushNotification, $user);
		}
	}
}
