<?php

namespace XF\Job;

use XF\Entity\User;
use XF\Language;
use XF\Mail\Mail;
use XF\Util\Url;

use function is_string;

class UserEmail extends AbstractUserCriteriaJob
{
	protected $defaultData = [
		'email' => [],
	];

	protected function executeAction(User $user)
	{
		if (!$user->email)
		{
			return;
		}

		$options = $this->app->options();
		$language = $this->app->userLanguage($user);
		$email = $this->data['email'];

		$email = array_replace([
			'from_name' => $options->emailSenderName ?: $options->boardTitle,
			'from_email' => $options->defaultEmailAddress,
			'email_body' => '',
			'email_title' => '',
			'email_format' => 'text',
			'email_wrapped' => true,
			'email_unsub' => false,
		], $email);

		$email['email_body'] = $this->replacePhrases($email['email_body'], $language);
		$email['email_title'] = $this->replacePhrases($email['email_title'], $language);

		if ($email['email_format'] == 'html')
		{
			if ($email['email_unsub'])
			{
				$email['email_body'] .= "\n\n<div class=\"footer\" align=\"left\"><div class=\"minorText\">"
					. $language->renderPhrase('prepared_email_html_footer', [
						'unsub' => '{unsub}',
						'unsub_all' => '{unsub_all}',
					])
					. '</div></div>';
			}

			$tokens = $this->prepareTokens($user, true);
			$html = strtr($email['email_body'], $tokens);
			$text = $this->app->mailer()->generateTextBody($html);
		}
		else
		{
			if ($email['email_unsub'])
			{
				$email['email_body'] .= "\n\n"
					. $language->renderPhrase('prepared_email_text_footer', [
						'unsub' => '{unsub}',
						'unsub_all' => '{unsub_all}',
					]);
			}

			$tokens = $this->prepareTokens($user, false);
			$text = strtr($email['email_body'], $tokens);
			$html = null;
		}

		$titleTokens = $this->prepareTokens($user, false);
		$title = strtr($email['email_title'], $titleTokens);

		$mail = $this->getMail($user)->setFrom($email['from_email'], $email['from_name']);
		$mail->setTemplate('prepared_email', [
			'title' => $title,
			'htmlBody' => $html,
			'textBody' => $text,
			'raw' => !$email['email_wrapped'],
		]);
		$mail->send();
	}

	protected function getActionDescription()
	{
		$actionPhrase = \XF::phrase('emailing');
		$typePhrase = \XF::phrase('users');

		return sprintf('%s... %s', $actionPhrase, $typePhrase);
	}

	protected function wrapTransaction()
	{
		return false;
	}

	protected function prepareTokens(User $user, $escape)
	{
		$unsubLink = $this->app->router('public')->buildLink('canonical:email-stop/mailing-list', $user);
		$unsubAllLink = $this->app->router('public')->buildLink('canonical:email-stop/all', $user);

		$tokens = [
			'{name}' => $user->username,
			'{email}' => Url::emailToUtf8($user->email, false),
			'{id}' => $user->user_id,
			'{unsub}' => $unsubLink,
			'{unsub_all}' => $unsubAllLink,
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

	protected function replacePhrases($string, Language $language)
	{
		return $this->app->stringFormatter()->replacePhrasePlaceholders($string, $language);
	}

	/**
	 * @param User $user
	 *
	 * @return Mail
	 */
	protected function getMail(User $user)
	{
		$mailer = $this->app->mailer();
		$mail = $mailer->newMail();

		$mail->setToUser($user);

		return $mail;
	}

	public function canCancel()
	{
		return true;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}
