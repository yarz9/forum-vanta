<?php

namespace XF\Mail;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Email;
use XF\Entity\User;
use XF\Language;

use function count, is_array, strlen;

class Mail
{
	/**
	 * @var Mailer
	 */
	protected $mailer;

	/**
	 * @var Email
	 */
	protected $email;

	/**
	 * @var Language|null
	 */
	protected $language;

	/**
	 * @var User|null
	 */
	protected $toUser;

	protected $bounceHmac;
	protected $bounceVerpBase;

	protected $templateName;
	protected $templateParams = [];
	protected $renderedTemplateName;

	/**
	 * Valid values are 'no', 'auto-generated', 'auto-replied', 'auto-notified'
	 *
	 * @var string
	 */
	protected $autoSubmitted = 'auto-generated';

	/**
	 * @var null|\Exception
	 */
	protected $setupError;

	protected $listUnsubscribeMailtoSet = false;

	protected $listUnsubscribeHttpSet = false;

	public function __construct(Mailer $mailer, $templateName = null, ?array $templateParams = null)
	{
		$this->mailer = $mailer;
		$this->email = new Email();

		if ($templateName)
		{
			$this->templateName = $templateName;
			$this->templateParams = is_array($templateParams) ? $templateParams : [];
		}
	}

	public function setTo($email, $name = null): Mail
	{
		try
		{
			$this->email->to(
				new Address($email, (string) $name)
			);
		}
		catch (\Exception $e)
		{
			$this->applySetupError($e);

			return $this;
		}

		$this->bounceHmac = $this->mailer->calculateBounceHmac($email);

		$headers = $this->email->getHeaders();
		if ($headers->has('X-To-Validate'))
		{
			$headers->remove('X-To-Validate');
		}
		$headers->addTextHeader('X-To-Validate', $this->bounceHmac . '+' . $email);

		$this->applyVerp();

		$this->toUser = null;

		return $this;
	}

	public function setToUser(User $user): Mail
	{
		if (!$user->email)
		{
			$this->setupError = new \Exception("Trying to send email to user without email (ID: $user->user_id)");

			return $this;
		}

		$this->setTo($user->email, $user->username);

		$language = \XF::app()->userLanguage($user);
		$this->setLanguage($language);

		$this->toUser = $user;

		return $this;
	}

	public function getToUser()
	{
		return $this->toUser;
	}

	public function setFrom($email, $name = null): Mail
	{
		try
		{
			$this->email->from(
				new Address($email, (string) $name)
			);
		}
		catch (\Exception $e)
		{
			$this->applySetupError($e);
		}

		return $this;
	}

	public function setReplyTo($email, $name = null): Mail
	{
		try
		{
			$this->email->replyTo(
				new Address($email, (string) $name)
			);
		}
		catch (\Exception $e)
		{
			$this->applySetupError($e);
		}

		return $this;
	}

	public function setReturnPath($email, $useVerp = false): Mail
	{
		$email = preg_replace('/["\'\s\\\\]/', '', $email);

		try
		{
			$this->email->returnPath($email);
		}
		catch (\Exception $e)
		{
			$this->applySetupError($e);
		}

		if ($useVerp)
		{
			$this->bounceVerpBase = $email;
			$this->applyVerp();
		}

		return $this;
	}

	public function setListUnsubscribeFromOption(): Mail
	{
		$options = \XF::options();
		$useVerp = $options->enableVerp;

		$unsubHandling = $options->unsubscribeEmailHandling;
		$unsubEmail = $options->unsubscribeEmailAddress;

		if (!empty($unsubHandling['email']) && $unsubEmail)
		{
			$this->setListUnsubscribe($unsubEmail, $useVerp);
		}

		if (!empty($unsubHandling['http']))
		{
			$this->setListUnsubscribeHttp();
		}

		return $this;
	}

	public function setListUnsubscribe($unsubEmail, $useVerp = false): Mail
	{
		if (!$unsubEmail || !$this->toUser)
		{
			// if we're not sending to an actual user, or no unsub email no point in setting header
			return $this;
		}

		if ($this->listUnsubscribeMailtoSet)
		{
			return $this;
		}

		$unsubEmail = preg_replace('/["\'\s\\\\]/', '', $unsubEmail);
		$hmac = substr($this->toUser->getEmailConfirmKey(), 0, 8);
		$userEmail = $this->toUser->email;

		if ($useVerp)
		{
			$verpAddress = $this->getVerpAddress($hmac, $unsubEmail);
			if ($verpAddress)
			{
				$unsubEmail = $verpAddress;
			}
		}

		// if we have a verp address at this point, great. if not, then pop some query
		// string parameters into the mailto: link for when we parse the requests later.
		$unsubEmailHeaderVal = '<mailto:' . $unsubEmail . '?' . http_build_query([
			'subject' => '[List-Unsubscribe[' . $hmac . ',' . $userEmail . ']]',
		], '', '&', PHP_QUERY_RFC3986) . '>';

		$this->email->getHeaders()->addTextHeader('List-Unsubscribe', $unsubEmailHeaderVal);
		$this->listUnsubscribeMailtoSet = true;

		return $this;
	}

