<?php

namespace XF\Search;

class MetadataStructure
{
	/**
	 * @var string
	 */
	public const INT = 'int';

	/**
	 * @var string
	 */
	public const FLOAT = 'float';

	/**
	 * @var string
	 */
	public const STR = 'str';

	/**
	 * @var string
	 */
	public const KEYWORD = 'keyword';

	/**
	 * @var string
	 */
	public const BOOL = 'bool';

	/**
	 * @var array<string, array<string, mixed>>
	 */
	protected $fields;

	/**
	 * @param array<string, array<string, mixed>> $fields
	 */
	public function __construct(array $fields = [])
	{
		$this->fields = $fields;
	}

	/**
	 * @param string $name
	 * @param string $type
	 * @param array<string, mixed> $config
	 */
	public function addField($name, $type, array $config = [])
	{
		$config['type'] = $type;
		$this->fields[$name] = $config;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function getFields()
	{
		return $this->fields;
	}
}
