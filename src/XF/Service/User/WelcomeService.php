<?php

namespace XF\Service\User;

use XF\App;
use XF\Entity\ConversationMaster;
use XF\Entity\ConversationRecipient;
use XF\Entity\User;
use XF\Language;
use XF\Mail\Mail;
use XF\Repository\ConversationRepository;
use XF\Service\AbstractService;
use XF\Service\Conversation\CreatorService;
use XF\Service\Conversation\NotifierService;
use XF\Util\Url;

use function is_array, is_string;

class WelcomeService extends AbstractService
{
	/**
	 * @var User
	 */
	protected $user;

	protected $options = [
		'emailEnabled' => false,
		'emailBody' => '',
		'emailTitle' => '',
		'emailFormat' => 'plain',
		'emailFromName' => '',
		'emailFromEmail' => '',

		'messageEnabled' => false,
		'messageParticipants' => [],
		'messageTitle' => '',
		'messageBody' => '',
		'messageOpenInvite' => false,
		'messageLocked' => false,
		'messageDelete' => 'no_delete',
	];

	/**
	 * @var Mail
	 */
	protected $sentMail;

	/**
	 * @var ConversationMaster
	 */
	protected $sentMessage;

	public function __construct(App $app, User $user)
	{
		parent::__construct($app);
		$this->user = $user;
		$this->setOptions($app->options()->registrationWelcome);
	}

	public function setOptions(array $options)
	{
		$this->options = array_merge($this->options, $options);
	}

	/**
	 * @return null|Mail
	 */
	public function getSentMail()
	{
		return $this->sentMail;
	}

	/**
	 * @return null|ConversationMaster
	 */
	public function getSentMessage()
	{
		return $this->sentMessage;
	}

	public function send()
	{
		if ($this->user->user_state != 'valid')
		{
			throw new \LogicException("User must have a valid user_state to send");
		}

		if ($this->options['emailEnabled'])
		{
			$this->sendMail();
		}

		if ($this->options['messageEnabled'])
		{
			$this->sendMessage();
		}
	}

	protected function sendMail()
	{
		if (!$this->user->email)
		{
			return;
		}

		$options = $this->options;

		$language = $this->app->userLanguage($this->user);
		$options['emailBody'] = $this->replacePhrases($options['emailBody'], $language);
		$options['emailTitle'] = $this->replacePhrases($options['emailTitle'], $language);

		if ($options['emailFormat'] == 'html')
		{
			$tokens = $this->prepareTokens();
			$body = $this->replaceTokens($options['emailBody'], $tokens);
			$text = $this->app->mailer()->generateTextBody($body);
		}
		else
		{
			$tokens = $this->prepareTokens(false);
			$text = $this->replaceTokens($options['emailBody'], $tokens);
			$body = nl2br(htmlspecialchars($text));
		}
		$title = $this->replaceTokens($options['emailTitle'], $tokens);

		$mail = $this->getMail($this->user);

		if ($options['emailFromEmail'])
		{
			$mail->setFrom($options['emailFromEmail'], $options['emailFromName']);
		}

		$mail->setTemplate('prepared_email', [
			'title' => $title,
			'htmlBody' => $body,
			'textBody' => $text,
		]);
		$mail->queue();

		$this->sentMail = $mail;
	}

	protected function getMail(User $user)
	{
		return $this->app->mailer()->newMail()->setToUser($user);
	}

	public function sendMessage()
	{
		$options = $this->options;

		$participants = $options['messageParticipants'];
		if (!is_array($participants))
		{
			\XF::logError('Cannot send welcome message as there are no valid participants to send the message from.');
			return;
		}

		$starter = array_shift($participants);

		$starterUser = null;
		if ($starter)
		{
			/** @var User $starterUser */
			$starterUser = $this->em()->find(User::class, $starter);
		}
		if (!$starterUser)
		{
			\XF::logError('Cannot send welcome message as there are no valid participants to send the message from.');
			return;
		}

		$tokens = $this->prepareTokens(false);
		$language = $this->app->userLanguage($this->user);

		$title = $this->replacePhrases($this->replaceTokens($options['messageTitle'], $tokens), $language);
		$body = $this->replacePhrases($this->replaceTokens($options['messageBody'], $tokens), $language);

		$recipients = [];
		if ($participants)
		{
			$recipients = $this->em()->findByIds(User::class, $participants)->toArray();
		}
		$recipients[$this->user->user_id] = $this->user;

		/** @var CreatorService $creator */
		$creator = $this->service(CreatorService::class, $starterUser);
		$creator->setIsAutomated();
		$creator->setOptions([
			'open_invite' => $options['messageOpenInvite'],
			'conversation_open' => !$options['messageLocked'],
		]);
		$creator->setRecipientsTrusted($recipients);
		$creator->setContent($title, $body);
		if (!$creator->validate($errors))
		{
			return;
		}
		$creator->setAutoSendNotifications(false);
		$conversation = $creator->save();

		/** @var ConversationRepository $conversationRepo */
		$conversationRepo = $this->app->repository(ConversationRepository::class);
		$convRecipients = $conversation->getRelationFinder('Recipients')->with('ConversationUser')->fetch();

		$recipientState = ($options['messageDelete'] == 'delete_ignore' ? 'deleted_ignored' : 'deleted');

		/** @var ConversationRecipient $recipient */
		foreach ($convRecipients AS $recipient)
		{
			if ($recipient->user_id == $this->user->user_id)
			{
				continue;
			}

			$conversationRepo->markUserConversationRead($recipient->ConversationUser);

			if ($options['messageDelete'] != 'no_delete')
			{
				$recipient->recipient_state = $recipientState;
				$recipient->save();
			}
		}

		/** @var NotifierService $notifier */
		$notifier = $this->service(NotifierService::class, $conversation);
		$notifier->addNotificationLimit($this->user)->notifyCreate();

		$this->sentMessage = $conversation;
	}

	protected function prepareTokens($escape = true)
	{
		$tokens = [
			'{name}' => $this->user->username,
			'{email}' => Url::emailToUtf8($this->user->email, false),
			'{id}' => $this->user->user_id,
		];

		if ($escape)
		{
			array_walk($tokens, function (&$value)
			{
				if (is_string($value))
				{
					$value = htmlspecialchars($value);
				}
			});
		}

		return $tokens;
	}

	protected function replaceTokens($string, array $tokens)
	{
		return strtr($string, $tokens);
	}

	protected function replacePhrases($string, Language $language)
	{
		return $this->app->stringFormatter()->replacePhrasePlaceholders($string, $language);
	}
}
