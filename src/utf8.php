<?php

// ###### NOTE: Functions in this file are deprecated and will be removed in the future. ######

use XF\Util\Str;

if (!function_exists('utf8_isASCII'))
{
   /**
	* @deprecated in 2.3.0 and will be removed in 3.0.0
	*/
	function utf8_isASCII(string $str): bool
	{
		return Str::is_ascii($str);
	}
}

if (!function_exists('utf8_romanize'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 */
	function utf8_romanize(string $string): string
	{
		return Str::transliterate($string);
	}
}

if (!function_exists('utf8_deaccent'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 */
	function utf8_deaccent(string $string, int $case = 0): string
	{
		return Str::normalize($string, $case);
	}
}

if (!function_exists('utf8_bad_replace'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 */
	function utf8_bad_replace(string $string, string $replace = ''): string
	{
		return Str::clean($string);
	}
}

if (!function_exists('utf8_substr'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 */
	function utf8_substr(string $string, int $start, ?int $length = null): string
	{
		return Str::substr($string, $start, $length);
	}
}

if (!function_exists('utf8_substr_replace'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 */
	function utf8_substr_replace(string $string, string $replacement, int $start, ?int $length = null): string
	{
		return Str::substr_replace($string, $replacement, $start, $length);
	}
}

if (!function_exists('utf8_strlen'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 */
	function utf8_strlen(string $string): int
	{
		return Str::strlen($string);
	}
}

if (!function_exists('utf8_strpos'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 *
	 * @return int<0,max>|false
	 */
	function utf8_strpos(string $haystack, string $needle, int $offset = 0)
	{
		return Str::strpos($haystack, $needle, $offset);
	}
}

if (!function_exists('utf8_ltrim'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 */
	function utf8_ltrim(string $string, string $charlist = ''): string
	{
		return Str::ltrim($string, $charlist);
	}
}

if (!function_exists('utf8_rtrim'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 */
	function utf8_rtrim(string $string, string $charlist = ''): string
	{
		return Str::rtrim($string, $charlist);
	}
}

if (!function_exists('utf8_trim'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 */
	function utf8_trim(string $string, string $charlist = ''): string
	{
		return Str::trim($string, $charlist);
	}
}

if (!function_exists('utf8_strtolower'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 */
	function utf8_strtolower(string $string): string
	{
		return Str::strtolower($string);
	}
}

if (!function_exists('utf8_strtoupper'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 */
	function utf8_strtoupper(string $string): string
	{
		return Str::strtoupper($string);
	}
}

if (!function_exists('utf8_ucfirst'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 */
	function utf8_ucfirst(string $string): string
	{
		return Str::ucfirst($string);
	}
}

if (!function_exists('utf8_ucwords'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 */
	function utf8_ucwords(string $string, string $separators = " \t\r\n\f\v"): string
	{
		return Str::ucwords($string, $separators);
	}
}

if (!function_exists('utf8_unhtml'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 */
	function utf8_unhtml(string $string, bool $entities = false): string
	{
		return Str::from_html($string, $entities);
	}
}

if (!function_exists('utf8_to_unicode'))
{
	/**
	 * @deprecated in 2.3.0 and will be removed in 3.0.0
	 *
	 * @return string|false
	 */
	function utf8_to_unicode(array $arr, bool $strict = false)
	{
		return Str::to_utf8($arr, $strict);
	}
}
