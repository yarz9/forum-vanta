<?php

namespace XF;

use XF\Entity\User;
use XF\Repository\StyleRepository;

use function in_array, intval, is_array, is_string;

class Style implements \ArrayAccess
{
	/**
	 * @var string
	 */
	public const VARIATION_DEFAULT = 'default';

	/**
	 * @var string
	 */
	public const VARIATION_VARIABLE = '_variable';

	/**
	 * @var string
	 */
	public const VARIABLE_KEY = '_variables';

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var int
	 */
	protected $lastModified;

	/**
	 * @var array<string, array{
	 *     _type: string,
	 *     default: mixed,
	 *     _variable?: string,
	 *     _variables?: array<string, string>,
	 *     ...<string, mixed>,
	 * }
	 */
	protected $properties;

	/**
	 * @var array{
	 *     parent_id: int,
	 *     parent_list: list<int>,
	 *     title: string,
	 *     description: string,
	 *     assets: array<string, string>,
	 *     effective_assets: array<string, string>,
	 *     enable_variations: bool,
	 *     user_selectable: bool,
	 * }
	 */
	protected $options = [
		'parent_id' => 0,
		'parent_list' => '',
		'title' => '',
		'description' => '',
		'assets' => [],
		'effective_assets' => [],
		'enable_variations' => true,
		'user_selectable' => true,
	];

	/**
	 * @var string
	 */
	protected $variation = self::VARIATION_DEFAULT;

	/**
	 * @param int $id
	 * @param array<string, array{
	 *     _type: string,
	 *     default: mixed,
	 *     _variable?: string,
	 *     _variables?: array<string, string>,
	 *     ...<string, mixed>,
	 * } $properties
	 * @param int|null $lastModified
	 * @param array{
	 *     parent_id: int,
	 *     parent_list: list<int>,
	 *     title: string,
	 *     description: string,
	 *     assets: array<string, string>,
	 *     effective_assets: array<string, string>,
	 *     enable_variations: bool,
	 *     user_selectable: bool,
	 * }|null $options
	 */
	public function __construct($id, array $properties, $lastModified = null, ?array $options = null)
	{
		if ($lastModified === null && $options === null)
		{
			$lastModified = $properties['last_modified_date'];

			$realProperties = $properties['properties'];
			$options = $properties;
			unset($options['last_modified_date'], $options['properties']);

			$properties = $realProperties;
		}

		$lastModified = intval($lastModified);
		if (!$lastModified)
		{
			$lastModified = \XF::$time;
		}
		if (!is_array($options))
		{
			$options = [];
		}

		$this->id = $id;
		$this->properties = $properties;
		$this->lastModified = $lastModified;
		$this->options = $options;

		if ($this->isVariationsEnabled())
		{
			$this->setVariation(self::VARIATION_VARIABLE);
		}
	}

	/**
	 * @return int
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @param int $lastModified
	 */
	public function setLastModified($lastModified)
	{
		$this->lastModified = (int) $lastModified;
	}

	/**
	 * @return int
	 */
	public function getLastModified()
	{
		return $this->lastModified;
	}

	public function isVariationsEnabled(): bool
	{
		return (bool) ($this->options['enable_variations'] ?? true);
	}

	public function setVariation(string $variation): void
	{
		if (!$this->isVariationsEnabled())
		{
			throw new \InvalidArgumentException(
				'Style does not support variations'
			);
		}

		if (
			!in_array($variation, $this->getVariations()) &&
			$variation !== self::VARIATION_VARIABLE
		)
		{
			throw new \InvalidArgumentException(
				"Invalid style variation: {$variation}"
			);
		}

		$this->variation = $variation;
	}

	public function getVariation(): string
	{
		return $this->variation;
	}

	public function getVariationIcon(string $variation): string
	{
		if ($variation === $this->getStyleTypeVariation('light'))
		{
			return 'fa-sun';
		}

		if ($variation === $this->getStyleTypeVariation('dark'))
		{
			return 'fa-moon';
		}

		return 'fa-adjust';
	}

	/**
	 * @return list<string>
	 */
	public function getVariations(bool $includeDefault = true): array
	{
		if (!$this->isVariationsEnabled())
		{
			return $includeDefault ? [Style::VARIATION_DEFAULT] : [];
		}

		return \XF::repository(StyleRepository::class)->getVariations($includeDefault);
	}

	public function getDefaultStyleType(): string
	{
		return $this->getPropertyVariation(
			'styleType',
			self::VARIATION_DEFAULT,
			'light'
		);
	}

