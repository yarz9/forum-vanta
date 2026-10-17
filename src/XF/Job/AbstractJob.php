<?php

namespace XF\Job;

use XF\App;
use XF\Util\File;

abstract class AbstractJob
{
	/**
	 * @var App
	 */
	protected $app;

	/**
	 * @var int
	 */
	protected $jobId;

	/**
	 * @var array<string, mixed>
	 */
	protected $data;

	/**
	 * @var int
	 */
	protected $previousAttempts;

	/**
	 * @var array<string, mixed>
	 */
	protected $defaultData = [];

	/**
	 * @param float $maxRunTime
	 *
	 * @return JobResult
	 */
	abstract public function run($maxRunTime);
	abstract public function getStatusMessage();

	/**
	 * @return bool
	 */
	abstract public function canCancel();

	/**
	 * @return bool
	 */
	abstract public function canTriggerByChoice();

	/**
	 * @param int $jobId
	 * @param array<string, mixed> $data
	 * @param int $previousAttempts
	 */
	public function __construct(App $app, $jobId, array $data = [], int $previousAttempts = 0)
	{
		@set_time_limit(0);

		$this->app = $app;
		$this->jobId = $jobId;
		$this->data = $this->setupData($data);
		$this->previousAttempts = $previousAttempts;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	protected function setupData(array $data)
	{
		return array_merge($this->defaultData, $data);
	}

	protected function saveIncrementalData()
	{
		$this->app->db->update('xf_job', [
			'execute_data' => serialize($this->data),
		], 'job_id = ?', $this->jobId);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getData()
	{
		return $this->data;
	}

	/**
	 * @return int
	 */
	public function getJobId()
	{
		return $this->jobId;
	}

	public function getPreviousAttempts(): int
	{
		File::cleanUpTempFiles();

		return $this->previousAttempts;
	}

	public function complete(): JobResult
	{
		return JobResult::newComplete($this->jobId);
	}

	public function resume(): JobResult
	{
		return JobResult::newResume(
			$this->jobId,
			$this->data,
			$this->getStatusMessage(),
			$this->canCancel()
		);
	}

	public function fail(?\Exception $e = null): JobResult
	{
		return JobResult::newFailed($this->jobId, $this->data, $e);
	}

	/**
	 * @param int $expected
	 * @param int $done
	 * @param float $start
	 * @param float $maxTime
	 * @param int|null $maxBatch
	 *
	 * @return int
	 */
	public function calculateOptimalBatch($expected, $done, $start, $maxTime, $maxBatch = null)
	{
		$spent = microtime(true) - $start;
		$remaining = $maxTime - $spent;

		$done = min($expected, $done);
		$percentDone = $done / $expected;
		$percentSpent = $spent / $maxTime;

		if ($percentSpent == 0)
		{
			return $maxBatch > 0 ? $maxBatch : $expected;
		}

		if ($percentSpent <= 1 && ($remaining < 1 || $percentSpent >= .9))
		{
			// if 90% finished, keep grabbing that amount
			return max(1, ($percentDone >= .9 ? $expected : $done));
		}

		$newExpected = floor($done / $percentSpent);
		if ($maxBatch > 0)
		{
			$newExpected = min($maxBatch, $newExpected);
		}

		return max(1, $newExpected);
	}
}
