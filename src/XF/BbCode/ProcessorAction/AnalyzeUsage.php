<?php

namespace XF\BbCode\ProcessorAction;

use XF\App;
use XF\BbCode\Processor;
use XF\Repository\EmbedResolverRepository;
use XF\Repository\UnfurlRepository;
use XF\Service\ImageProxyService;
use XF\Str\Formatter;
use XF\Util\Str;

use function count, intval;

class AnalyzeUsage implements AnalyzerInterface
{
	/**
	 * @var Formatter
	 */
	protected $formatter;

	protected $tagCount = [];
	protected $smilieCount = 0;
	protected $printableLength = 0;

	protected $attachments = [];
	protected $quotes = [];
	protected $unfurls = [];
	protected $images = [];
	protected $embeds = [];

	public function __construct(Formatter $formatter)
	{
		$this->formatter = $formatter;
	}

	public function addAnalysisHooks(AnalyzerHooks $hooks)
	{
		$hooks->addSetupHook('initialize')
			->addGlobalTagHook('analyzeTagUsage')
			->addTagHook('url', 'analyzeUrlUnfurlUsage')
			->addTagHook('quote', 'analyzeQuoteTag')
			->addTagHook('attach', 'analyzeAttachTag')
			->addTagHook('img', 'analyzeImgTag')
			->addStringHook('analyzeString')
			->addFinalHook('analyzeEmbedUsage')
			->addFinalHook('analyzeUnfurlUsage');
	}

	public function getTagCount($tag)
	{
		return $this->tagCount[$tag] ?? 0;
	}

	public function getTotalTagCount()
	{
		return array_sum($this->tagCount);
	}

	public function getSmilieCount()
	{
		return $this->smilieCount;
	}

	public function getAttachments()
	{
		return $this->attachments;
	}

	public function getQuotes()
	{
		return $this->quotes;
	}

	public function getUnfurls()
	{
		return $this->unfurls;
	}

	public function getImages(): array
	{
		return $this->images;
	}

	public function getEmbeds()
	{
		return $this->embeds;
	}

	public function getPrintableLength()
	{
		return $this->printableLength;
	}

	public function initialize()
	{
		$this->tagCount = [];
		$this->smilieCount = 0;
		$this->printableLength = 0;
		$this->attachments = [];
		$this->quotes = [];
		$this->unfurls = [];
		$this->embeds = [];
	}

	public function analyzeTagUsage(array $tag, array $options)
	{
		$this->incrementTagCount($tag['tag']);
	}

	public function analyzeString($string, array $options)
	{
		$this->printableLength += Str::strlen($string);

		if (empty($options['stopSmilies']))
		{
			$this->formatter->replaceSmiliesInText($string, function ()
			{
				$this->smilieCount++;
				return '';
			});
		}
	}

	/**
	 * @deprecated
	 */
	public function analyzeUnfurlUsage($string, Processor $processor)
	{
	}

	public function analyzeUrlUnfurlUsage(array $tag, array $options, $finalOutput, Processor $processor)
	{
		if (!$finalOutput)
		{
			// was stripped
			return;
		}

		if (!isset($tag['option']['unfurl']) || $tag['option']['unfurl'] !== 'true')
		{
			// url is not unfurled
			return;
		}

		$url = $processor->renderSubTreePlain($tag['children']);

		/** @var UnfurlRepository $unfurlRepo */
		$unfurlRepo = \XF::repository(UnfurlRepository::class);
		$unfurl = $unfurlRepo->getUnfurlResultByUrl($url);
		if ($unfurl)
		{
			$this->unfurls[$unfurl->result_id] = $unfurl->result_id;
		}
	}

	public function analyzeEmbedUsage($string, Processor $processor): void
	{
		if (preg_match_all('#\[EMBED\s+content="(?<content_type>[a-z0-9_]+)-(?<content_id>\d+)"].*\[/EMBED]#ium', $string, $matches, PREG_SET_ORDER))
		{
			/** @var EmbedResolverRepository $embedRepo */
			$embedRepo = \XF::repository(EmbedResolverRepository::class);

			foreach ($matches AS $match)
			{
				$isValid = $embedRepo->isValidEmbed($match['content_type'], $match['content_id']);
				if ($isValid)
				{
					$this->embeds[$match['content_type']][$match['content_id']] = $match['content_id'];
				}
			}
		}
	}

	public function analyzeAttachTag(array $tag, array $options, $finalOutput, Processor $processor)
	{
		if (!$finalOutput)
		{
			// was stripped
			return;
		}

		$id = intval($processor->renderSubTreePlain($tag['children']));
		if ($id)
		{
			$this->attachments[$id] = $id;
		}
	}

	public function analyzeImgTag(array $tag, array $options, $finalOutput, Processor $processor): void
	{
		if (empty($tag['children']))
		{
			return;
		}

		$url = $tag['children'][0];

		if (!preg_match('#^https?://#i', $url))
		{
			return;
		}

		$urlParts = @parse_url($url);
		if (!$urlParts || empty($urlParts['host']))
		{
			return;
		}

		$size = $tag['option']['size'] ?? null;
		$hash = md5($url);

		if ($size)
		{
			$parts = explode('x', $size);
			if (count($parts) !== 2)
			{
				return;
			}

			[$width, $height] = $parts;
			$width = (int) $width;
			$height = (int) $height;
		}
		else if (\XF::options()->imageLinkProxy['images'])
		{
			$imageProxyService = \XF::service(ImageProxyService::class);
			$proxiedImage = $imageProxyService->getImage($url);

			if ($proxiedImage)
			{
				$width = $proxiedImage->width;
				$height = $proxiedImage->height;
			}
		}

		$this->images[$hash] = [
			'url' => $url,
			'width' => $width ?? null,
			'height' => $height ?? null,
		];
	}

	public function analyzeQuoteTag(array $tag, array $options, $finalOutput)
	{
		if (!$finalOutput)
		{
			// was stripped
			return;
		}

		if (!empty($tag['option']))
		{
			$optionParts = explode(',', $tag['option']);
			$attributes = [];
			foreach ($optionParts AS $part)
			{
				$pair = explode(':', trim($part), 2);
				if (isset($pair[1]))
				{
					$attributes[trim($pair[0])] = trim($pair[1]);
				}
				else
				{
					$attributes['source'] = $pair[0];
				}
			}

			if ($attributes)
			{
				$this->quotes[] = $attributes;
			}
		}
	}

	public function incrementTagCount($tag)
	{
		$this->adjustTagCount($tag, 1);
	}

	public function adjustTagCount($tag, $adjust)
	{
		if (!isset($this->tagCount[$tag]))
		{
			$this->tagCount[$tag] = 0;
		}

		$this->tagCount[$tag] += $adjust;
		if ($this->tagCount[$tag] <= 0)
		{
			unset($this->tagCount[$tag]);
		}
	}

	public static function factory(App $app)
	{
		return new static($app->stringFormatter());
	}
}
