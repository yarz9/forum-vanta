<?php

namespace XF\Str;

use JoyPixels\Client;
use JoyPixels\Ruleset;
use XF\Util\Str;

use function array_key_exists, in_array, is_array;

class EmojiFormatter
{
	public const UC_OUTPUT = 0;
	public const UC_MATCH = 1;
	public const UC_BASE = 2;

	/**
	 * @var Client
	 */
	protected $client;

	protected $config = [];

	public function __construct(array $config)
	{
		$this->client = new Client($this->getRuleset());
		$this->config = $this->setTypeSpecificDefaults($config);
	}

	public function formatEmojiToImage(string $string, array $formatOptions = []): string
	{
		if ($this->config['style'] == 'native')
		{
			return $string;
		}

		$client = $this->client;

		$string = preg_replace_callback('/' . $client->ignoredRegexp . '|' . $client->unicodeRegexp . '/u', function ($match) use ($formatOptions)
		{
			if (!is_array($match) || !isset($match[0]) || empty($match[0]))
			{
				return $match[0];
			}

			$ruleset = $this->getRuleset();
			$unicodeReplace = $ruleset->getUnicodeReplace();

			$unicode = strtoupper($match[0]);

			if ($key = array_search($unicode, $unicodeReplace, true))
			{
				return $this->getImageFromShortname($key, false, $formatOptions);
			}

			return $match[0];
		}, $string);

		return $string;
	}

	public function formatShortnameToImage(string $string, array $formatOptions = []): string
	{
		$client = $this->client;

		$string = preg_replace_callback(
			'/' . $client->ignoredRegexp . '|(' . $client->shortcodeRegexp . ')/Si',
			function ($match) use ($formatOptions)
			{
				if (!is_array($match) || !isset($match[1]) || empty($match[1]))
				{
					return $match[0];
				}
				else
				{
					$ruleset = $this->getRuleset();
					$shortcodeReplace = $ruleset->getShortcodeReplace();

					$shortname = $match[1];

					if (!isset($shortcodeReplace[$shortname]))
					{
						return $match[0];
					}

					return $this->getImageFromShortname($shortname, false, $formatOptions);
				}
			},
			$string
		);

		return $string;
	}

	public function getImageFromShortname(
		string $shortname,
		bool $lazyLoad = false,
		array $formatOptions = []
	): string
	{
		$title = $this->getEmojiNameFromShortname($shortname) . '    ' . $shortname;
		$info = $this->getInfoFromShortname($shortname, $formatOptions);

		$emoji = $info['text'];
		$src = $info['image'] ?? null;

		if (!$src)
		{
			if (!empty($formatOptions['wrapNative']))
			{
				return '<span class="smilie smilie--emoji"'
					. ' title="' . htmlspecialchars($title) . '"'
					. ' data-shortname="' . htmlspecialchars($shortname) . '">'
					. htmlspecialchars($emoji) . '</span>';
			}
			else
			{
				return htmlspecialchars($emoji);
			}
		}

		$attributes = [
			'class' => 'smilie smilie--emoji',
			($lazyLoad ? 'data-alt' : 'alt') => $emoji,
			($lazyLoad ? 'data-src' : 'src') => $src,
			'title' => $title,
			'data-shortname' => $shortname,
		];

		if ($lazyLoad)
		{
			$attributes['class'] .= ' smilie--lazyLoad';
		}
		else
		{
			$attributes['loading'] = 'lazy';
			$attributes['width'] = $info['size'];
			$attributes['height'] = $info['size'];
			// CSS controls the actual dimensions, but setting this should allow aspect ratio optimizations
		}

		$attrHtml = '';
		foreach ($attributes AS $k => $v)
		{
			$attrHtml .= ' ' . $k . '="' . htmlspecialchars($v) . '"';
		}

		if ($lazyLoad)
		{
			return "<span{$attrHtml}></span>";
		}
		else
		{
			return "<img{$attrHtml} />";
		}
	}

	public function getInfoFromShortname(string $shortname, array $options = []): array
	{
		$config = $this->config;
		$forceImage = $options['forceImage'] ?? false;
		$useNative = !empty($options['forceNative']) || $config['style'] == 'native';

		$emoji = $this->formatShortnameToEmoji($shortname);
		$imageUrl = null;

		if ($useNative)
		{
			if ($forceImage)
			{
				$imageUrl = 'data:image/svg+xml,' . rawurlencode($this->getEmojiInSvg($emoji));
			}
		}
		else
		{
			$ruleset = $this->getRuleset();
			$shortcodeReplace = $ruleset->getShortcodeReplace();

			if (!isset($shortcodeReplace[$shortname]))
			{
				if ($forceImage)
				{
					$imageUrl = 'data:image/svg+xml,' . rawurlencode($this->getEmojiInSvg($emoji));
				}
			}
			else
			{
				$filename = $shortcodeReplace[$shortname][$config['uc_filename']];
				$filename = $config['filename_formatter']($filename);

				$imageUrl = $config['path'] . $filename . '.png';
			}
		}

		switch ($config['style'])
		{
			case 'emojione':
				$size = 64;
				break;

			case 'twemoji':
				$size = 72;
				break;

			default:
				$size = 22;
				break;
		}

		return [
			'native' => $useNative,
			'text' => $emoji,
			'image' => $imageUrl,
			'size' => $size,
		];
	}

