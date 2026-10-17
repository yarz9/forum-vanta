<?php

namespace XF\Util;

use XF\EmojiTransliterator;

use function array_key_exists, chr, strlen;

class Str
{
	/**
	 * @var array<string, \Transliterator>
	 */
	protected static $transliterators = [];

	public static function check_encoding(string $string, string $encoding = 'UTF-8'): bool
	{
		return mb_check_encoding($string, $encoding);
	}

	public static function is_ascii(string $string): bool
	{
		return static::check_encoding($string, 'ASCII');
	}

	public static function transliterate(string $string, bool $withEmoji = false): string
	{
		if (static::is_ascii($string))
		{
			return $string;
		}

		if (function_exists('transliterator_transliterate'))
		{
			return static::_transliterate($string, $withEmoji);
		}

		$romanization = static::getStringData()->romanization();

		return strtr($string, $romanization);
	}

	protected static function _transliterate(string $string, bool $withEmoji = false): string
	{
		$rules = ['NFD'];

		$transliterator = static::createTransliterator();
		if ($transliterator)
		{
			$rules[] = $transliterator;
		}

		$emojiTransliterator = ($withEmoji && \XF::options()->includeEmojiInTitles === 'convert')
			? static::createTransliteratorFromRule('emoji')
			: null;
		if ($emojiTransliterator)
		{
			$rules[] = $emojiTransliterator;
		}

		$rules[] = 'Latin-ASCII';
		$rules[] = 'Any-Latin';
		$rules[] = 'NFKD';
		$rules[] = '[:Nonspacing Mark:] Remove';

		foreach ($rules AS $rule)
		{
			if ($rule === null)
			{
				continue;
			}

			if ($rule instanceof \Transliterator)
			{
				if (strpos(($rule->id ?? ''), 'de-ASCII') === 0)
				{
					$string = preg_replace(
						"/([AUO])\u{0308}(?=\p{Ll})/u",
						'$1e',
						$string
					);
					$string = str_replace(
						["a\u{0308}", "o\u{0308}", "u\u{0308}", "A\u{0308}", "O\u{0308}", "U\u{0308}"],
						['ae', 'oe', 'ue', 'AE', 'OE', 'UE'],
						$string
					);
				}
				else
				{
					$string = $rule->transliterate($string);
				}
			}
			else if ($rule === 'NFD')
			{
				if (!normalizer_is_normalized($string, \Normalizer::NFD))
				{
					$string = normalizer_normalize($string, \Normalizer::NFD);
				}
			}
			else if ($rule === 'NFKD')
			{
				if (!normalizer_is_normalized($string, \Normalizer::NFKD))
				{
					$string = normalizer_normalize($string, \Normalizer::NFKD);
				}
			}
			else if ($rule === '[:Nonspacing Mark:] Remove')
			{
				$string = preg_replace('/\p{Mn}++/u', '', $string);
			}
			else if ($rule === 'Latin-ASCII')
			{
				$latinAscii = static::getStringData()->latinToAscii();
				$string = str_replace(
					array_keys($latinAscii),
					array_values($latinAscii),
					$string
				);
			}
			else
			{
				$ruleTransliterator = static::createTransliteratorFromRule($rule);
				if ($ruleTransliterator === null)
				{
					throw new \InvalidArgumentException(
						"Unknown transliteration rule '$rule'."
					);
				}

				$string = $ruleTransliterator->transliterate($string);
			}
		}

		return $string;
	}

	public static function normalize(string $string, int $case = 0): string
	{
		if (function_exists('normalizer_normalize'))
		{
			$normalized = (string) \Normalizer::normalize($string, \Normalizer::FORM_D);
			return preg_replace('/\pM/u', '', $normalized);
		}

		$stringData = static::getStringData();

		if ($case <= 0)
		{
			$lowerAccents = $stringData->lowerAccents();
			$string = strtr($string, $lowerAccents);
		}
		if ($case >= 0)
		{
			$upperAccents = $stringData->upperAccents();
			$string = strtr($string, $upperAccents);
		}
		return $string;
	}

	public static function clean(string $string, string $replace = ''): string
	{
		if (static::check_encoding($string))
		{
			return $string;
		}

		$badUtf8 = static::getStringData()->badUtf8();
		return preg_replace_callback('/' . $badUtf8 . '/S', function (array $matches) use ($replace): string
		{
			return isset($matches[2]) ? $replace : $matches[0];
		}, $string);
	}

	public static function substr(string $string, int $start, ?int $length = null): string
	{
		return mb_substr($string, $start, $length, 'UTF-8');
	}

	public static function substr_replace(string $string, string $replacement, int $start, ?int $length = null): string
	{
		$stringLength = static::strlen($string);

		if ($start < 0)
		{
			$start = max(0, $stringLength + $start);
		}

		if ($length === null)
		{
			$length = $stringLength;
		}
		else if ($length < 0)
		{
			$length = max(0, $stringLength - $start + $length);
		}

		$before = static::substr($string, 0, $start);
		$after = static::substr($string, $start + $length);

		return $before . $replacement . $after;
	}

	public static function strlen(string $string): int
	{
		return mb_strlen($string, 'UTF-8');
	}

	/**
	 * @return int<0,max>|false
	 */
	public static function strpos(string $haystack, string $needle, int $offset = 0)
	{
		return mb_strpos($haystack, $needle, $offset, 'UTF-8');
	}

	public static function str_contains(string $haystack, string $needle): bool
	{
		return static::strpos($haystack, $needle) !== false;
	}

	public static function ltrim(string $string, string $charlist = ''): string
	{
		if ($charlist === '')
		{
			return ltrim($string);
		}

		$pattern = '/^[' . preg_quote($charlist, '/') . ']+/u';

		return preg_replace($pattern, '', $string);
	}