	public function setListUnsubscribeHttp()
	{
		if (!$user = $this->toUser)
		{
			// if we're not sending to an actual user no point in setting header
			return $this;
		}

		if ($this->listUnsubscribeHttpSet)
		{
			return $this;
		}

		$existingHeader = $this->email->getHeaders()->get('List-Unsubscribe') ?? null;
		$newHeaderValue = '';

		if ($existingHeader)
		{
			$newHeaderValue = $existingHeader->getBodyAsString() . ', ';
			$this->email->getHeaders()->remove('List-Unsubscribe');
		}

		$unsubLinkParams = [];
		if ($this->renderedTemplateName
			&& (strpos($this->renderedTemplateName, 'conversation') !== false
			|| strpos($this->renderedTemplateName, 'direct_message') !== false
			|| $this->renderedTemplateName === 'user_email_unsubscribed')
		)
		{
			$unsubLinkParams['include_dm'] = true;
		}

		$newHeaderValue .= '<' . \XF::app()->router('public')->buildLink('canonical:email-stop/unsubscribe', $user, $unsubLinkParams) . '>';

		$this->email->getHeaders()->addTextHeader('List-Unsubscribe', $newHeaderValue);
		$this->email->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
		$this->listUnsubscribeHttpSet = true;

		return $this;
	}

	protected function applyVerp()
	{
		$verpAddress = $this->getVerpAddress($this->bounceHmac, $this->bounceVerpBase);
		if ($verpAddress)
		{
			try
			{
				$this->email->returnPath($verpAddress);
			}
			catch (\Exception $e)
			{
				$this->applySetupError($e);
			}
		}

		return $verpAddress;
	}

	protected function getVerpAddress($hmac, $verpBase, $to = null)
	{
		if (!$hmac || !$verpBase)
		{
			return null;
		}

		if (!$to)
		{
			$toAll = $this->email->getTo();
			if (!$toAll || count($toAll) > 1)
			{
				// 0 or 2+ to addresses, so we can't really do verp
				return null;
			}

			$to = $toAll[0]->getAddress();
		}

		$verpValue = str_replace('@', '=', $to);
		$verpAddress = str_replace('@', "+{$hmac}+$verpValue@", $verpBase);
		$verpAddress = preg_replace('/["\'\s\\\\]/', '', $verpAddress);

		return $verpAddress;
	}

	public function setSender($sender, $name = null): Mail
	{
		try
		{
			$this->email->sender(
				new Address($sender, (string) $name)
			);
		}
		catch (\Exception $e)
		{
			$this->applySetupError($e);
		}

		return $this;
	}

	public function setId($id): Mail
	{
		try
		{
			$this->email->getHeaders()->addIdHeader('Content-ID', $id);
		}
		catch (\Exception $e)
		{
			$this->applySetupError($e);
		}

		return $this;
	}

	public function addHeader($name, $value): Mail
	{
		$this->email->getHeaders()->addTextHeader($name, $value);

		return $this;
	}

	public function setContent($subject, $htmlBody, $textBody = null): Mail
	{
		$htmlBodyStr = (string) $htmlBody;
		$textBodyStr = (string) $textBody;

		if (!strlen($htmlBodyStr) && !strlen($textBodyStr))
		{
			throw new \InvalidArgumentException("Must provide at least one of the HTML and text bodies");
		}

		if ($textBody === null)
		{
			$textBodyStr = $this->mailer->generateTextBody($htmlBodyStr);
		}

		$subject = preg_replace('#[\r\n\t]\s*#', ' ', $subject);
		$subject = preg_replace('#( ){2,}#', ' ', $subject);
		$subject = trim($subject);

		$this->email->subject($subject);

		if (strlen($htmlBodyStr))
		{
			$this->email->html($htmlBodyStr, 'utf-8');
		}
		if (strlen($textBodyStr))
		{
			$this->email->text($textBodyStr, 'utf-8');
		}

		$this->templateName = null;
		$this->templateParams = [];

		return $this;
	}

	public function setTemplate($name, array $params = []): Mail
	{
		$this->templateName = $name;
		$this->templateParams = $params;

		return $this;
	}

	public function getTemplateName()
	{
		return $this->templateName;
	}

