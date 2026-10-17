<?php

namespace XF\Job;

class JobParams
{
	/**
	 * @var string|null
	 */
	protected $uniqueId;

	/**
	 * @var string
	 */
	protected $jobClass;

	/**
	 * @var array
	 */
	protected $params;

	/**
	 * @var bool
	 */
	protected $manual = false;

	/**
	 * @var int|null
	 */
	protected $runTime;

	/**
	 * @var bool
	 */
	protected $blocking = false;

	/**
	 * @var int
	 */
	protected $priority = 100;

	public function getUniqueId(): ?string
	{
		return $this->uniqueId;
	}

	public function setUniqueId(?string $uniqueId): JobParams
	{
		$this->uniqueId = $uniqueId;

		return $this;
	}

	public function getJobClass(): string
	{
		return $this->jobClass;
	}

	public function setJobClass(string $jobClass): JobParams
	{
		$this->jobClass = $jobClass;

		return $this;
	}

	public function getParams(): array
	{
		return $this->params;
	}

	public function setParams(array $params): JobParams
	{
		$this->params = $params;

		return $this;
	}

	public function isManual(): bool
	{
		return $this->manual;
	}

	public function setManual(bool $manual): JobParams
	{
		$this->manual = $manual;

		return $this;
	}

	public function getRunTime(): ?int
	{
		return $this->runTime;
	}

	public function setRunTime(?int $runTime): JobParams
	{
		$this->runTime = $runTime;

		return $this;
	}

	public function isBlocking(): bool
	{
		return $this->blocking;
	}

	public function setBlocking(bool $blocking): JobParams
	{
		$this->blocking = $blocking;

		return $this;
	}

	public function getPriority(): int
	{
		return $this->priority;
	}

	public function setPriority(int $priority): JobParams
	{
		$this->priority = $priority;

		return $this;
	}
}