	public static function rtrim(string $string, string $charlist = ''): string
	{
		if ($charlist === '')
		{
			return rtrim($string);
		}

		$pattern = '/[' . preg_quote($charlist, '/') . ']+$/u';

		return preg_replace($pattern, '', $string);
	}

	public static function trim(string $string, string $charlist = ''): string
	{
		return static::ltrim(static::rtrim($string, $charlist), $charlist);
	}

	public static function strtolower(string $string): string
	{
		return mb_strtolower($string, 'UTF-8');
	}

	public static function strtoupper(string $string): string
	{
		return mb_strtoupper($string, 'UTF-8');
	}

	public static function ucfirst(string $string): string
	{
		$first = static::substr($string, 0, 1);
		$remaining = static::substr($string, 1);

		return static::strtoupper($first) . $remaining;
	}

	public static function ucwords(string $string, string $separators = " \t\r\n\f\v"): string
	{
		$words = preg_split("/([$separators]+)/u", $string, -1, PREG_SPLIT_DELIM_CAPTURE);
		$uppercased = array_map(function (string $word): string
		{
			return static::ucfirst($word);
		}, $words);

		return implode('', $uppercased);
	}

	public static function from_html(string $string, bool $entities = false): string
	{
		if (!$entities)
		{
			return preg_replace_callback(
				'/(&#([Xx])?([0-9A-Za-z]+);)/m',
				[static::class, 'decodeNumericEntity'],
				$string
			);
		}

		return preg_replace_callback(
			'/&(#)?([Xx])?([0-9A-Za-z]+);/m',
			[static::class, 'decodeAnyEntity'],
			$string
		);
	}

	/**
	 * @return string|false
	 */
	public static function to_utf8(array $arr, bool $strict = false)
	{
		$result = '';

		foreach ($arr AS $k => $codePoint)
		{
			if ($codePoint >= 0 && $codePoint <= 0x7F)
			{
				// ASCII range (including control chars)
				$result .= chr($codePoint);
			}
			else if ($codePoint <= 0x7FF)
			{
				// 2-byte sequence
				$result .= chr(0xC0 | ($codePoint >> 6))
					. chr(0x80 | ($codePoint & 0x3F));
			}
			else if ($codePoint == 0xFEFF)
			{
				// Byte order mark (skip)
				continue;
			}
			else if ($codePoint >= 0xD800 && $codePoint <= 0xDFFF)
			{
				// Test for illegal surrogates
				if ($strict)
				{
					trigger_error(
						"\XF\Util\Str::to_utf8: Illegal surrogate at index: $k, value: $codePoint",
						E_USER_WARNING
					);
					return false;
				}
			}
			else if ($codePoint <= 0xFFFF)
			{
				// 3-byte sequence
				$result .= chr(0xE0 | ($codePoint >> 12))
					. chr(0x80 | (($codePoint >> 6) & 0x3F))
					. chr(0x80 | ($codePoint & 0x3F));
			}
			else if ($codePoint <= 0x10FFFF)
			{
				// 4-byte sequence
				$result .= chr(0xF0 | ($codePoint >> 18))
					. chr(0x80 | (($codePoint >> 12) & 0x3F))
					. chr(0x80 | (($codePoint >> 6) & 0x3F))
					. chr(0x80 | ($codePoint & 0x3F));
			}
			else if ($strict)
			{
				trigger_error(
					"\XF\Util\Str::to_utf8: Codepoint out of Unicode range at index: $k, value: $codePoint",
					E_USER_WARNING
				);
				return false;
			}
		}

		return $result;
	}

	/**
	 * @return string|false
	 */
	protected static function decodeAnyEntity(array $entity)
	{
		// create the named entity lookup table
		static $table = null;
		if ($table === null)
		{
			$table = get_html_translation_table(HTML_ENTITIES);
			$table = array_flip($table);
		}

		if ($entity[1] === '#')
		{
			return static::decodeNumericEntity($entity);
		}

		if (array_key_exists($entity[0], $table))
		{
			return $table[$entity[0]];
		}

		return $entity[0];
	}

	/**
	 * @return string|false
	 */
	protected static function decodeNumericEntity(array $entity)
	{
		switch ($entity[2])
		{
			case 'X':
			case 'x':
				$cp = hexdec($entity[3]);
				break;
			default:
				$cp = (int) $entity[3];
				break;
		}
		return static::to_utf8([$cp]);
	}

	protected static function createTransliterator(): ?\Transliterator
	{
		// use default language
		$locale = static::getLocale(\XF::app()->language()->getLanguageCode());
		if (array_key_exists($locale, static::$transliterators))
		{
			return static::$transliterators[$locale];
		}

		$stringData = static::getStringData();
		$id = $stringData->transliteratorIds()[$locale] ?? null;
		if ($id)
		{
			return static::$transliterators[$locale] = \Transliterator::create($id . '/BGN') ?? \Transliterator::create($id) ?? null;
		}

		return static::$transliterators[$locale] = null;
	}

	protected static function createTransliteratorFromRule(string $rule): ?\Transliterator
	{
		if (!array_key_exists($rule, static::$transliterators))
		{
			static::$transliterators[$rule] = $rule === 'emoji'
				? EmojiTransliterator::create()
				: \Transliterator::create($rule);
		}

		return static::$transliterators[$rule];
	}

	protected static function getLocale(string $languageCode): string
	{
		$parentLocale = strrchr($languageCode, '-');

		if (!$parentLocale)
		{
			return 'en';
		}

		return substr($languageCode, 0, -strlen($parentLocale));
	}

	protected static function getStringData(): \XF\Data\Str
	{
		return \XF::app()->data(\XF\Data\Str::class);
	}
}
