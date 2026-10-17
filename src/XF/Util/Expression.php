<?php

namespace XF\Util;

use function intval;

class Expression
{
	/**
	 * Tests whether a given integer n matches a valid CSS :nth-child expression such as 2n+1
	 *
	 * @param int $n
	 * @param     $expression
	 *
	 * @return bool
	 */
	public static function nthMatch(int $n, $expression): bool
	{
		if (is_numeric($expression))
		{
			return intval($expression) === $n;
		}

		switch (Str::strtolower($expression))
		{
			case 'odd':
				return $n % 2 === 1;

			case 'even':
				return $n % 2 === 0;

			default:
				$expression = preg_replace('/\s+/', '', $expression);

				if (preg_match('/^(?P<a>[+-]?\d*)n(?P<b>[+-]\d+)?$/', $expression, $matches))
				{
					$a = isset($matches['a']) ? intval($matches['a']) : 1;
					$b = isset($matches['b']) ? intval($matches['b']) : 0;

					$result = ($n - $b) / $a;

					return $result > 0 && floor($result) == $result;
				}
		}

		// not a valid expression
		return false;
	}
}
