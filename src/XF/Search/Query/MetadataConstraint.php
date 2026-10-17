<?php

namespace XF\Search\Query;

use function count, is_array;

class MetadataConstraint implements MetadataConstraintInterface
{
	/**
	 * @var string
	 */
	protected $key = '';

	/**
	 * list<mixed> $values
	 */
	protected $values = [];

	/**
	 * @var int
	 */
	protected $matchType = 1;

	/**
	 * @var int
	 */
	public const MATCH_ANY = 1;

	/**
	 * @var int
	 */
	public const MATCH_ALL = 2;

	/**
	 * @var int
	 */
	public const MATCH_NONE = 3;

	/**
	 * @param string $key
	 * @param mixed|list<mixed> $values
	 * @param int|string $matchType
	 */
	public function __construct($key, $values, $matchType = self::MATCH_ANY)
	{
		$this->key = $key;
		$this->setValues($values);
		$this->setMatchType($matchType);
	}

	/**
	 * @return string
	 */
	public function getKey()
	{
		return $this->key;
	}

	/**
	 * @param mixed|list<mixed> $values
	 */
	public function setValues($values)
	{
		if (!is_array($values))
		{
			$values = [$values];
		}

		if (!count($values))
		{
			throw new \LogicException("Must provide at least 1 metadata value");
		}

		$this->values = $values;
	}

	/**
	 * @return list<mixed>
	 */
	public function getValues()
	{
		return $this->values;
	}

	/**
	 * @param int|string $match
	 */
	public function setMatchType($match)
	{
		switch ($match)
		{
			case self::MATCH_ANY:
			case 'any':
				$this->matchType = self::MATCH_ANY;
				break;

			case self::MATCH_ALL:
			case 'all':
				$this->matchType = self::MATCH_ALL;
				break;

			case self::MATCH_NONE:
			case 'none':
				$this->matchType = self::MATCH_NONE;
				break;

			default:
				throw new \LogicException("Invalid match type '$match'");
		}
	}

	/**
	 * @return int
	 */
	public function getMatchType()
	{
		return $this->matchType;
	}
}
