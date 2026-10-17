<?php

namespace XF\Search\Query;

class TypeMetadataConstraint implements MetadataConstraintInterface
{
	/**
	 * @var string
	 */
	public const MATCH_ANY = 'any';

	/**
	 * @var string
	 */
	public const MATCH_NONE = 'none';

	/**
	 * @var list<string>
	 */
	protected $types = [];

	/**
	 * @var string
	 */
	protected $matchType = self::MATCH_ANY;

	/**
	 * @var list<MetadataConstraint>
	 */
	protected $metadataConstraints = [];

	/**
	 * @param list<string> $types
	 */
	public function __construct(
		array $types,
		string $matchType = self::MATCH_ANY
	)
	{
		$this->setTypes($types);
		$this->setMatchType($matchType);
	}

	/**
	 * @param list<string> $types
	 */
	public function setTypes(array $types): void
	{
		if (!$types)
		{
			throw new \LogicException('Must provide at least one type');
		}

		$this->types = $types;
	}

	/**
	 * @return list<string>
	 */
	public function getTypes(): array
	{
		return $this->types;
	}

	public function setMatchType(string $matchType): void
	{
		if ($this->metadataConstraints && $matchType !== static::MATCH_ANY)
		{
			throw new \LogicException(
				'Cannot set "none" match type with metadata constraints'
			);
		}

		switch ($matchType)
		{
			case static::MATCH_ANY:
				$this->matchType = static::MATCH_ANY;
				break;

			case static::MATCH_NONE:
				$this->matchType = static::MATCH_NONE;
				break;

			default:
				throw new \LogicException("Invalid match type '{$matchType}'");
		}
	}

	public function getMatchType(): string
	{
		return $this->matchType;
	}

	public function addMetadataConstraint(MetadataConstraint $constraint): void
	{
		if ($this->matchType !== static::MATCH_ANY)
		{
			throw new \LogicException(
				'Cannot add metadata constraints unless match type is "any"'
			);
		}

		$this->metadataConstraints[] = $constraint;
	}

	/**
	 * @return list<MetadataConstraint>
	 */
	public function getMetadataConstraints(): array
	{
		return $this->metadataConstraints;
	}
}