	public function getAlternateStyleType(): string
	{
		return $this->getDefaultStyleType() === 'light' ? 'dark' : 'light';
	}

	public function getStyleTypeVariation(string $styleType): ?string
	{
		if ($styleType === $this->getDefaultStyleType())
		{
			return self::VARIATION_DEFAULT;
		}

		if ($styleType === $this->getAlternateStyleType())
		{
			return $this->getAlternateStyleTypeVariation();
		}

		return null;
	}

	public function hasAlternateStyleTypeVariation(): bool
	{
		return $this->getAlternateStyleTypeVariation() !== null;
	}

	public function getAlternateStyleTypeVariation(): ?string
	{
		$alternateStyleType = $this->getAlternateStyleType();
		foreach ($this->getVariations(false) AS $variation)
		{
			$styleType = $this->getPropertyVariation('styleType', $variation);
			if ($styleType === $alternateStyleType)
			{
				return $variation;
			}
		}

		return null;
	}

	/**
	 * @return bool
	 */
	public function isUsable(User $user)
	{
		if ($this->options['user_selectable'])
		{
			return true;
		}

		return $user->is_admin ? true : false;
	}

	/**
	 * @return string|null
	 */
	public function getAsset($key)
	{
		return $this->options['effective_assets'][$key] ?? null;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getVariationVariables(
		string $variation,
		bool $colors = false
	): array
	{
		if ($this->variation !== self::VARIATION_VARIABLE)
		{
			return [];
		}

		$values = [];

		foreach ($this->properties AS $name => $property)
		{
			if (
				($colors && $property['_type'] !== 'color') ||
				(!$colors && $property['_type'] === 'color')
			)
			{
				continue;
			}

			$value = $property[self::VARIABLE_KEY][$variation] ?? '';
			if (!$value)
			{
				continue;
			}

			$values[$name] = $value;
		}

		return $values;
	}

	/**
	 * @param string $name
	 * @param mixed $fallback
	 *
	 * @return mixed
	 */
	public function getProperty($name, $fallback = '')
	{
		return $this->getPropertyVariation($name, $this->variation, $fallback);
	}

	/**
	 * @param mixed $fallback
	 *
	 * @return mixed
	 */
	public function getPropertyVariation(
		string $name,
		string $variation,
		$fallback = ''
	)
	{
		if (strpos($name, '--'))
		{
			[$name, $subName] = explode('--', $name, 2);
		}
		else
		{
			$subName = null;
		}

		$property = $this->properties[$name] ?? null;
		if ($property === null)
		{
			return $fallback;
		}

		$default = $property[self::VARIATION_DEFAULT] ?? null;
		if ($default === null)
		{
			return $fallback;
		}

		if ($variation === self::VARIATION_DEFAULT)
		{
			$value = $default;
		}
		else
		{
			if (is_array($default))
			{
				$value = array_merge($default, $property[$variation] ?? []);
			}
			else
			{
				$value = $property[$variation] ?? $default;
			}
		}

		if ($subName !== null)
		{
			$value = $value[$subName] ?? null;
		}

		return $value ?? $fallback;
	}

	/**
	 * @param string $name
	 * @param list<string>|string|null $filters
	 *
	 * @return string
	 */
	public function getCssProperty($name, $filters = null)
	{
		return $this->getCssPropertyVariation($name, $this->variation, $filters);
	}

	/**
	 * @param list<string>|string|null $filters
	 *
	 * @return string
	 */
	public function getCssPropertyVariation(
		string $name,
		string $variation,
		$filters = null
	)
	{
		if (is_string($filters))
		{
			$filters = preg_split('/,\s*/', $filters);
		}
		if (!is_array($filters))
		{
			$filters = [];
		}

		$value = $this->getPropertyVariation($name, $variation);
		if (!is_array($value))
		{
			return '';
		}

		return $this->compileCssPropertyValue($value, $filters);
	}

	/**
	 * @param array<string, mixed> $css
	 * @param list<string>|string|null $filters
	 *
	 * @return string
	 */
	public function compileCssPropertyValue(array $css, array $filters = [])
	{
		$include = [
			'text' => true,
			'background' => true,
			'border' => true,
			'border-radius' => true,
			'padding' => true,
			'extra' => true,
		];
		$hasPositiveReset = false;
		foreach ($filters AS $filter)
		{
			if (isset($include[$filter]))
			{
				// positive match - remove everything on the first one, then add up
				if (!$hasPositiveReset)
				{
					foreach ($include AS &$included)
					{
						$included = false;
					}
					$hasPositiveReset = true;
				}
				$include[$filter] = true;
			}
			else if (substr($filter, 0, 3) == 'no-')
			{
				$noFilter = substr($filter, 3);
				if (isset($include[$noFilter]))
				{
					// negative match - just remove this one
					$include[$noFilter] = false;
				}
				else if (isset($css[$noFilter]))
				{
					unset($css[$noFilter]);
				}
			}
		}

		$output = [];

		$outputSimple = function ($name) use (&$output, $css)
		{
			if (isset($css[$name]))
			{
				$output[] = "$name: " . $css[$name] . ';';
			}
		};

		if ($include['text'])
		{
			$outputSimple('font-size');
			$outputSimple('color');
			$outputSimple('font-weight');
			$outputSimple('font-style');
			$outputSimple('text-decoration');
		}

		if ($include['background'])
		{
			if (isset($css['background-color']) || isset($css['background-image']))
			{
				$output[] = 'background: '
					. trim(
						($css['background-color'] ?? '')
						. ' '
						. ($css['background-image'] ?? '')
					)
					. ';';
			}
		}

		if ($include['border'])
		{
			$hasGeneralBorderStyle = false;

			if (isset($css['border-width'], $css['border-color']))
			{
				$output[] = 'border: ' . $css['border-width'] . ' solid ' . $css['border-color'] . ';';
				$hasGeneralBorderStyle = true;
			}
			else
			{
				$outputSimple('border-width');
				if (isset($css['border-width']))
				{
					$output[] = 'border-style: solid;';
					$hasGeneralBorderStyle = true;
				}
				$outputSimple('border-color');
			}

			$outputBorderSide = function ($side) use (&$output, $css, $outputSimple, $hasGeneralBorderStyle)
			{
				$width = "border-{$side}-width";
				$color = "border-{$side}-color";
				$sideProperty = "border-{$side}";

				if (isset($css[$width], $css[$color]))
				{
					$output[$sideProperty] = $sideProperty . ': ' . $css[$width] . ' solid ' . $css[$color] . ';';
				}
				else
				{
					$outputSimple($width);
					if (isset($css[$width]) && !$hasGeneralBorderStyle)
					{
						$output[] = "border-{$side}-style: solid;";
					}
					$outputSimple($color);
				}
			};
			$outputBorderSide('top');
			$outputBorderSide('right');
			$outputBorderSide('bottom');
			$outputBorderSide('left');
		}

		if ($include['border-radius'])
		{
			$outputSimple('border-radius');
			$outputSimple('border-top-left-radius');
			$outputSimple('border-top-right-radius');
			$outputSimple('border-bottom-right-radius');
			$outputSimple('border-bottom-left-radius');
		}

		if ($include['padding'])
		{
			$outputSimple('padding');
			$outputSimple('padding-top');
			$outputSimple('padding-right');
			$outputSimple('padding-bottom');
			$outputSimple('padding-left');
		}

		if ($include['extra'] && isset($css['extra']))
		{
			$output[] = $css['extra'];
		}

		$return = trim(implode("\n", $output));
		$return = "\t" . str_replace("\n", "\n\t", $return);

		return $return;
	}

	/**
	 * @return array<string, array{
	 *     _type: string,
	 *     default: mixed,
	 *     _variable?: string,
	 *     _variables?: array<string, string>,
	 *     ...<string, mixed>,
	 * }
	 */
	public function getProperties()
	{
		return $this->properties ?: [];
	}

	/**
	 * @param array<string, array{
	 *     _type: string,
	 *     default: mixed,
	 *     _variable?: string,
	 *     _variables?: array<string, string>,
	 *     ...<string, mixed>,
	 * } $properties
	 */
	public function setProperties(array $properties)
	{
		$this->properties = $properties;
	}

	/**
	 * @param mixed $key
	 *
	 * @return mixed
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet($key)
	{
		switch ($key)
		{
			case 'style_id': return $this->id;
			case 'last_modified_date': return $this->lastModified;
			default: return $this->options[$key];
		}
	}

	/**
	 * @param mixed $key
	 */
	public function offsetExists($key): bool
	{
		switch ($key)
		{
			case 'style_id':
			case 'last_modified_date':
				return true;

			default:
				return isset($this->options[$key]);
		}
	}

	/**
	 * @param mixed $key
	 * @param mixed $value
	 */
	public function offsetSet($key, $value): void
	{
		throw new \LogicException("Style object options cannot be written to.");
	}

	/**
	 * @param mixed $key
	 */
	public function offsetUnset($key): void
	{
		throw new \LogicException("Style object options cannot be written to.");
	}
}
