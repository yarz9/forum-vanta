<?php

namespace XF;

use function is_string;

trait MultiPartRunnerTrait
{
	/**
	 * @var int
	 */
	protected $currentStep = 0;

	/**
	 * @var int|null
	 */
	protected $stepLastOffset = null;

	/**
	 * @return array<string|callable(int|null, float): int|false|null>
	 */
	abstract protected function getSteps();

	/**
	 * @param int $currentStep
	 * @param int|null $lastOffset
	 *
	 * @return static
	 */
	public function restoreState($currentStep, $lastOffset)
	{
		$this->currentStep = $currentStep;
		$this->stepLastOffset = $lastOffset;

		return $this;
	}

	/**
	 * @return array{
	 *     string|callable(int|null, float): int|false|null,
	 *     int|null
	 * }|null
	 */
	protected function getRunnableStep()
	{
		$stepId = 0;

		foreach ($this->getSteps() AS $stepCallable)
		{
			if ($stepId < $this->currentStep)
			{
				$stepId++;
				continue;
			}

			return [$stepCallable, $this->stepLastOffset];
		}

		return null;
	}

	/**
	 * @param float $maxRunTime
	 *
	 * @return ContinuationResult
	 */
	protected function runLoop($maxRunTime = 0)
	{
		$start = microtime(true);

		while ($stepInfo = $this->getRunnableStep())
		{
			[$stepCallable, $stepLastOffset] = $stepInfo;
			$remainingTime = $maxRunTime
				? ($maxRunTime - (microtime(true) - $start))
				: 0;

			if (is_string($stepCallable))
			{
				$stepCallable = [$this, $stepCallable];
			}

			if (!is_callable($stepCallable))
			{
				throw new \LogicException('The step was not callable.');
			}

			$stepResult = $stepCallable($stepLastOffset, $remainingTime);

			if ($stepResult === null || $stepResult === false)
			{
				// next step
				$this->currentStep++;
				$this->stepLastOffset = null;
			}
			else
			{
				// step to be continued
				$this->stepLastOffset = $stepResult;
			}

			if ($maxRunTime && microtime(true) - $start > $maxRunTime)
			{
				break;
			}
		}

		if ($this->getRunnableStep())
		{
			return ContinuationResult::continued([
				'currentStep' => $this->currentStep,
				'lastOffset' => $this->stepLastOffset,
			]);
		}

		return ContinuationResult::completed();
	}
}
