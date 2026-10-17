<?php

namespace XF\Job;

use Symfony\Component\Mime\Message;

class MailSend extends AbstractJob
{
	use Retryable;

	protected $defaultData = [
		'email' => null,
	];

	public function run($maxRunTime): JobResult
	{
		if (!$this->data['email'])
		{
			throw new \InvalidArgumentException('Cannot send email without a valid message');
		}

		$mailer = \XF::mailer();
		$email = $this->data['email'];

		if (!($email instanceof Message))
		{
			if (\XF::$debugMode)
			{
				\XF::logError('Queued mail failed to be sent due to a mail_data error. The queued email has been logged to xf_failed_job.');
				return $this->fail();
			}

			\XF::logError('Queued mail failed to be sent due to a mail_data error. The queued email entry has been deleted.');
			return $this->complete();
		}

		$sent = $mailer->send($email);

		if (!$sent)
		{
			return $this->attemptLaterOrComplete();
		}
		return $this->complete();
	}

	protected function calculateNextAttemptDate($previousAttempts): ?int
	{
		switch ($previousAttempts)
		{
			case 0: $delay = 5 * 60; break; // 5 minutes
			case 1: $delay = 1 * 60 * 60; break; // 1 hour
			case 2: $delay = 2 * 60 * 60; break; // 2 hours
			case 3: $delay = 6 * 60 * 60; break; // 6 hours
			case 4: $delay = 12 * 60 * 60; break; // 12 hours
			default: return null; // give up
		}

		return time() + $delay;
	}
}
