<?php

namespace XF\Less\Tree;

use Less_Exception_Compiler as CompilerException;
use Less_Tree as Tree;
use Less_Tree_Call as Call;
use Less_Tree_Color as Color;
use Less_Tree_Dimension as Dimension;
use Less_Tree_Keyword as Keyword;
use Less_Tree_Operation as Operation;
use Less_Tree_Paren as Paren;
use XF\Util\Php;

use function get_class;

class HslColorFunction extends Tree
{
	// TODO: better error msgs
	/**
	 * @var string
	 */
	public $type = 'HslColorFunction';

	/**
	 * @var string
	 */
	public $function;

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
		string $function,
		array $args,
		int $index,
		?array $currentFileInfo = null
	)
	{
		if (!self::isValidFunction($function))
		{
			throw new \InvalidArgumentException(
				"Invalid HSL color function: '{$function}'"
			);
		}

		$this->function = $function;
		$this->args = $args;

		$this->index = $index;
		$this->currentFileInfo = $currentFileInfo;
	}

	public static function fromCall(Call $call): self
	{
		if (!self::isValidFunction($call->name))
		{
			throw new \InvalidArgumentException(
				'Call must be a valid HSL color function, ' . $call->name . ' given'
			);
		}

		return new self(
			$call->name,
			$call->args,
			$call->index,
			$call->currentFileInfo
		);
	}

	public static function toCall(self $self): Call
	{
		return new Call(
			$self->function,
			$self->args,
			$self->index,
			$self->currentFileInfo
		);
	}

	public static function isValidFunction(string $function): bool
	{
		$method = self::getFunctionMethod($function);
		return method_exists(self::class, $method);
	}

	protected static function getFunctionMethod(string $function): string
	{
		return 'func' . Php::camelCase($function, '-');
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
				$compiled = $argument->compile($env);
				if ($compiled instanceof Color)
				{
					return HslColor::fromColor($compiled);
				}

				return $compiled;
			},
			$this->args
		);

		if (!$this->containsHslColor($args))
		{
			$call = self::toCall($this);
			return $call->compile($env);
		}

		return $this->func($this->function, $args);
	}

	/**
	 * @param Tree[] $args
	 */
	protected function containsHslColor(array $args): bool
	{
		foreach ($args AS $arg)
		{
			if ($arg instanceof HslColor)
			{
				return true;
			}

			if ($arg instanceof HslColorFunction)
			{
				return true;
			}
		}

		return false;
	}

	protected function func(string $function, array $args): Tree
	{
		try
		{
			$method = self::getFunctionMethod($function);
			$value = $this->{$method}(...$args);
		}
		catch (\Throwable $e)
		{
			$index = $this->index;

			throw new CompilerException(
				"The function '{$function}' could not be evaluated (index {$index}): " . $e->getMessage()
			);
		}

		return $value;
	}

	protected function funcRed(HslColor $color): Dimension
	{
		// this is unsupported on HSL colors
		return new Dimension(0);
	}

	protected function funcBlue(HslColor $color): Dimension
	{
		// this is unsupported on HSL colors
		return new Dimension(0);
	}

	protected function funcGreen(HslColor $color): Dimension
	{
		// this is unsupported on HSL colors
		return new Dimension(0);
	}

	protected function funcHue(HslColor $color): Tree
	{
		return $color->getHue();
	}

	protected function funcSaturation(HslColor $color): Tree
	{
		return $color->getSaturation();
	}

	protected function funcLightness(HslColor $color): Tree
	{
		return $color->getLight();
	}

	protected function funcAlpha(HslColor $color): Tree
	{
		return $color->getAlpha();
	}

	protected function funcSaturate(HslColor $color, Tree $amount): HslColor
	{
		$this->assertValidDimensionArgument($amount);

		return $color->setSaturation($this->getCalc(
			$color->getSaturation(),
			'+',
			$amount
		));
	}

	protected function funcDesaturate(HslColor $color, Tree $amount): HslColor
	{
		$this->assertValidDimensionArgument($amount);

		return $color->setSaturation($this->getCalc(
			$color->getSaturation(),
			'-',
			$amount
		));
	}

	protected function funcLighten(HslColor $color, Tree $amount): HslColor
	{
		$this->assertValidDimensionArgument($amount);

		return $color->setLight($this->getCalc(
			$color->getLight(),
			'+',
			$amount
		));
	}

	protected function funcDarken(HslColor $color, Tree $amount): HslColor
	{
		$this->assertValidDimensionArgument($amount);

		return $color->setLight($this->getCalc(
			$color->getLight(),
			'-',
			$amount
		));
	}

	protected function funcXfDiminish(HslColor $color, Tree $amount): HslColor
	{
		$this->assertValidDimensionArgument($amount);

		return $color->setLight($this->getCalc(
			$color->getLight(),
			'+',
			$this->getColorAdjustCalc($amount)
		));
	}

	protected function funcXfIntensify(HslColor $color, Tree $amount): HslColor
	{
		$this->assertValidDimensionArgument($amount);

		return $color->setLight($this->getCalc(
			$color->getLight(),
			'-',
			$this->getColorAdjustCalc($amount)
		));
	}

	protected function funcFadein(HslColor $color, Tree $amount): HslColor
	{
		$this->assertValidDimensionArgument($amount);

		return $color->setAlpha($this->getCalc(
			$color->getAlpha(),
			'+',
			$amount
		));
	}

	protected function funcFadeout(HslColor $color, Tree $amount): HslColor
	{
		$this->assertValidDimensionArgument($amount);

		return $color->setAlpha($this->getCalc(
			$color->getAlpha(),
			'-',
			$amount
		));
	}

	protected function funcFade(HslColor $color, Tree $alpha): HslColor
	{
		$this->assertValidDimensionArgument($alpha);

		return $color->setAlpha($alpha);
	}

	protected function funcSpin(HslColor $color, Tree $angle): HslColor
	{
		$this->assertValidDimensionArgument($angle);

		return $color->setHue($this->getCalc(
			$color->getHue(),
			'+',
			$angle
		));
	}

	protected function funcMix(
		Tree $color1,
		Tree $color2,
		?Dimension $weight = null
	): HslColor
	{
		$color1 = $this->normalizeColorArgument($color1);
		$color2 = $this->normalizeColorArgument($color2);

		if ($weight === null)
		{
			$weight = new Dimension(50, '%');
		}

		$weight1 = new Dimension($weight->value / 100);
		$weight2 = new Dimension(1 - $weight1->value);

		$hue = $this->getCalc(
			$this->getCalc(
				$color1->getHue(),
				'*',
				$weight1
			),
			'+',
			$this->getCalc(
				$color2->getHue(),
				'*',
				$weight2
			)
		);
		$saturation = $this->getCalc(
			$this->getCalc(
				$color1->getSaturation(),
				'*',
				$weight1
			),
			'+',
			$this->getCalc(
				$color2->getSaturation(),
				'*',
				$weight2
			)
		);
		$light = $this->getCalc(
			$this->getCalc(
				$color1->getLight(),
				'*',
				$weight1
			),
			'+',
			$this->getCalc(
				$color2->getLight(),
				'*',
				$weight2
			)
		);
		$alpha = $this->getCalc(
			$this->getCalc(
				$color1->getAlpha(),
				'*',
				$weight1
			),
			'+',
			$this->getCalc(
				$color2->getAlpha(),
				'*',
				$weight2
			)
		);

		return new HslColor(
			[$hue, $saturation, $light, $alpha],
			$this->index,
			$this->currentFileInfo
		);
	}

	protected function funcTint(
		HslColor $color,
		?Dimension $weight = null
	): HslColor
	{
		if ($weight === null)
		{
			$weight = new Dimension(50, '%');
		}

		$weight1 = new Dimension($weight->value / 100);
		$weight2 = new Dimension(1 - $weight1->value);

		return new HslColor(
			[
				$color->getHue(),
				$this->getCalc(
					$color->getSaturation(),
					'*',
					$weight2
				),
				$this->getCalc(
					$this->getCalc(
						new Dimension(100, '%'),
						'*',
						$weight1
					),
					'+',
					$this->getCalc(
						$color->getLight(),
						'*',
						$weight2
					)
				),
				$color->getAlpha(),
			],
			$this->index,
			$this->currentFileInfo
		);
	}

	protected function funcShade(
		HslColor $color,
		?Dimension $weight = null
	): HslColor
	{
		if ($weight === null)
		{
			$weight = new Dimension(50, '%');
		}

		$multiplier = new Dimension(1 - $weight->value / 100);

		return $color->setLight($this->getCalc(
			$color->getLight(),
			'*',
			$multiplier
		));
	}

	protected function funcGreyscale(HslColor $color): HslColor
	{
		return $color->setSaturation(new Dimension(0, '%'));
	}

	protected function funcContrast(
		HslColor $color,
		?HslColor $dark = null,
		?HslColor $light = null,
		?Dimension $threshold = null
	): HslColor
	{
		if ($threshold === null)
		{
			$threshold = new Dimension(67, '%');
		}

		$light = $this->getCalc(
			$this->getCalc(
				$color->getLight(),
				'-',
				$threshold
			),
			'*',
			new Dimension(-100)
		);

		$clamp = new HslClamp(
			[
				new Dimension(0, '%'),
				$light,
				new Dimension(100, '%'),
			],
			$this->index,
			$this->currentFileInfo
		);

		return $color->setLight($clamp);
	}

	protected function assertValidDimensionArgument(Tree $node): void
	{
		if (
			!($node instanceof Dimension) &&
			!($node instanceof HslColorVariable)
		)
		{
			throw new \TypeError(
				'Argument must be a dimension or CSS variable, ' . get_class($node) . ' given'
			);
		}
	}

	protected function normalizeColorArgument(Tree $node): HslColor
	{
		if (!($node instanceof HslColor) && !($node instanceof Color))
		{
			throw new \TypeError(
				'Argument must be color, ' . get_class($node) . ' given'
			);
		}

		if ($node instanceof Color)
		{
			$node = HslColor::fromColor($node);
		}

		return $node;
	}

	protected function getColorAdjustCalc(Tree $amount): Call
	{
		return $this->getCalc(
			new Call(
				'var',
				[new Keyword('--xf-color-adjust'), new Dimension(1)],
				$this->index,
				$this->currentFileInfo
			),
			'*',
			$amount
		);
	}

	/**
	 * @param Tree[] $operands
	 */
	protected function getCalc(Tree $left, string $operation, Tree $right): Call
	{
		if ($left instanceof Call && $left->name === 'calc')
		{
			$left = new Paren($left->args[0]);
		}

		if ($right instanceof Call && $right->name === 'calc')
		{
			$right = new Paren($right->args[0]);
		}

		$operation = new Operation($operation, [$left, $right], true);

		return new Call(
			'calc',
			[$operation],
			$this->index,
			$this->currentFileInfo
		);
	}
}
