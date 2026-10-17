<?php

namespace XF\Job;

trait Retryable
{
	public function attemptLaterOrComplete(?int $previousAttempts = null): JobResult
	{
		$attempts = $previousAttempts ?? $this->getPreviousAttempts();

		$nextTry = $this->calculateNextAttemptDate($attempts);
		if ($nextTry)
		{
			return $this->attemptLater($nextTry);
		}

		return $this->complete();
	}

	public function attemptLater(int $nextTry): JobResult
	{
		return JobResult::newReattempt($this->jobId, $nextTry, $this->data);
	}

	protected function willBeRetried(?int $previousAttempts = null): bool
	{
		$attempts = $previousAttempts ?? $this->getPreviousAttempts();

		return $this->calculateNextAttemptDate($attempts) !== null;
	}

	protected function calculateNextAttemptDate(int $previousAttempts): ?int
	{
		switch ($previousAttempts)
		{
			case 0: $delay = 5 * 60; break; // 5 minutes
			case 1: $delay = 10 * 60; break; // 10 minutes
			case 2: $delay = 20 * 60; break; // 20 minutes
			default: return null; // give up
		}

		return time() + $delay;
	}

	public function getStatusMessage(): string
	{
		return '';
	}

	public function canCancel(): bool
	{
		return false;
	}

	public function canTriggerByChoice(): bool
	{
		return false;
	}
}
