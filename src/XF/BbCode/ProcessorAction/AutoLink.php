<?php

namespace XF\BbCode\ProcessorAction;

use XF\App;
use XF\BbCode\Processor;
use XF\Http\Metadata;
use XF\Repository\BbCodeMediaSiteRepository;
use XF\Repository\EmbedResolverRepository;
use XF\Repository\UnfurlRepository;
use XF\Service\OembedService;
use XF\Util\Url;

use function array_key_exists, count, is_array, strlen;

class AutoLink implements FiltererInterface
{
	/**
	 * @var App
	 */
	protected $app;

	protected $autoEmbed = true;
	protected $autoEmbedLink = '';
	protected $maxEmbed = PHP_INT_MAX;
	protected $embedSites = [];

	protected $embedRemaining = PHP_INT_MAX;

	protected $urlToPageTitle = false;
	protected $urlToTitleFormat = '';
	protected $urlToTitleTimeLimit = 10;

	protected $urlToRichPreview = true;
	protected $urlUnfurl = true;

	protected $urlToEmbedResolver = true;

	/**
	 * @var Metadata[]
	 */
	protected $unfurlCache = [];

	protected $startTime;

	public function __construct(App $app, array $config = [])
	{
		$this->app = $app;

		$baseConfig = [
			'autoEmbed' => true,
			'autoEmbedLink' => '',
			'maxEmbed' => PHP_INT_MAX,
			'embedSites' => [],
			'urlToPageTitle' => false,
			'urlToTitleFormat' => '',
			'urlToTitleTimeLimit' => 10,
			'urlToRichPreview' => true,
			'urlToEmbedResolver' => true,
		];
		$config = array_replace($baseConfig, $config);

		$this->autoEmbed = $config['autoEmbed'];
		$this->autoEmbedLink = $config['autoEmbedLink'];
		$this->maxEmbed = $config['maxEmbed'];
		$this->embedSites = $config['embedSites'];

		$this->urlToPageTitle = $config['urlToPageTitle'];
		$this->urlToTitleFormat = $config['urlToTitleFormat'];
		$this->urlToTitleTimeLimit = $config['urlToTitleTimeLimit'];

		$this->urlToRichPreview = $config['urlToRichPreview'];
		$this->urlToEmbedResolver = $config['urlToEmbedResolver'];

		$this->startTime = microtime(true);
	}

	public function addFiltererHooks(FiltererHooks $hooks)
	{
		$hooks->addSetupHook('filterSetup')
			->addStringHook('filterString')
			->addTagHook('url', 'filterUrlTag');
	}

	public function enableUnfurling($enable = true)
	{
		$this->urlToRichPreview = (bool) $enable;

		return $this;
	}

	public function filterSetup(array $ast)
	{
		$this->embedRemaining = $this->maxEmbed;

		$mediaTotal = 0;
		$f = function (array $tree) use (&$mediaTotal, &$f)
		{
			foreach ($tree AS $entry)
			{
				if (is_array($entry))
				{
					if ($entry['tag'] == 'media')
					{
						$mediaTotal++;
					}

					$f($entry['children']);
				}
			}
		};

		$f($ast);

		$this->embedRemaining -= $mediaTotal;
	}

	public function filterUrlTag(array $tag, array $options, Processor $processor)
	{
		if ($this->autoEmbed)
		{
			$url = $processor->renderSubTreePlain($tag['children']);
			if (empty($tag['option']) || $tag['option'] == $url)
			{
				$output = $this->autoLinkUrl($url);

				if ($output)
				{
					return $output;
				}
			}
		}

		return null;
	}

