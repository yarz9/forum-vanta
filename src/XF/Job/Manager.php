<?php

namespace XF\Job;

use XF\App;
use XF\Db\AbstractAdapter;

use function count, strlen;

class Manager
{
	/**
	 * @var App
	 */
	protected $app;

	/**
	 * @var AbstractAdapter
	 */
	protected $db;

	protected $allowManual;

	protected $forceManual = false;

	protected $uniqueEnqueued = [];

	protected $autoEnqueuedList = [];
	protected $autoBlockingList = [];
	protected $manualEnqueuedList = [];

	protected $autoBlockingMessage = null;

	protected $shutdownRegistered = false;
	protected $runningJob;

	public function __construct(App $app, $allowManual = true, $forceManual = false)
	{
		$this->app = $app;
		$this->db = $app->db();
		$this->allowManual = $allowManual || $forceManual;
		$this->forceManual = $forceManual;
	}

	public function setAllowManual($allowManual)
	{
		$this->allowManual = $allowManual;
	}

	public function setForceManual($forceManual)
	{
		$this->forceManual = $forceManual;
		if ($forceManual)
		{
			$this->setAllowManual(true);
		}
	}

	public function canRunJobs(): bool
	{
		return (\XF::$versionId == $this->app->options()->currentVersionId || !$this->app->config('checkVersion'));
	}

	/**
	 * @param bool $manual
	 * @param int|float $maxRunTime
	 *
	 * @return null|JobResult
	 */
	public function runQueue($manual, $maxRunTime)
	{
		if ($maxRunTime < 2)
		{
			$maxRunTime = 2;
		}

		$runnable = $this->getRunnable($manual);
		$startTime = microtime(true);
		$result = null;

		foreach ($runnable AS $job)
		{
			$remaining = $maxRunTime - (microtime(true) - $startTime);
			if ($remaining < 1)
			{
				break;
			}

			$result = $this->runJobEntry($job, $remaining);
		}

		return $result;
	}

	/**
	 * @param array $ids
	 * @param int|float $maxRunTime
	 * @return array
	 */
	public function runByIds(array $ids, $maxRunTime)
	{
		if ($maxRunTime < 2)
		{
			$maxRunTime = 2;
		}

		$startTime = microtime(true);
		$result = null;

		foreach ($ids AS $k => $id)
		{
			$remaining = $maxRunTime - (microtime(true) - $startTime);
			if ($remaining < 1)
			{
				break;
			}

			$job = $this->getJob($id);
			if ($job && $job['trigger_date'] <= time())
			{
				$result = $this->runJobEntry($job, $remaining);
			}
			else
			{
				$result = null;
			}

			if (!$result || $result->result === JobResult::RESULT_COMPLETED)
			{
				unset($ids[$k]);
			}
		}

		return [
			'result' => $result,
			'remaining' => $ids,
		];
	}

	/**
	 * @param string $key
	 * @param int|float $maxRunTime
	 *
	 * @return null|JobResult
	 */
	public function runUnique($key, $maxRunTime)
	{
		if ($maxRunTime < 2)
		{
			$maxRunTime = 2;
		}

		$job = $this->getUniqueJob($key);
		if ($job)
		{
			return $this->runJobEntry($job, $maxRunTime);
		}
		else
		{
			return null;
		}
	}

	public function runById($id, $maxRunTime)
	{
		if ($maxRunTime < 2)
		{
			$maxRunTime = 2;
		}

		$job = $this->getJob($id);
		if ($job)
		{
			return $this->runJobEntry($job, $maxRunTime);
		}
		else
		{
			return null;
		}
	}

	public function queuePending($manual)
	{
		return count($this->getRunnable($manual)) > 0;
	}