	protected function getEmojiInSvg(string $emoji): string
	{
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
			. '<text x="50%" y="50%" text-anchor="middle" dominant-baseline="central" font-size="54">'
			. htmlspecialchars($emoji)
			. '</text>'
			. '</svg>';
	}

	public function getEmojiNameFromShortname($shortname)
	{
		return \XF::phrase('emoji.' . str_replace('-', '_', str_replace(':', '', strtolower(Str::transliterate($shortname)))));
	}

	public function formatShortnameToEmojiExceptions($string, array $exceptions = [], $native = true)
	{
		$client = $this->client;

		$exceptionsKeyed = array_fill_keys(array_map('strtolower', $exceptions), true);

		$string = preg_replace_callback(
			'/' . $client->ignoredRegexp . '|(\B:([-+\w]+):\B)/Siu',
			function ($match) use ($client, $native, $exceptionsKeyed)
			{
				if (!is_array($match) || !isset($match[1]) || empty($match[1]))
				{
					return $match[0];
				}
				else
				{
					$ruleset = $this->getRuleset();
					$shortcodeReplace = $ruleset->getShortcodeReplace();

					$shortname = strtolower($match[1]);

					if (isset($exceptionsKeyed[$shortname]))
					{
						return $match[0];
					}

					if (!array_key_exists($shortname, $shortcodeReplace))
					{
						return $match[0];
					}

					$unicode = $shortcodeReplace[$shortname][0];
					$unicode = $client->convert($unicode);

					if ($native)
					{
						return html_entity_decode($unicode);
					}
					else
					{
						return $unicode;
					}
				}
			},
			$string
		);

		return $string;
	}

	public function formatShortnameToEmoji($string, $native = true)
	{
		return $this->formatShortnameToEmojiExceptions($string, [], $native);
	}

	public function formatEmojiToShortname($string)
	{
		return $this->client->toShort($string);
	}

	/**
	 * @return Client
	 */
	public function getClient()
	{
		return $this->client;
	}

	/**
	 * @return Ruleset
	 */
	public function getRuleset()
	{
		return new Ruleset();
	}

	protected function setTypeSpecificDefaults($config)
	{
		if ($config['style'] == 'native')
		{
			return $config;
		}

		$useCdn = ($config['source'] == 'cdn');

		if ($config['style'] == 'emojione')
		{
			$config['path'] = $useCdn ? 'https://cdn.jsdelivr.net/joypixels/assets/8.0/png/unicode/64/' : $config['path'];
			$config['uc_filename'] = self::UC_MATCH;
			$config['filename_formatter'] = function ($filename) { return $filename; };
		}
		else if ($config['style'] == 'twemoji')
		{
			$config['path'] = $useCdn ? 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/' : $config['path'];
			$config['uc_filename'] = self::UC_OUTPUT;
			$config['filename_formatter'] = function ($filename)
			{
				// Twemoji strips the leading zeros.
				if (strpos($filename, '00') === 0)
				{
					$filename = preg_replace('/^(00)/', '', $filename);
				}

				// Some Twemoji symbols don't include the fe0f 'variant form' indicator
				if (strpos($filename, '-fe0f-') !== false)
				{
					$filename = preg_replace('/^(\w{2})(?:-fe0f-)(.*)$/', '$1-$2', $filename);
				}

				/**
				 * Some Twemoji file names are different to what we would expect
				 * so handle those replacements manually based on a known list
				 */
				$filename = $this->getTwemojiReplacement($filename);

				return $filename;
			};
		}

		return $config;
	}

