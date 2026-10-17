<?php

namespace XF\Job;

class JobResult
{
	public const RESULT_COMPLETED = 1;
	public const RESULT_RESUME = 2;
	public const RESULT_REATTEMPT = 3;
	public const RESULT_FAILED = 4;

	public $result;
	public $exception;

	public $jobId;
	public $data;
	public $continueDate;
	public $statusMessage;
	public $canCancel;

	public static function newComplete(?int $jobId, array $data = [], string $statusMessage = '', bool $canCancel = false): JobResult
	{
		$result = new self();

		$result->result = self::RESULT_COMPLETED;
		$result->jobId = $jobId;
		$result->data = $data;
		$result->statusMessage = $statusMessage;
		$result->canCancel = $canCancel;

		return $result;
	}

	public static function newResume(?int $jobId, array $data = [], string $statusMessage = '', bool $canCancel = false): JobResult
	{
		$result = new self();

		$result->result = self::RESULT_RESUME;
		$result->jobId = $jobId;
		$result->data = $data;
		$result->statusMessage = $statusMessage;
		$result->canCancel = $canCancel;

		return $result;
	}

	public static function newReattempt(?int $jobId, int $nextTryDate, array $data = []): JobResult
	{
		$result = new self();

		$result->result = self::RESULT_REATTEMPT;
		$result->jobId = $jobId;
		$result->data = $data;
		$result->continueDate = $nextTryDate;

		return $result;
	}

	public static function newFailed(?int $jobId, array $data = [], ?\Exception $e = null): JobResult
	{
		$result = new self();

		$result->result = self::RESULT_FAILED;
		$result->jobId = $jobId;
		$result->data = $data;
		$result->exception = $e ?: new ManuallyFailedException();

		return $result;
	}

	public function __get(string $name)
	{
		switch ($name)
		{
			case 'completed':
				return (
					$this->result === self::RESULT_COMPLETED ||
					$this->result === self::RESULT_FAILED
				);
		}


		trigger_error(
			'Undefined property: ' . self::class . '::' . $name,
			E_USER_WARNING
		);
		return null;
	}
}
