<?php

namespace XF\Less\Tree;

use Less_Environment as Environment;
use Less_Parser as Parser;
use Less_Tree as Tree;
use Less_Tree_Call as Call;
use Less_Tree_Color as Color;
use Less_Tree_Dimension as Dimension;

use function count;

class HslColor extends Tree
{
	/**
	 * @var string
	 */
	public $type = 'HslColor';

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
		if ($call->name !== 'hsl')
		{
			throw new \InvalidArgumentException(
				'Call must be a HSL call, ' . $call->name . ' given'
			);
		}

		return new self($call->args, $call->index, $call->currentFileInfo);
	}

	public static function toCall(self $self): Call
	{
		return new Call(
			'hsl',
			$self->args,
			$self->index,
			$self->currentFileInfo
		);
	}

	public static function fromColor(Color $color): self
	{
		$hsl = $color->toHSL();

		return new self(
			[
				new Dimension(round($hsl['h'])),
				new Dimension(round($hsl['s'] * 100), '%'),
				new Dimension(round($hsl['l'] * 100), '%'),
				new Dimension(round($hsl['a'], 2)),
			],
			0
		);
	}

	public function setHue(Tree $hue): self
	{
		return new self(
			[
				$hue,
				$this->getSaturation(),
				$this->getLight(),
				$this->getAlpha(),
			],
			$this->index,
			$this->currentFileInfo
		);
	}

	public function getHue(): ?Tree
	{
		return $this->args[0] ?? null;
	}

	public function setSaturation(Tree $saturation): self
	{
		return new self(
			[
				$this->getHue(),
				$saturation,
				$this->getLight(),
				$this->getAlpha(),
			],
			$this->index,
			$this->currentFileInfo
		);
	}

	public function getSaturation(): ?Tree
	{
		return $this->args[1] ?? null;
	}

	public function setLight(Tree $light): self
	{
		return new self(
			[
				$this->getHue(),
				$this->getSaturation(),
				$light,
				$this->getAlpha(),
			],
			$this->index,
			$this->currentFileInfo
		);
	}

	public function getLight(): ?Tree
	{
		return $this->args[2] ?? null;
	}

	public function setAlpha(Tree $alpha): self
	{
		return new self(
			[
				$this->getHue(),
				$this->getSaturation(),
				$this->getLight(),
				$alpha,
			],
			$this->index,
			$this->currentFileInfo
		);
	}

	public function getAlpha(): ?Tree
	{
		return $this->args[3] ?? null;
	}

	public function isSimpleColor(): bool
	{
		$hue = $this->getHue();
		$saturation = $this->getSaturation();
		$light = $this->getLight();
		$alpha = $this->getAlpha();

		return (
			$hue instanceof Dimension &&
			$saturation instanceof Dimension &&
			$light instanceof Dimension &&
			$alpha instanceof Dimension
		);
	}

	public function isSimpleColorVariable(): bool
	{
		$hue = $this->getHue();
		$saturation = $this->getSaturation();
		$light = $this->getLight();
		$alpha = $this->getAlpha();

		if (
			!($hue instanceof HslColorVariable) ||
			!($saturation instanceof HslColorVariable) ||
			!($light instanceof HslColorVariable) ||
			!($alpha instanceof HslColorVariable)
		)
		{
			return false;
		}

		return (
			$hue->getName() === $saturation->getName() &&
			$hue->getName() === $light->getName() &&
			$hue->getName() === $alpha->getName()
		);
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

		$argCount = count($args);

		if ($argCount !== 1 && $argCount !== 4)
		{
			$call = self::toCall($this);
			return $call->compile($env);
		}

		if ($argCount === 1)
		{
			$variable = $args[0];
			if (!$this->containsHslColorVariable([$variable]))
			{
				$call = self::toCall($this);
				return $call->compile($env);
			}

			[$hue, $saturation, $light, $alpha] = $this->expandSimpleColorVariable($variable);
		}
		else
		{
			$hue = $args[0];
			$saturation = $args[1];
			$light = $args[2];
			$alpha = $args[3];
		}

		return new self(
			[
				$hue,
				$saturation,
				$light,
				$alpha,
			],
			$this->index,
			$this->currentFileInfo
		);
	}

	/**
	 * @param Tree[] $args
	 */
	protected function containsHslColorVariable(array $args): bool
	{
		foreach ($args AS $arg)
		{
			if ($arg instanceof HslColorVariable)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @return HslColorVariable[]
	 */
	protected function expandSimpleColorVariable(HslColorVariable $variable): array
	{
		$hue = $variable->setComponent('h');
		$saturation = $variable->setComponent('s');
		$light = $variable->setComponent('l');
		$alpha = $variable->setComponent('a');

		return [$hue, $saturation, $light, $alpha];
	}

	/**
	 * @param \Less_Output $output
	 */
	public function genCss($output): void
	{
		$alpha = $this->getAlpha();
		$hasAlpha = !($alpha instanceof Dimension) || $alpha->value !== 1.0;

		$output->add(
			$hasAlpha ? 'hsla(' : 'hsl(',
			$this->currentFileInfo,
			$this->index
		);
		$output->add($this->getOutputNewline(1));

		if ($this->isSimpleColorVariable())
		{
			$color = $this->getSimpleColorVariable($this->getHue());
			$color->genCSS($output);
		}
		else
		{
			$this->getHue()->genCSS($output);

			$output->add(Environment::$_outputMap[',']);
			$output->add($this->getOutputNewline(1));
			$this->getSaturation()->genCSS($output);

			$output->add(Environment::$_outputMap[',']);
			$output->add($this->getOutputNewline(1));
			$this->getLight()->genCSS($output);

			if ($hasAlpha)
			{
				$output->add(Environment::$_outputMap[',']);
				$output->add($this->getOutputNewline(1));
				$alpha->genCSS($output);
			}
		}

		$output->add($this->getOutputNewline());
		$output->add(')');
	}

	protected function getSimpleColorVariable(HslColorVariable $node): HslColorVariable
	{
		return $node->setComponent(null);
	}

	protected function getOutputNewline(int $indent = 0): string
	{
		if (Parser::$options['compress'])
		{
			return '';
		}

		if ($this->isSimpleColor() || $this->isSimpleColorVariable())
		{
			return '';
		}

		$indentation = str_repeat(
			Parser::$options['indentation'],
			Environment::$tabLevel + $indent
		);
		return "\n" . $indentation;
	}
}
