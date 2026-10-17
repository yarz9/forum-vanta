<?php

namespace XF\Search\Query;

class TableReference
{
	/**
	 * @var string
	 */
	protected $alias;

	/**
	 * @var string
	 */
	protected $table;

	/**
	 * @var string
	 */
	protected $condition;

	/**
	 * @param string $alias
	 * @param string $table
	 * @param string $condition
	 */
	public function __construct($alias, $table, $condition)
	{
		$this->alias = $alias;
		$this->table = $table;
		$this->condition = $condition;
	}

	/**
	 * @return string
	 */
	public function getAlias()
	{
		return $this->alias;
	}

	/**
	 * @return string
	 */
	public function getTable()
	{
		return $this->table;
	}

	/**
	 * @return string
	 */
	public function getCondition()
	{
		return $this->condition;
	}
}