	public function filterString($string, array $options, Processor $processor)
	{
		if (!empty($options['stopAutoLink']))
		{
			return $string;
		}

		$autoLinkRegex = '(?<=[^a-z0-9@/\.-]|^)(?<!\]\(|url=(?:"|\')|url\]|url\sunfurl=(?:"|\')true(?:"|\')\]|img|embed\])(https?://|www\.)(?!\.)[^\s"<>{}`]+';
		$unfurlLinkRegex = '^' . $autoLinkRegex . '$';

		$placeholders = [];

		if ($this->urlToRichPreview || $this->urlToEmbedResolver)
		{
			// attempt to unfurl or embed URLs if enabled
			$string = preg_replace_callback(
				'#' . $unfurlLinkRegex . '#ium',
				function ($match) use ($processor, &$placeholders)
				{
					$output = null;

					if ($this->urlToEmbedResolver)
					{
						$output = $this->embedLinkUrl($match[0]);
					}

					if (!$output && $this->urlToRichPreview)
					{
						$output = $this->unfurlLinkUrl($match[0]);
					}

					if (!$output)
					{
						// cannot be unfurled, will be auto linked as normal.
						return $match[0];
					}

					$this->incrementMatchedTag($processor, $output);

					$replace = "\x1A" . count($placeholders) . "\x1A";
					$placeholders[$replace] = $output;

					return $replace;
				},
				$string
			);
		}

		$string = preg_replace_callback(
			'#' . $autoLinkRegex . '#iu',
			function ($match) use ($processor, &$placeholders)
			{
				if (preg_match('#\[/embed]$#ium', $match[0]))
				{
					return $match[0];
				}

				$output = $this->preAutoLinkUrl($match[0], $processor);
				if (!$output)
				{
					return $match[0];
				}
				$this->incrementMatchedTag($processor, $output);

				$replace = "\x1A" . count($placeholders) . "\x1A";
				$placeholders[$replace] = $output;

				return $replace;
			},
			$string
		);

		if (strpos($string, '@') !== false)
		{
			// assertion to prevent matching email in url matched above (user:pass@example.com)
			$string = preg_replace_callback(
				'#[a-z0-9.+_-]+@[a-z0-9-]+(\.[a-z0-9-]+)+(?![^\s"]*\[/url\])#iu',
				function ($match) use ($processor)
				{
					$this->incrementTagUsageCount($processor, 'email');
					return '[email]' . $match[0] . '[/email]';
				},
				$string
			);
		}

		if ($placeholders)
		{
			$string = strtr($string, $placeholders);
		}

		return $string;
	}

	public function preAutoLinkUrl($url, Processor $processor)
	{
		// if we have a limit tags filterer and auto embed is enabled, disable
		// auto embedding if the media tag is disabled, otherwise auto linking
		// may bypass the limiting of the tag.
		$limit = $processor->getFilterer('limit');

		if ($limit && $limit instanceof LimitTags && $this->autoEmbed)
		{
			if ($limit->isTagDisabled('media'))
			{
				$this->autoEmbed = false;
			}
		}

		return $this->autoLinkUrl($url);
	}

	public function autoLinkUrl($url)
	{
		$link = $this->app->stringFormatter()->prepareAutoLinkedUrl($url);

		if (!$link['url'])
		{
			return null;
		}

		if (Url::urlToUtf8($link['url'], false) === Url::urlToUtf8($link['linkText'], false))
		{
			$mediaTag = $this->getMediaTagIfPermitted($link['url']);

			if ($mediaTag)
			{
				$tag = $mediaTag;
				$this->embedRemaining--;
			}
			else
			{
				$tag = $this->getUrlBbCode($link['url']);
			}
		}
		else
		{
			$tag = '[URL="' . $link['url'] . '"]' . $link['linkText'] . '[/URL]';
		}

		return $tag . $link['suffixText'];
	}

	protected function getMediaTagIfPermitted($url)
	{
		if (!$this->autoEmbed || !$this->embedRemaining)
		{
			return false;
		}

		return $this->getMediaBbCode($url);
	}

	protected function getUrlBbCode($url)
	{
		if ($this->urlToPageTitle)
		{
			$title = $this->getUrlTitle($url);
			if ($title)
			{
				$format = $this->urlToTitleFormat ?: '{title}';
				$tokens = [
					'{title}' => $title,
					'{url}' => $url,
				];
				$linkTitle = strtr($format, $tokens);

				return '[URL="' . $url . '"]' . $linkTitle . '[/URL]';
			}
		}

		$linkText = Url::urlToUtf8($url, false);
		if ($linkText !== $url)
		{
			return '[URL="' . $url . '"]' . $linkText . '[/URL]';
		}

		return '[URL]' . $url . '[/URL]';
	}

	protected function fetchMetadataFromUrl($requestUrl)
	{
		if (array_key_exists($requestUrl, $this->unfurlCache))
		{
			return $this->unfurlCache[$requestUrl];
		}

		$fetcher = $this->app->http()->metadataFetcher();

		$metadata = $fetcher->fetch($requestUrl, $null, $this->startTime, $this->urlToTitleTimeLimit);
		if (!$metadata)
		{
			return null;
		}

		$this->unfurlCache[$requestUrl] = $metadata;

		return $metadata;
	}

	protected function getUrlTitle($url)
	{
		$metadata = $this->fetchMetadataFromUrl($url);

		if (!$metadata)
		{
			return false;
		}

		$title = $metadata->getTitle();

		if (!strlen($title))
		{
			return false;
		}

		$bbCodeContainer = $this->app->bbCode();

		/** @var AnalyzeUsage $usage */
		$usage = $bbCodeContainer->processorAction('usage');

		$bbCodeContainer->processor()
			->addProcessorAction('usage', $usage)
			->render($title, $bbCodeContainer->parser(), $bbCodeContainer->rules('base'));

		if ($usage->getSmilieCount() || $usage->getTotalTagCount())
		{
			$title = "[PLAIN]{$title}[/PLAIN]";
		}

		return $title;
	}