	/**
	 * @param array $job
	 * @param int|float $maxRunTime
	 *
	 * @return JobResult
	 */
	public function runJobEntry(array $job, $maxRunTime)
	{
		$affected = $this->db->update('xf_job', [
			'trigger_date' => time() + 15 * 60,
			'last_run_date' => time(),
		], 'job_id = ? AND trigger_date = ?', [$job['job_id'], $job['trigger_date']]);
		if (!$affected)
		{
			// job has already been taken, treat it as complete
			return JobResult::newComplete($job['job_id']);
		}

		$result = $this->runJobInternal($job, $maxRunTime);

		switch ($result->result)
		{
			case JobResult::RESULT_COMPLETED:
				$this->cancelAndDequeueJob($job);
				break;

			case JobResult::RESULT_RESUME:
			case JobResult::RESULT_REATTEMPT:
				$update = [
					'execute_data' => serialize($result->data),
					'trigger_date' => $result->continueDate ? (int) $result->continueDate : $job['trigger_date'],
					'last_run_date' => time(),
				];

				if ($result->result === JobResult::RESULT_REATTEMPT)
				{
					$update['attempts'] = $job['attempts'] + 1;
				}

				$this->db->update('xf_job', $update, 'job_id = ?', $job['job_id']);

				break;

			case JobResult::RESULT_FAILED:
				$this->cancelAndDequeueJob($job);

				$this->app->db()->insert('xf_failed_job', [
					'execute_class' => $job['execute_class'],
					'execute_data' => serialize($result->data),
					'exception' => $result->exception,
					'fail_date' => time(),
				]);

				break;
		}

		if (!$job['manual_execute'])
		{
			$this->scheduleRunTimeUpdate();
		}

		return $result;
	}

	public function getJobRunner(array $job)
	{
		return $this->app->job($job['execute_class'], $job['job_id'], unserialize($job['execute_data']), $job['attempts']);
	}

	protected function runJobInternal(array $job, $maxRunTime)
	{
		$runner = $this->getJobRunner($job);
		if (!$runner)
		{
			$this->app->logException(new \Exception("Could not get runner for job $job[execute_class] (unique: $job[unique_key]). Skipping."));

			return JobResult::newComplete($job['job_id']);
		}

		if (!$this->shutdownRegistered)
		{
			register_shutdown_function([$this, 'handleShutdown']);
		}

		$this->runningJob = $job;

		try
		{
			$result = $runner->run($maxRunTime);
			$this->runningJob = null;
		}
		catch (\Exception $e)
		{
			$this->runningJob = null;

			$this->db->rollbackAll();

			if ($job['manual_execute'] || $this->app->config()['development']['throwJobErrors'])
			{
				$this->db->update('xf_job', [
					'trigger_date' => $job['trigger_date'],
					'last_run_date' => time(),
				], 'job_id = ?', $job['job_id']);

				throw $e;
			}
			else
			{
				$this->app->logException($e, false, "Job $job[execute_class]: ");
				$result = JobResult::newComplete($job['job_id'], [], "$job[execute_class] threw exception. See error log.");
			}
		}

		if (!($result instanceof JobResult))
		{
			throw new \LogicException("Jobs must return JobResult objects");
		}

		\XF::triggerRunOnce();

		return $result;
	}

	public function handleShutdown()
	{
		if (!$this->runningJob)
		{
			return;
		}

		$job = $this->runningJob;

		try
		{
			// job is being run manually, there's no error which implies a call to exit, or forced re-enqueue
			if ($job['manual_execute'] || !error_get_last() || $this->app->config()['development']['throwJobErrors'])
			{
				$this->db->rollbackAll();

				$this->db->update('xf_job', [
					'trigger_date' => $job['trigger_date'],
					'last_run_date' => time(),
				], 'job_id = ?', $job['job_id']);

				$this->updateNextRunTime();
			}
		}
		catch (\Exception $e)
		{
		}
	}

	public function cancelJob(array $job)
	{
		$rows = $this->db->delete('xf_job', 'job_id = ?', $job['job_id']);
		if ($rows)
		{
			$this->scheduleRunTimeUpdate();
		}
	}