	protected function getTwemojiReplacement(string $filename): string
	{
		$replacements = [
			'263a-fe0f', '2639-fe0f', '2620-fe0f', '270c-fe0f', '261d-fe0f', '270d-fe0f', '1f441-fe0f', '1f5e3-fe0f', '1f574-fe0f', '26d1-fe0f', '1f576-fe0f', '1f577-fe0f', '1f578-fe0f', '1f54a-fe0f', '1f43f-fe0f', '2618-fe0f', '2604-fe0f', '1f32a-fe0f', '2600-fe0f', '1f324-fe0f', '1f325-fe0f', '2601-fe0f', '1f326-fe0f', '1f327-fe0f', '26c8-fe0f', '1f329-fe0f', '1f328-fe0f', '2744-fe0f', '2603-fe0f', '1f32c-fe0f', '2602-fe0f', '1f32b-fe0f', '1f336-fe0f', '1f37d-fe0f', '26f8-fe0f', '26f7-fe0f', '1f396-fe0f', '1f3f5-fe0f', '1f397-fe0f', '1f39f-fe0f', '265f-fe0f', '1f3ce-fe0f', '1f3cd-fe0f', '2708-fe0f', '1f6e9-fe0f', '1f6f0-fe0f', '1f6e5-fe0f', '1f6f3-fe0f', '26f4-fe0f', '1f5fa-fe0f', '1f3df-fe0f', '26f1-fe0f', '1f3d6-fe0f', '1f3dd-fe0f', '1f3dc-fe0f', '26f0-fe0f', '1f3d4-fe0f', '1f3d5-fe0f', '1f3d8-fe0f', '1f3da-fe0f', '1f3d7-fe0f', '1f3db-fe0f', '26e9-fe0f', '1f6e4-fe0f', '1f6e3-fe0f', '1f3de-fe0f', '1f3d9-fe0f', '2328-fe0f', '1f5a5-fe0f', '1f5a8-fe0f', '1f5b1-fe0f', '1f5b2-fe0f', '1f579-fe0f', '1f5dc-fe0f', '1f4fd-fe0f', '1f39e-fe0f', '260e-fe0f', '1f399-fe0f', '1f39a-fe0f', '1f39b-fe0f', '23f1-fe0f', '23f2-fe0f', '1f570-fe0f', '1f56f-fe0f', '1f6e2-fe0f', '2696-fe0f', '2692-fe0f', '1f6e0-fe0f', '26cf-fe0f', '2699-fe0f', '26d3-fe0f', '1f5e1-fe0f', '2694-fe0f', '1f6e1-fe0f', '26b0-fe0f', '26b1-fe0f', '2697-fe0f', '1f573-fe0f', '1f321-fe0f', '1f6ce-fe0f', '1f5dd-fe0f', '1f6cb-fe0f', '1f6cf-fe0f', '1f5bc-fe0f', '1f6cd-fe0f', '2709-fe0f', '1f3f7-fe0f', '1f5d2-fe0f', '1f5d3-fe0f', '1f5d1-fe0f', '1f5c3-fe0f', '1f5f3-fe0f', '1f5c4-fe0f', '1f5c2-fe0f', '1f5de-fe0f', '1f587-fe0f', '2702-fe0f', '1f58a-fe0f', '1f58b-fe0f', '2712-fe0f', '1f58c-fe0f', '1f58d-fe0f', '270f-fe0f', '2764-fe0f', '2763-fe0f', '262e-fe0f', '271d-fe0f', '262a-fe0f', '1f549-fe0f', '2638-fe0f', '2721-fe0f', '262f-fe0f', '2626-fe0f', '269b-fe0f', '2622-fe0f', '2623-fe0f', '1f237-fe0f', '2734-fe0f', '3299-fe0f', '3297-fe0f', '1f170-fe0f', '1f171-fe0f', '1f17e-fe0f', '2668-fe0f', '203c-fe0f', '2049-fe0f', '303d-fe0f', '26a0-fe0f', '269c-fe0f', '267b-fe0f', '2747-fe0f', '2733-fe0f', '24c2-fe0f', '1f17f-fe0f', '1f202-fe0f', '2139-fe0f', '23cf-fe0f', '25b6-fe0f', '23f8-fe0f', '23ef-fe0f', '23f9-fe0f', '23fa-fe0f', '23ed-fe0f', '23ee-fe0f', '25c0-fe0f', '27a1-fe0f', '2b05-fe0f', '2b06-fe0f', '2b07-fe0f', '2197-fe0f', '2198-fe0f', '2199-fe0f', '2196-fe0f', '2195-fe0f', '2194-fe0f', '21aa-fe0f', '21a9-fe0f', '2934-fe0f', '2935-fe0f', '2716-fe0f', '267e-fe0f', '2122-fe0f', 'a9-fe0f', 'ae-fe0f', '3030-fe0f', '2714-fe0f', '2611-fe0f', '25aa-fe0f', '25ab-fe0f', '25fc-fe0f', '25fb-fe0f', '1f5e8-fe0f', '1f441-fe0f-200d-1f5e8-fe0f', '1f5ef-fe0f', '2660-fe0f', '2663-fe0f', '2665-fe0f', '2666-fe0f', '2640-fe0f', '2642-fe0f', '2695-fe0f', '1f3f3-fe0f',
		];

		if (in_array($filename, $replacements))
		{
			return str_replace('-fe0f', '', $filename);
		}
		else
		{
			return $filename;
		}
	}
}
