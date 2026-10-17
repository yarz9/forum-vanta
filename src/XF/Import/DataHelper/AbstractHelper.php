<?php

namespace XF\Import\DataHelper;

use XF\Import\DataManager;

abstract class AbstractHelper
{
	/**
	 * @var DataManager
	 */
	protected $dataManager;

	public function __construct(DataManager $dataManager)
	{
		$this->dataManager = $dataManager;
	}

	protected function db()
	{
		return $this->dataManager->db();
	}

	protected function em()
	{
		return $this->dataManager->em();
	}
}