	public function cancelUniqueJob($uniqueId)
	{
		$job = $this->getUniqueJob($uniqueId);
		if ($job)
		{
			$this->cancelJob($job);
			return true;
		}
		else
		{
			return false;
		}
	}

	public function cancelAndDequeueJob(array $job): void
	{
		$this->cancelJob($job);

		unset(
			$this->manualEnqueuedList[$job['job_id']],
			$this->autoEnqueuedList[$job['job_id']],
			$this->autoBlockingList[$job['job_id']]
		);

		if ($job['unique_key'])
		{
			unset($this->uniqueEnqueued[$job['unique_key']]);
		}
	}

	public function getRunnable($manual)
	{
		return $this->db->fetchAll("
			SELECT *
			FROM xf_job
			WHERE trigger_date <= ?
				AND manual_execute = ?
			ORDER BY priority DESC, trigger_date ASC
			LIMIT 1000
		", [\XF::$time, $manual ? 1 : 0]);
	}

	public function getFirstRunnable($manual)
	{
		return $this->db->fetchRow("
			SELECT *
			FROM xf_job
			WHERE trigger_date <= ?
				AND manual_execute = ?
			ORDER BY priority DESC, trigger_date ASC
			LIMIT 1
		", [\XF::$time, $manual ? 1 : 0]);
	}

	public function hasStoppedJobs(): bool
	{
		$pending = $this->queuePending(false);

		if (!$pending)
		{
			return false;
		}

		$jobRunTime = $this->app['job.runTime'];
		if (!$jobRunTime)
		{
			return false;
		}

		if ($jobRunTime + 3600 <= \XF::$time)
		{
			// scheduled run time exceeded by an hour so jobs appear to be stuck
			return true;
		}
		else
		{
			return false;
		}
	}

	public function hasStoppedManualJobs()
	{
		$match = $this->db->fetchRow("
			SELECT job_id
			FROM xf_job
			WHERE trigger_date <= ?
				AND (last_run_date <= ? OR last_run_date IS NULL)
				AND manual_execute = 1
			LIMIT 1
		", [\XF::$time - 15, \XF::$time - 180]);

		return $match ? true : false;
	}

	public function getJob($id)
	{
		return $this->db->fetchRow("
			SELECT *
			FROM xf_job
			WHERE job_id = ?
		", $id);
	}

	public function getUniqueJob($key)
	{
		return $this->db->fetchRow("
			SELECT *
			FROM xf_job
			WHERE unique_key = ?
		", $key);
	}

	public function getFirstAutomaticTime()
	{
		return $this->db->fetchOne("
			SELECT MIN(trigger_date)
			FROM xf_job
			WHERE manual_execute = 0
		");
	}

	public function updateNextRunTime()
	{
		$runTime = $this->getFirstAutomaticTime();
		$this->app->registry()->set('autoJobRun', $runTime);

		return $runTime;
	}

	public function setNextAutoRunTime($time)
	{
		$this->app->registry()->set('autoJobRun', $time);
	}

	public function scheduleRunTimeUpdate()
	{
		\XF::runOnce('autoJobRun', function ()
		{
			$this->updateNextRunTime();
		});
	}

	public function enqueue(string $jobClass, array $params = [], bool $manual = false, int $priority = 100): ?int
	{
		$jobParams = (new JobParams())
			->setJobClass($jobClass)
			->setParams($params)
			->setManual($manual)
			->setPriority($priority);

		return $this->_enqueue($jobParams);
	}

	public function enqueueAutoBlocking(string $jobClass, array $params = [], int $priority = 100): ?int
	{
		$jobParams = (new JobParams())
			->setJobClass($jobClass)
			->setParams($params)
			->setBlocking(true)
			->setPriority($priority);

		return $this->_enqueue($jobParams);
	}

	public function enqueueUnique(string $uniqueId, string $jobClass, array $params = [], bool $manual = true, int $priority = 100): ?int
	{
		$jobParams = (new JobParams())
			->setUniqueId($uniqueId)
			->setJobClass($jobClass)
			->setParams($params)
			->setManual($manual)
			->setPriority($priority);

		return $this->_enqueue($jobParams);
	}

	public function enqueueLater(?string $uniqueId, int $runTime, string $jobClass, array $params = [], bool $manual = false, int $priority = 100): ?int
	{
		$jobParams = (new JobParams())
			->setUniqueId($uniqueId)
			->setJobClass($jobClass)
			->setParams($params)
			->setManual($manual)
			->setRunTime($runTime)
			->setPriority($priority);

		return $this->_enqueue($jobParams);
	}

	protected function prepareJobParams(JobParams $jobParams): JobParams
	{
		return $jobParams;
	}

	protected function _enqueue(JobParams $jobParams): ?int
	{
		$uniqueId = $jobParams->getUniqueId();
		if ($uniqueId)
		{
			if (strlen($uniqueId) > 50)
			{
				$uniqueId = md5($uniqueId);
				$jobParams->setUniqueId($uniqueId);
			}

			if (isset($this->uniqueEnqueued[$uniqueId]))
			{
				return $this->uniqueEnqueued[$uniqueId];
			}
		}
		else
		{
			$uniqueId = null;
		}

		$manual = $jobParams->isManual();
		if ($this->forceManual)
		{
			$manual = true;
		}
		else if (!$this->allowManual)
		{
			$manual = false;
		}
		$jobParams->setManual($manual);

		$runTime = $jobParams->getRunTime();
		if (!$runTime)
		{
			$runTime = \XF::$time;
			$jobParams->setRunTime($runTime);
		}

		$jobParams = $this->prepareJobParams($jobParams);

		$db = $this->db;
		$affected = $db->insert('xf_job', [
			'execute_class' => $jobParams->getJobClass(),
			'execute_data' => serialize($jobParams->getParams()),
			'unique_key' => $uniqueId,
			'manual_execute' => $manual ? 1 : 0,
			'trigger_date' => $runTime,
			'priority' => $jobParams->getPriority(),
		], false, '
			execute_class = VALUES(execute_class),
			execute_data = VALUES(execute_data),
			manual_execute = VALUES(manual_execute),
			trigger_date = VALUES(trigger_date),
			last_run_date = NULL,
			priority = VALUES(priority)
		');

		if ($affected == 1)
		{
			$id = $db->lastInsertId();
		}
		else
		{
			// this is an update
			$id = $db->fetchOne("
				SELECT job_id
				FROM xf_job
				WHERE unique_key = ?
			", $uniqueId);
			if (!$id)
			{
				return null;
			}
		}

		if ($uniqueId)
		{
			$this->uniqueEnqueued[$uniqueId] = $id;
		}

		if ($manual)
		{
			$this->manualEnqueuedList[$id] = $id;
		}
		else
		{
			$blocking = $jobParams->isBlocking();
			if ($blocking)
			{
				$this->autoBlockingList[$id] = $id;
			}
			$this->autoEnqueuedList[$id] = $id;

			$this->scheduleRunTimeUpdate();
		}

		return $id;
	}

	public function hasManualEnqueued()
	{
		return count($this->manualEnqueuedList) > 0;
	}

	public function getManualEnqueued()
	{
		return $this->manualEnqueuedList;
	}

	public function hasAutoEnqueued()
	{
		return count($this->autoEnqueuedList) > 0;
	}

	public function getAutoEnqueued()
	{
		return $this->autoEnqueuedList;
	}

	public function hasAutoBlocking()
	{
		return count($this->autoBlockingList) > 0;
	}

	public function getAutoBlocking()
	{
		return $this->autoBlockingList;
	}

	public function setAutoBlockingMessage($message)
	{
		$this->autoBlockingMessage = $message;
	}

	public function getAutoBlockingMessage()
	{
		return $this->autoBlockingMessage;
	}
}
