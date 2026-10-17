<?php

namespace XF\Search\Query;

use XF\Db\AbstractAdapter;

use function is_array;

class SqlConstraint
{
	/**
	 * @var string
	 */
	protected $condition;

	/**
	 * @var list<mixed>
	 */
	protected $values = [];

	/**
	 * @var array<string, TableReference>
	 */
	protected $tables = [];

	/**
	 * @param string $condition
	 * @param mixed|list<mixed>|null $values
	 */
	public function __construct($condition, $values = null, ?TableReference $table = null)
	{
		$this->condition = $condition;

		if ($values !== null)
		{
			if (!is_array($values))
			{
				$values = [$values];
			}
			$this->values = $values;
		}

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
	public function getCondition()
	{
		return $this->condition;
	}

	/**
	 * @return list<mixed>
	 */
	public function getValues()
	{
		return $this->values;
	}

	/**
	 * @return array<string, TableReference>
	 */
	public function getTables()
	{
		return $this->tables;
	}

	/**
	 * @return string
	 */
	public function getSql(AbstractAdapter $db)
	{
		if ($this->values)
		{
			return vsprintf(
				$this->condition,
				array_map([$db, 'quote'], $this->values)
			);
		}
		else
		{
			return $this->condition;
		}
	}
}
