<?php

namespace XF\Mail;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;
use XF\Entity\User;
use XF\Job\MailSend;
use XF\Language;

use function is_array;

class Mailer
{
	/**
	 * @var Templater
	 */
	protected $templater;

	/**
	 * @var AbstractTransport
	 */
	protected $defaultTransport;

	/**
	 * @var Styler|null
	 */
	protected $styler;

	/**
	 * @var bool
	 */
	protected $queue;

	protected $defaultFromEmail;
	protected $defaultFromName;
	protected $defaultReturnPath;
	protected $defaultUseVerp;

	protected $mailClass = Mail::class;

	public function __construct(Templater $templater, AbstractTransport $defaultTransport, ?Styler $styler = null, bool $queue = true)
	{
		$this->templater = $templater;
		$this->defaultTransport = $defaultTransport;
		$this->styler = $styler;
		$this->queue = $queue;
	}

	public function getMailClass()
	{
		return $this->mailClass;
	}

	public function setMailClass($class)
	{
		$this->mailClass = $class;
	}

	public function setDefaultFrom($email, $name = null)
	{
		if ($email)
		{
			$this->defaultFromEmail = $email;
			$this->defaultFromName = $name;
		}
		else
		{
			$this->defaultFromEmail = null;
			$this->defaultFromName = null;
		}
	}

	public function getDefaultFromEmail()
	{
		return $this->defaultFromEmail;
	}

	public function getDefaultFromName()
	{
		return $this->defaultFromName;
	}

	public function setDefaultReturnPath($email, $useVerp = false)
	{
		if ($email)
		{
			$this->defaultReturnPath = $email;
		}
		else
		{
			$this->defaultReturnPath = null;
		}
	}

	public function getDefaultReturnPath()
	{
		return $this->defaultReturnPath;
	}

	public function setDefaultUseVerp(bool $useVerp = false)
	{
		$this->defaultUseVerp = $useVerp;
	}

	public function getDefaultUseVerp()
	{
		return $this->defaultUseVerp;
	}

	/**
	 * @return Mail
	 */
	public function newMail()
	{
		$mailClass = $this->mailClass;
		$mail = new $mailClass($this);
		$this->applyMailDefaults($mail);

		return $mail;
	}

	public function applyMailDefaults(Mail $mail)
	{
		if ($this->defaultFromEmail)
		{
			$mail->setFrom($this->defaultFromEmail, $this->defaultFromName);
		}
		if ($this->defaultReturnPath)
		{
			$mail->setReturnPath($this->defaultReturnPath, $this->defaultUseVerp);
		}
	}

	public function calculateBounceHmac($toEmail)
	{
		return substr(hash_hmac('md5', $toEmail, \XF::config('globalSalt')), 0, 8);
	}

	public function generateTextBody($html)
	{
		if ($this->styler)
		{
			return $this->styler->generateTextBody($html);
		}

		return '';
	}

	public function renderMailTemplate($name, array $params, ?Language $language = null, ?User $toUser = null)
	{
		if (!$language)
		{
			$language = \XF::language();
		}

		$templater = $this->templater;

		// for info purposes
		$params['template'] = $name;

		$output = $this->renderPartialMailTemplate($name, $params, $language, $toUser);
		$parts = $this->pullComponentsFromTemplateOutput($output);

		if (!$parts['text'] && !$parts['html'])
		{
			throw new \LogicException("Template email:$name did not render to anything. It must provide either a text or HTML body.");
		}

		$containerTemplate = $templater->pageParams['template'] ?? 'MAIL_CONTAINER';
		if ($containerTemplate)
		{
			if (!strpos($containerTemplate, ':'))
			{
				$containerTemplate = 'email:' . $containerTemplate;
			}

			$containerParams = array_replace($templater->pageParams, $parts);

			$containerOutput = $templater->renderTemplate($containerTemplate, $containerParams);
			$containerParts = $this->pullComponentsFromTemplateOutput($containerOutput);
		}
		else
		{
			$containerParts = ['subject' => '', 'html' => '', 'text' => ''];
		}

		$subject = $parts['subject'] && $containerParts['subject'] ? $containerParts['subject'] : $parts['subject'];
		$html = $parts['html'] && $containerParts['html'] ? $containerParts['html'] : $parts['html'];
		$text = $parts['text'] && $containerParts['text'] ? $containerParts['text'] : $parts['text'];

		if ($this->styler)
		{
			$html = $this->styler->styleHtml($html, $containerTemplate ? true : false, $language);
		}

		if (isset($templater->pageParams['headers']) && is_array($templater->pageParams['headers']))
		{
			$headers = $templater->pageParams['headers'];
		}
		else
		{
			$headers = [];
		}

		return [
			'subject' => $subject,
			'html' => $html,
			'text' => $text,
			'headers' => $headers,
		];
	}

	public function renderPartialMailTemplate($name, array $params, ?Language $language = null, ?User $toUser = null)
	{
		if (!$language)
		{
			$language = \XF::language();
		}

		$defaultParams = $this->getDefaultTemplateParams($language, $toUser);

		$templater = $this->templater;
		$templater->setLanguage($language);
		$templater->addDefaultParam('xf', $defaultParams);
		$templater->pageParams = [];

		return $templater->renderTemplate("email:$name", $params);
	}

