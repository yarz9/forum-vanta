<?php

namespace XF\Less\Tree;

use Less_Environment as Environment;
use Less_Tree as Tree;
use Less_Tree_Call as Call;

use function count;

class HslClamp extends Tree
{
	/**
	 * @var string
	 */
	public $type = 'HslClamp';

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
		if ($call->name !== 'clamp')
		{
			throw new \InvalidArgumentException(
				'Call must be a clamp call, ' . $call->name . ' given'
			);
		}

		return new self($call->args, $call->index, $call->currentFileInfo);
	}

	public static function toCall(self $self): Call
	{
		return new Call(
			'clamp',
			$self->args,
			$self->index,
			$self->currentFileInfo
		);
	}

	public function setMin(Tree $min): self
	{
		return new self(
			[
				$min,
				$this->getValue(),
				$this->getMax(),
			],
			$this->index,
			$this->currentFileInfo
		);
	}

	public function getMin(): ?Tree
	{
		return $this->args[0] ?? null;
	}

	public function setValue(Tree $value): self
	{
		return new self(
			[
				$this->getMin(),
				$value,
				$this->getMax(),
			],
			$this->index,
			$this->currentFileInfo
		);
	}

	public function getValue(): ?Tree
	{
		return $this->args[1] ?? null;
	}

	public function setMax(Tree $max): self
	{
		return new self(
			[
				$this->getMin(),
				$this->getValue(),
				$max,
			],
			$this->index,
			$this->currentFileInfo
		);
	}

	public function getMax(): ?Tree
	{
		return $this->args[2] ?? null;
	}

	/**
	 * @param \Less_Visitor $visitor
	 */
	public function accept($visitor): void
	{
		$this->args = $visitor->visitArray($this->args);
	}

	/**
	 * @param Environment|null $env
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

		if (count($args) !== 3)
		{
			$call = self::toCall($this);
			return $call->compile($env);
		}

		[$min, $value, $max] = $args;

		return new self(
			[
				$min,
				$value,
				$max,
			],
			$this->index,
			$this->currentFileInfo
		);
	}

	/**
	 * @param \Less_Output $output
	 */
	public function genCss($output): void
	{
		$output->add('clamp(', $this->currentFileInfo, $this->index);

		$this->getMin()->genCss($output);

		$output->add(Environment::$_outputMap[',']);
		$this->getValue()->genCss($output);

		$output->add(Environment::$_outputMap[',']);
		$this->getMax()->genCss($output);

		$output->add(')');
	}
}