	public function renderTemplate(): Mail
	{
		if (!$this->templateName)
		{
			throw new \LogicException("Cannot render an email template without one specified");
		}

		$this->renderedTemplateName = $this->templateName;

		$output = $this->mailer->renderMailTemplate(
			$this->templateName,
			$this->templateParams,
			$this->language,
			$this->toUser
		);

		$this->setContent($output['subject'], $output['html'], $output['text']);

		if ($output['headers'])
		{
			$headers = $this->email->getHeaders();
			foreach ($output['headers'] AS $header => $value)
			{
				$headers->addTextHeader($header, $value);
			}
		}

		return $this;
	}

	public function setLanguage(?Language $language = null): Mail
	{
		$this->language = $language;

		return $this;
	}

	public function getLanguage()
	{
		return $this->language;
	}

	public function setAutoSubmitted(string $autoSubmitted): Mail
	{
		$this->autoSubmitted = $autoSubmitted;

		return $this;
	}

	public function getAutoSubmitted(): string
	{
		return $this->autoSubmitted;
	}

	public function getFromAddress(): ?string
	{
		$from = $this->email->getFrom();

		if (!$from)
		{
			return null;
		}

		return $from[0]->getAddress();
	}

	/**
	 * @return Email
	 */
	public function getEmailObject(): Email
	{
		return $this->email;
	}

	/**
	 * @return Email
	 */
	public function getSendableEmail(): Email
	{
		if ($this->templateName)
		{
			$this->renderTemplate();
		}

		return $this->email;
	}

	protected function contentContainsEmailStop(): bool
	{
		$message = $this->email;
		$user = $this->toUser;

		if (!$user)
		{
			return false;
		}

		$body = $message->getTextBody() . $message->getHtmlBody();

		$regexBase = \XF::app()->router('public')->buildLink('canonical:email-stop/__SENTINEL__', $user);
		$regexBase = preg_quote($regexBase, '#');
		$emailStopRegex = str_replace('__SENTINEL__', '([a-z0-9-]+)?', $regexBase);

		return preg_match("#$emailStopRegex#i", $body) > 0;
	}

	protected function setFinalHeaders()
	{
		$email = $this->email;

		switch ($this->autoSubmitted)
		{
			case 'auto-generated':
			case 'auto-replied':
			case 'auto-notified':
				$email->getHeaders()->addTextHeader('Auto-Submitted', $this->autoSubmitted);
				break;
		}

		$dkimOptions = \XF::options()->emailDkim;
		if ($dkimOptions['enabled']
			&& $dkimOptions['verified']
			&& extension_loaded('openssl')
			&& $dkimOptions['domain'] == substr(strrchr($this->getFromAddress(), '@'), 1)
		)
		{
			$key = \XF::registry()->get('emailDkimKey');

			if ($key)
			{
				$signer = new DkimSigner($key, $dkimOptions['domain'], 'xenforo');
				$email = $signer->sign($email);
			}
		}

		if ($this->contentContainsEmailStop())
		{
			$this->setListUnsubscribeFromOption();
		}

		return $email;
	}

	/**
	 * @param AbstractTransport|null $transport
	 * @param bool                   $allowRetry
	 *
	 * @return false|SentMessage|null
	 */
	public function send(?AbstractTransport $transport = null, $allowRetry = true)
	{
		if ($this->setupError)
		{
			$this->logSetupError($this->setupError);
			return false;
		}

		$email = $this->getSendableEmail();
		if (!$email->getTo())
		{
			return false;
		}

		$email = $this->setFinalHeaders();

		return $this->mailer->send($email, $transport);
	}

	public function queue()
	{
		if ($this->setupError)
		{
			$this->logSetupError($this->setupError);
			return false;
		}

		$email = $this->getSendableEmail();
		if (!$email->getTo())
		{
			return false;
		}

		$email = $this->setFinalHeaders();

		return $this->mailer->queue($email);
	}

	/**
	 * Handles the application of the setup error. Throws the exception immediately in debug mode.
	 * (In normal execution, queues it for logging when the email is sent.)
	 *
	 * @param \Exception $e
	 * @throws \Exception
	 */
	protected function applySetupError(\Exception $e): void
	{
		if (\XF::$debugMode)
		{
			throw $e;
		}

		$this->setupError = $e;
	}

	protected function logSetupError(\Exception $e): void
	{
		$to = $this->email->getTo();

		$toEmails = [];

		foreach ($to AS $address)
		{
			$toEmails[] = $address->getAddress();
		}

		$toEmails = $toEmails ? implode(', ', $toEmails) : '[unknown]';

		\XF::logException($this->setupError, false, "Email to {$toEmails} failed setup:");
	}
}