	protected function getDefaultTemplateParams(Language $language, ?User $toUser = null)
	{
		return [
			'language' => $language,
			'isRtl' => $language->isRtl(),
			'options' => \XF::options(),
			'toUser' => $toUser,
		];
	}

	protected function pullComponentsFromTemplateOutput($output)
	{
		if (preg_match('#<mail:subject>(.*)</mail:subject>#siU', $output, $match))
		{
			$subject = trim(htmlspecialchars_decode($match[1], ENT_QUOTES));
			$output = preg_replace('#<mail:subject>.*</mail:subject>#siU', '', $output);
		}
		else
		{
			$subject = '';
		}

		if (preg_match('#<mail:text>(.*)</mail:text>#siU', $output, $match))
		{
			$text = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML401, "utf-8"));
			$output = preg_replace('#<mail:text>.*</mail:text>#siU', '', $output);
		}
		else
		{
			$text = '';
		}

		if (preg_match('#<mail:html>(.*)</mail:html>#siU', $output, $match))
		{
			$html = trim($match[1]);
		}
		else
		{
			$html = trim($output);
		}

		if (!$text && $html)
		{
			$text = $this->generateTextBody($html);
		}

		return [
			'subject' => $subject,
			'html' => $html,
			'text' => $text,
		];
	}

	public function send(Message $email, ?AbstractTransport $transport = null)
	{
		$email = MessageConverter::toEmail($email);

		if (!$transport)
		{
			$transport = $this->defaultTransport;
		}

		$sent = false;

		try
		{
			$sent = $transport->send($email);

			if (!$sent)
			{
				throw new TransportException('Unable to send mail.');
			}
		}
		catch (\Throwable $e)
		{
			$toEmails = implode(', ', array_map(
				function (Address $address): string
				{
					return $address->getAddress();
				},
				$email->getTo()
			));
			$fromEmail = implode(', ', array_map(
				function (Address $address): string
				{
					return $address->getAddress();
				},
				$email->getFrom()
			));

			\XF::logException($e, false, "Email to {$toEmails} from {$fromEmail} failed:");
		}

		return $sent;
	}

	public function queue(Message $email)
	{
		if (!$this->queue)
		{
			// Queue may be disabled in config.php so skip straight to send
			return $this->send($email);
		}
		return \XF::app()->jobManager()->enqueue(MailSend::class, ['email' => $email]);
	}

	public function getDefaultTransport()
	{
		return $this->defaultTransport;
	}

	public function setDefaultTransport(AbstractTransport $transport)
	{
		$this->defaultTransport = $transport;
	}

	public static function getTransportFromOption($type, array $config)
	{
		switch ($type)
		{
			case 'smtp':
				$factory = new EsmtpTransportFactory();

				// older option values may have the port as a string
				$config['smtpPort'] = isset($config['smtpPort'])
					? (int) $config['smtpPort']
					: null;

				/** @var EsmtpTransport $transport */
				$transport = $factory->create(new Dsn(
					$config['smtpSsl'] ? 'smtps' : 'smtp',
					$config['smtpHost'],
					$config['smtpLoginUsername'] ?? null,
					$config['smtpLoginPassword'] ?? null,
					$config['smtpPort'] ?? null
				));

				// symfony forces ssl for port 465
				if (($config['smtpPort'] ?? null) === 465 && !$config['smtpSsl'])
				{
					/** @var SocketStream $stream */
					$stream = $transport->getStream();
					$stream->disableTls();
				}

				return $transport;

			case 'file':
				$transport = new FileTransport();
				if (!empty($config['path']))
				{
					$transport->setSavePath($config['path']);
				}

				return $transport;

			case 'sendmail':
			default:
				if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
				{
					$iniSmtpHost = ini_get('SMTP');
					$iniSmtpPort = ini_get('smtp_port');

					$factory = new EsmtpTransportFactory();
					$transport = $factory->create(new Dsn(
						'',
						$iniSmtpHost ?: 'localhost',
						null,
						null,
						$iniSmtpPort ?: 25
					));
				}
				else
				{
					$sendmailPath = \XF::app()->config('sendmailPath');
					if (!$sendmailPath)
					{
						$sendmailPath = ini_get('sendmail_path');
					}

					if ($sendmailPath && !preg_match('# -(t|bs)#', $sendmailPath))
					{
						// Symfony Mailer requires -t or -bs, so if there isn't one, add -t automatically to prevent errors
						$sendmailPath .= ' -t';
					}

					if (preg_match('/(.*)-f\s?[^@]+@[^\s]+(.*)$/', $sendmailPath, $matches))
					{
						// if the sendmail path already contains the -f parameter, Symfony Mailer won't override it in which
						// case, we should remove it by default so it can be set automatically to the appropriate value
						$sendmailPath = trim(rtrim($matches[1]) . $matches[2]);
					}

					if (!$sendmailPath)
					{
						$sendmailPath = '/usr/sbin/sendmail -t -i';
					}

					$transport = new SendmailTransport($sendmailPath);
				}

				return $transport;
		}
	}
}
