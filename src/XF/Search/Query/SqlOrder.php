<?php

namespace XF\Search\Query;

class SqlOrder
{
	/**
	 * @var string
	 */
	protected $order;

	/**
	 * @var array<string, TableReference>
	 */
	protected $tables = [];

	/**
	 * @param string $order
	 */
	public function __construct($order, ?TableReference $table = null)
	{
		$this->order = $order;

		if ($table)
		{
			$this->tables[$table->getAlias()] = $table;
		}
	}

	public function addTable(TableReference $table)
	{
		$this->tables[$table->getAlias()] = $table;
	}

	/**
	 * @return string
	 */
	public function getOrder()
	{
		return $this->order;
	}

	/**
	 * @return array<string, TableReference>
	 */
	public function getTables()
	{
		return $this->tables;
	}
}
