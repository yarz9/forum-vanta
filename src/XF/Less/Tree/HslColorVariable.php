<?php

namespace XF\Less\Tree;

use Less_Tree as Tree;
use Less_Tree_Call as Call;
use Less_Tree_Keyword as Keyword;

use function count;

class HslColorVariable extends Tree
{
	/**
	 * @var string
	 */
	protected const VALUE_REGEX = '/^--(?P<name>[a-z0-9-_]+)(--(?P<component>[hsla]))?$/iU';

	/**
	 * @var string
	 */
	public $type = 'HslColorVariable';

	/**
	 * @var Tree[]
	 */
	public $args;

	/**
	 * @var int
	 */
	public $index;

	/**
	 * @var string[]|null
	 */
	public $currentFileInfo;

	/**
	 * @param Tree[] $args
	 * @param string[]|null $currentFileInfo
	 */
	public function __construct(
		array $args,
		int $index,
		?array $currentFileInfo = null
	)
	{
		$this->args = $args;

		$this->index = $index;
		$this->currentFileInfo = $currentFileInfo;
	}

	public static function fromCall(Call $call): self
	{
		if ($call->name !== 'var')
		{
			throw new \InvalidArgumentException(
				'Call must be a var call, ' . $call->name . ' given'
			);
		}

		return new self($call->args, $call->index, $call->currentFileInfo);
	}

	public static function toCall(self $self): Call
	{
		return new Call(
			'var',
			$self->args,
			$self->index,
			$self->currentFileInfo
		);
	}

	public function setName(string $name): self
	{
		$value = '--' . $name;

		$component = $this->getComponent();
		if ($component)
		{
			$value .= '--' . $component;
		}

		return new self(
			[new Keyword($value)],
			$this->index,
			$this->currentFileInfo
		);
	}

	public function getName(): ?string
	{
		if (!preg_match(static::VALUE_REGEX, $this->args[0]->value, $matches))
		{
			return null;
		}

		return $matches['name'];
	}

	public function setComponent(?string $component): self
	{
		$value = '--' . $this->getName();

		if ($component)
		{
			$value .= '--' . $component;
		}

		return new self(
			[new Keyword($value)],
			$this->index,
			$this->currentFileInfo
		);
	}

	public function getComponent(): ?string
	{
		if (!preg_match(static::VALUE_REGEX, $this->args[0]->value, $matches))
		{
			return null;
		}

		return $matches['component'] ?? null;
	}

	/**
	 * @param \Less_Visitor $visitor
	 */
	public function accept($visitor): void
	{
		$this->args = $visitor->visitArray($this->args);
	}

	/**
	 * @param \Less_Environment|null $env
	 */
	public function compile($env = null): Tree
	{
		$args = array_map(
			function ($argument) use ($env)
			{
				return $argument->compile($env);
			},
			$this->args
		);

		if (count($args) !== 1)
		{
			$call = self::toCall($this);
			return $call->compile($env);
		}

		$arg = $args[0];
		if (!($arg instanceof Keyword))
		{
			$call = self::toCall($this);
			return $call->compile($env);
		}

		if (!preg_match(static::VALUE_REGEX, $arg->value, $matches))
		{
			$call = self::toCall($this);
			return $call->compile($env);
		}

		return new self($args, $this->index, $this->currentFileInfo);
	}

	/**
	 * @param \Less_Output $output
	 */
	public function genCss($output): void
	{
		$output->add('var(', $this->currentFileInfo, $this->index);

		$output->add('--' . $this->getName());

		$component = $this->getComponent();
		if ($component)
		{
			$output->add('--' . $component);
		}

		$output->add(')');
	}
}