	protected function prepareAutoLinkedUrl(string $url)
	{
		$link = $this->app->stringFormatter()->prepareAutoLinkedUrl($url, ['processTrailers' => false]);
		if (!$link['url'])
		{
			return false;
		}

		if (Url::urlToUtf8($link['url'], false) !== Url::urlToUtf8($link['linkText'], false))
		{
			return false;
		}

		$mediaTag = $this->getMediaTagIfPermitted($link['url']);
		if ($mediaTag)
		{
			// can't unfurl as matches as media, autolinking will pick it up
			return false;
		}

		return $link;
	}

	public function unfurlLinkUrl($url)
	{
		$link = $this->prepareAutoLinkedUrl($url);

		if (!$link)
		{
			return false;
		}

		return $this->getUnfurlBbCode($link['url']) . $link['suffixText'];
	}

	protected function getUnfurlBbCode($url)
	{
		/** @var UnfurlRepository $unfurlRepo */
		$unfurlRepo = $this->app->repository(UnfurlRepository::class);
		$result = $unfurlRepo->logPendingUnfurl($url);

		if ($result)
		{
			return '[URL unfurl="true"]' . $result->url . '[/URL]';
		}
		else
		{
			return false;
		}
	}

	public function embedLinkUrl(string $url)
	{
		$link = $this->prepareAutoLinkedUrl($url);

		if (!$link)
		{
			return false;
		}

		return $this->getEmbedBbCode($link['url']) . $link['suffixText'];
	}

	protected function getEmbedBbCode(string $url)
	{
		/** @var EmbedResolverRepository $embedRepo */
		$embedRepo = $this->app->repository(EmbedResolverRepository::class);
		$content = $embedRepo->getEntityFromUrl($url);

		if ($content)
		{
			return '[EMBED content="' . $content->getEntityContentTypeId() . '"]' . $url . '[/EMBED]';
		}

		return false;
	}

	protected function getMediaBbCode($url)
	{
		$match = $this->app->repository(BbCodeMediaSiteRepository::class)->urlMatchesMediaSiteList($url, $this->embedSites);
		if (!$match)
		{
			return null;
		}

		$matchBbCode = '[MEDIA=' . $match['media_site_id'] . ']' . $match['media_id'] . '[/MEDIA]';

		if (!empty($match['site']->oembed_enabled))
		{
			$this->cacheOembedResponse($match['site'], $match['media_id']);
		}

		if ($this->autoEmbedLink)
		{
			$matchBbCode .= "\n" . str_replace('{$url}', "{$url}", $this->autoEmbedLink) . "\n";
		}

		return $matchBbCode;
	}

	protected function cacheOembedResponse($site, $mediaId)
	{
		/** @var OembedService $oEmbedService */
		$oEmbedService = $this->app->service(OembedService::class);
		$oEmbedService->getOembed($site->media_site_id, $mediaId);
	}

	protected function incrementMatchedTag(Processor $processor, $output)
	{
		if (preg_match('#^\[(\w+)#i', $output, $match))
		{
			$this->incrementTagUsageCount($processor, strtolower($match[1]));
		}
	}

	protected function incrementTagUsageCount(Processor $processor, $tag)
	{
		$this->adjustTagUsageCount($processor, $tag, 1);
	}

	protected function adjustTagUsageCount(Processor $processor, $tag, $adjust)
	{
		$usage = $processor->getAnalyzer('usage');
		if ($usage && $usage instanceof AnalyzeUsage)
		{
			$usage->adjustTagCount($tag, $adjust);
		}
	}

	public static function factory(App $app, array $config = [])
	{
		$options = $app->options();

		$autoEmbed = $options->autoEmbedMedia;

		$baseConfig = [
			'autoEmbed' => (bool) $autoEmbed['embedType'], // 0 is false, otherwise true
			'autoEmbedLink' => $autoEmbed['embedType'] == 2 ? $autoEmbed['linkBbCode'] : '',
			'maxEmbed' => ($options->messageMaxMedia ?: PHP_INT_MAX),
			'embedSites' => null,
			'urlToPageTitle' => $options->urlToPageTitle['enabled'],
			'urlToTitleFormat' => $options->urlToPageTitle['format'],
			'urlToRichPreview' => $options->urlToRichPreview,
			'urlToEmbedResolver' => $options->urlToEmbedResolver,
		];

		$config = array_replace($baseConfig, $config);
		if ($config['embedSites'] === null)
		{
			$config['embedSites'] = $app->repository(BbCodeMediaSiteRepository::class)->findActiveMediaSites()->fetch();
		}

		return new static($app, $config);
	}
}
