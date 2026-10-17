<?php

namespace XF\Service\Icon;

use XF\App;
use XF\ContinuationResult;
use XF\Data\Currency;
use XF\Data\Editor;
use XF\Data\FileType;
use XF\Data\FontAwesome;
use XF\Finder\ConnectedAccountProviderFinder;
use XF\MultiPartRunnerTrait;
use XF\Mvc\Entity\Entity;
use XF\Repository\BbCodeRepository;
use XF\Repository\EditorRepository;
use XF\Repository\ForumTypeRepository;
use XF\Repository\IconRepository;
use XF\Service\AbstractService;
use XF\Util\File;

use function count, in_array;

class UsageAnalyzerService extends AbstractService
{
	use MultiPartRunnerTrait;

	/**
	 * @var string
	 */
	protected const JS_DIRECTORY = 'js/';

	/**
	 * @var string
	 */
	protected const ICON_HTML_REGEX = '<xf:fa[^>]*\sicon="(?P<classes>[^"]+)"[^>]*\/>';

	/**
	 * @var string
	 */
	protected const ICON_HTML_INLINE_REGEX = '<xf:fa[^>]*\sinline="[^"]+"[^>]*\/>';

	/**
	 * @var string
	 */
	protected const ICON_HTML_ELEMENT_REGEX = '<xf:[a-z]+[^>]*\sfa="(?P<classes>[^"]+)"[^>]*>';

	/**
	 * @var string
	 */
	protected const ICON_JS_REGEX = 'XF\.Icon\.getIcon\(\s*(?P<qv>"|\')(?P<variant>[A-Za-z]+)\k<qv>,\s*(?P<qn>"|\')(?P<name>[A-Za-z0-9-]+)\k<qn>[^)]*\)';

	/**
	 * @var string|null
	 */
	protected $contentType;

	/**
	 * @var mixed[][]
	 */
	protected $iconMetadata;

	/**
	 * @var IconRepository
	 */
	protected $iconRepo;

	/**
	 * @var string[]
	 */
	protected $defaultVariants;

	/**
	 * @var string[][][][]
	 */
	protected $icons = [];

	public function __construct(App $app, ?string $contentType = null)
	{
		parent::__construct($app);

		$this->setContentType($contentType);
		$this->iconMetadata = $this->app->data(FontAwesome::class)->getIconMetadata();

		$this->iconRepo = $this->repository(IconRepository::class);
		$this->defaultVariants = $this->iconRepo->getDefaultVariants();
	}

	protected function setContentType(?string $contentType): void
	{
		if ($contentType === null)
		{
			return;
		}

		$contentTypeSteps = $this->getContentTypeSteps();
		if (!isset($contentTypeSteps[$contentType]))
		{
			throw new \InvalidArgumentException(
				"Invalid content type: {$contentType}"
			);
		}

		$this->contentType = $contentType;
	}

	public function getContentType(): ?string
	{
		return $this->contentType;
	}

	public function analyze(float $maxRunTime = 0): ContinuationResult
	{
		return $this->runLoop($maxRunTime);
	}

	/**
	 * @param int|string|null $contentId
	 *
	 * @return string[][]|string[][][]|string[][][][]
	 */
	public function getIcons(
		?string $contentType = null,
		$contentId = null
	): array
	{
		if ($contentType !== null)
		{
			if ($contentId !== null)
			{
				return $this->icons[$contentType][$contentId] ?? [];
			}

			return $this->icons[$contentType] ?? [];
		}

		return $this->icons;
	}

	/**
	 * @return string[][]
	 */
	protected function getContentTypeSteps(): array
	{
		$steps = [
			'admin_navigation' => ['stepAdminNavigation'],
			'connected_account' => ['stepConnectedAccount'],
			'editor' => ['stepEditor'],
			'javascript' => ['stepJavascript'],
			'option_group' => ['stepOptionGroup'],
			'data' => ['stepButton', 'stepCurrency', 'stepFileType'],
			'forum_type' => ['stepForumType'],
			'thread_type' => ['stepThreadType'],
			'phrase' => ['stepPhrase'],
			'style_property' => ['stepStyleProperty'],
			'template' => ['stepTemplateHtml', 'stepTemplateLess'],
			'template_modification' => [
				'stepTemplateModificationHtml',
				'stepTemplateModificationLess',
			],
			'extra' => ['stepExtra'],
		];

		$this->app->fire('icon_usage_analyzer_steps', [&$steps, $this]);

		return $steps;
	}

	/**
	 * @return string[]
	 */
	protected function getSteps(): array
	{
		$contentTypeSteps = $this->getContentTypeSteps();

		$contentType = $this->getContentType();
		if ($contentType !== null)
		{
			return $contentTypeSteps[$contentType];
		}

		$allSteps  = [];

		foreach ($contentTypeSteps AS $contentType => $steps)
		{
			foreach ($steps AS $step)
			{
				$allSteps[] = $step;
			}
		}

		return $allSteps;
	}

	protected function stepAdminNavigation(
		?int $lastOffset,
		float $maxRunTime
	): ?int
	{
		$dataPairs = $this->fetchDataPairs(
			'xf_admin_navigation',
			'navigation_id'
		);
		$this->recordIconsFromClassPairs(
			'admin_navigation',
			$dataPairs,
			$this->getDefaultVariantForStyle(0)
		);
		$this->recordIconsFromClassPairs(
			'admin_navigation',
			$dataPairs,
			'duotone'
		);

		return null;
	}

	protected function stepEditor(?int $lastOffset, float $maxRunTime): ?int
	{

		$editorData = $this->app->data(Editor::class);

		$customBbCodes = $this->repository(BbCodeRepository::class)->findActiveBbCodes()
			->where('editor_icon_type', 'fa')
			->where('active', true)
			->fetch();
		$dropdowns = $this->repository(EditorRepository::class)->findEditorDropdownsForList()
			->where('active', true)
			->fetch();
		$editorButtons = $editorData->getCombinedButtonData(
			$customBbCodes,
			$dropdowns
		);
		$classPairs = $this->getIconsFromColumn($editorButtons);
		$this->recordIconsFromClassPairs('editor', $classPairs);

		$froalaIconClasses = $editorData->getFroalaIconClasses();
		foreach ($froalaIconClasses AS $classes)
		{
			$this->recordIconsFromClasses('editor', 'froala', $classes);
		}

		return null;
	}

	protected function stepConnectedAccount(
		?int $lastOffset,
		float $maxRunTime
	): ?int
	{
		$providers = $this->finder(ConnectedAccountProviderFinder::class)->fetch();
		$classPairs = $this->getIconsFromColumn(
			$providers->toArray(),
			'icon_class'
		);
		$this->recordIconsFromClassPairs(
			'connected_account',
			$classPairs
		);

		return null;
	}

	protected function stepJavascript(
		?int $lastOffset,
		float $maxRunTime
	): ?int
	{
		$jsPath = File::canonicalizePath(static::JS_DIRECTORY);
		$files = File::getRecursiveDirectoryIterator($jsPath);

		foreach ($files AS $file)
		{
			if ($file->isDir())
			{
				continue;
			}

			$fileName = $file->getFilename();
			if ($fileName[0] === '.')
			{
				continue;
			}

			if ($file->getExtension() !== 'js')
			{
				continue;
			}

			if (!$file->isReadable())
			{
				continue;
			}

			$pathname = $file->getPathname();
			$relativePathname = File::stripRootPathPrefix($pathname);

			$contentId = md5($relativePathname);
			$contents = file_get_contents($pathname);

			$this->recordIconsFromJavascript(
				'javascript',
				$contentId,
				$contents
			);
		}

		return null;
	}

	protected function stepOptionGroup(?int $lastOffset, float $maxRunTime): ?int
	{
		$dataPairs = $this->fetchDataPairs('xf_option_group', 'group_id');
		$this->recordIconsFromClassPairs(
			'option_group',
			$dataPairs,
			$this->getDefaultVariantForStyle(0)
		);

		return null;
	}

	protected function stepButton(?int $lastOffset, float $maxRunTime): ?int
	{
		$classPairs = $this->iconRepo->getButtonIconMap();
		$this->recordIconsFromClassPairs('button', $classPairs);

		return null;
	}

	protected function stepCurrency(
		?int $lastOffset,
		float $maxRunTime
	): ?int
	{
		$currencyData = $this->app->data(Currency::class)->getCurrencyData();
		$classPairs = $this->getIconsFromColumn($currencyData);
		$this->recordIconsFromClassPairs('currency', $classPairs);

		return null;
	}

	protected function stepFileType(
		?int $lastOffset,
		float $maxRunTime
	): ?int
	{
		$fileTypeData = $this->app->data(FileType::class);

		$classPairs = [];
		$extensions = array_keys($fileTypeData->getExtensionMap());
		foreach ($extensions AS $extension)
		{
			$classPairs[$extension] = $fileTypeData->getIcon($extension);
		}

		$this->recordIconsFromClassPairs('file_type', $classPairs);

		return null;
	}

	protected function stepForumType(
		?int $lastOffset,
		float $maxRunTime
	): ?int
	{
		$forumTypeRepo = $this->repository(ForumTypeRepository::class);
		$handlers = $forumTypeRepo->getForumTypeHandlers();

		$classPairs = [];
		foreach ($handlers AS $typeId => $handler)
		{
			$classPairs[$typeId] = $handler->getTypeIconClass();
		}

		$this->recordIconsFromClassPairs('forum_type', $classPairs);

		return null;
	}

	protected function stepThreadType(
		?int $lastOffset,
		float $maxRunTime
	): ?int
	{
		$threadTypes = array_keys($this->app->container('threadTypes'));

		$classPairs = [];
		foreach ($threadTypes AS $typeId)
		{
			$handler = $this->app->threadType($typeId, false);
			if (!$handler)
			{
				continue;
			}

			$classPairs[$typeId] = $handler->getTypeIconClass();
		}

		$this->recordIconsFromClassPairs('thread_type', $classPairs);

		return null;
	}

	protected function stepPhrase(?int $lastOffset, float $maxRunTime): ?int
	{
		$batchSize = 500;

		$dataPairs = $this->fetchDataPairs(
			'xf_phrase',
			'phrase_id',
			'phrase_text',
			"phrase_text LIKE '%{icon:%'",
			$batchSize,
			$lastOffset
		);

		$phraseRegex = IconRepository::ICON_PHRASE_REGEX;
		foreach ($dataPairs AS $phraseId => $phraseText)
		{
			if (!preg_match_all(
				"/{$phraseRegex}/i",
				$phraseText,
				$matches,
				PREG_SET_ORDER
			))
			{
				continue;
			}

			foreach ($matches AS $icon)
			{
				$classes = $icon['variant'] . ' fa-' . $icon['name'];
				$this->recordIconsFromClasses('phrase', $phraseId, $classes);
			}
		}

		return $this->getLastOffset($dataPairs, $batchSize);
	}

	protected function stepStyleProperty(?int $lastOffset, float $maxRunTime): ?int
	{
		$batchSize = 500;

		$dataPairs = $this->fetchDataPairs(
			'xf_style_property',
			'property_id',
			'property_value',
			"property_value LIKE '%@fa-var-%'",
			$batchSize,
			$lastOffset
		);
		$this->recordIconsFromLessPairs('style_property', $dataPairs);

		return $this->getLastOffset($dataPairs, $batchSize);
	}

	protected function stepTemplateHtml(?int $lastOffset, float $maxRunTime): ?int
	{
		$batchSize = 500;

		$excludedTemplates = $this->db()->quote(
			$this->getExcludedHtmlTemplates()
		);
		$excludedTemplatesClause = $excludedTemplates
			? "AND title NOT IN ({$excludedTemplates})"
			: '';

		$data = $this->db()->fetchAll(
			$this->db()->limit(
				"SELECT template_id, style_id, template
					FROM xf_template
					WHERE template_id > ? AND
					    title NOT LIKE '%.less' AND
						title NOT LIKE '%.css'
						{$excludedTemplatesClause}
					ORDER BY template_id",
				$batchSize
			),
			[$lastOffset ?? 0]
		);

		$groupedDataPairs = [];
		foreach ($data AS $item)
		{
			$groupedDataPairs[$item['style_id']][$item['template_id']] = $item['template'];
		}

		foreach ($groupedDataPairs AS $styleId => $dataPairs)
		{
			$this->recordIconsFromHtmlPairs('template', $dataPairs);
		}

		return $this->getLastOffset(
			$groupedDataPairs ? array_replace(...$groupedDataPairs) : [],
			$batchSize
		);
	}

	/**
	 * @return string[]
	 */
	protected function getExcludedHtmlTemplates(): array
	{
		return [];
	}

	protected function stepTemplateLess(?int $lastOffset, float $maxRunTime): ?int
	{
		$batchSize = 500;

		$excludedTemplates = $this->db()->quote(
			$this->getExcludedLessTemplates()
		);
		$excludedTemplatesClause = $excludedTemplates
			? "AND title NOT IN ({$excludedTemplates})"
			: '';

		$dataPairs = $this->fetchDataPairs(
			'xf_template',
			'template_id',
			'template',
			"title LIKE '%.less' {$excludedTemplatesClause}",
			$batchSize,
			$lastOffset
		);
		$this->recordIconsFromLessPairs('template', $dataPairs);

		return $this->getLastOffset($dataPairs, $batchSize);
	}

	/**
	 * @return string[]
	 */
	protected function getExcludedLessTemplates(): array
	{
		return ['setup_fa.less'];
	}

	protected function stepTemplateModificationHtml(
		?int $lastOffset,
		float $maxRunTime
	): ?int
	{
		$dataPairs = $this->fetchDataPairs(
			'xf_template_modification',
			'modification_id',
			'replace',
			"template NOT LIKE '%.less' AND template NOT LIKE '%.css'"
		);
		$this->recordIconsFromHtmlPairs('template_modification', $dataPairs);

		return null;
	}

	protected function stepTemplateModificationLess(
		?int $lastOffset,
		float $maxRunTime
	): ?int
	{
		$dataPairs = $this->fetchDataPairs(
			'xf_template_modification',
			'modification_id',
			'replace',
			"template LIKE '%.less'"
		);
		$this->recordIconsFromLessPairs('template_modification', $dataPairs);

		return null;
	}

	protected function stepExtra(?int $lastOffset, float $maxRunTime): ?int
	{
		$classPairs = $this->iconRepo->getExtraIcons();
		$this->recordIconsFromClassPairs('extra', $classPairs);

		$optionClassPairs = preg_split('/\r?\n/', \XF::options()->extraFaIcons ?? '');
		$this->recordIconsFromClassPairs('extra', $optionClassPairs);

		return null;
	}

	/**
	 * @return string[]
	 */
	public function fetchDataPairs(
		string $table,
		string $primaryKey,
		string $iconColumn = 'icon',
		?string $where = null,
		?int $batchSize = null,
		?int $offset = null
	): array
	{
		if ($where !== null)
		{
			$where = "AND {$where}";
		}

		return $this->db()->fetchPairs(
			$this->db()->limit(
				"SELECT `{$primaryKey}`, `{$iconColumn}`
					FROM `{$table}`
					WHERE `{$iconColumn}` <> '' {$where}
					ORDER BY `{$primaryKey}`",
				$batchSize,
				$offset
			)
		);
	}

	/**
	 * @param string[] $dataPairs
	 */
	public function getLastOffset(array $dataPairs, int $batchSize): ?int
	{
		if (count($dataPairs) !== $batchSize)
		{
			return null;
		}

		return max(array_keys($dataPairs));
	}

	/**
	 * @param string[][]|Entity[] $data
	 *
	 * @return string[]
	 */
	public function getIconsFromColumn(
		array $data,
		string $column = 'fa'
	): array
	{
		return array_filter(array_map(
			function ($item) use ($column): ?string
			{
				return $item[$column] ?? null;
			},
			$data
		));
	}

	public function getDefaultVariantForStyle(int $styleId): string
	{
		if (!isset($this->defaultVariants[$styleId]))
		{
			throw new \InvalidArgumentException("Invalid style ID: {$styleId}");
		}

		return $this->defaultVariants[$styleId];
	}

	/**
	 * @param int|string $contentId
	 */
	public function recordIcon(
		string $contentType,
		$contentId,
		string $usageType,
		?string $iconVariant,
		string $iconName
	): void
	{
		if (!in_array($usageType, IconRepository::USAGE_TYPES))
		{
			throw new \InvalidArgumentException(
				"Invalid icon type: {$usageType}"
			);
		}

		if ($iconVariant !== null)
		{
			if (!in_array($iconVariant, IconRepository::ICON_VARIANTS))
			{
				throw new \InvalidArgumentException(
					"Invalid icon variant: {$iconVariant}"
				);
			}
		}
		else
		{
			$iconVariant = 'default';
		}

		if (!isset($this->iconMetadata[$iconName]))
		{
			return;
		}

		$this->icons[$contentType][$contentId][] = [
			'usage_type' => $usageType,
			'icon_variant' => $iconVariant,
			'icon_name' => $iconName,
		];
	}

	/**
	 * @param int|string $contentId
	 */
	public function recordIconsFromClasses(
		string $contentType,
		$contentId,
		string $classes,
		?string $defaultIconVariant = null,
		bool $standalone = false
	): void
	{
		$icons = $this->iconRepo->getIconsFromClasses($classes);
		if (!$icons)
		{
			return;
		}

		$usageType = $standalone ? 'standalone' : 'sprite';

		foreach ($icons AS $icon)
		{
			if ($icon['variant'] === null)
			{
				$icon['variant'] = $defaultIconVariant;
			}

			$this->recordIcon(
				$contentType,
				$contentId,
				$usageType,
				$icon['variant'],
				$icon['name']
			);
		}
	}

	/**
	 * @param string[] $classPairs
	 */
	public function recordIconsFromClassPairs(
		string $contentType,
		array $classPairs,
		?string $defaultIconVariant = null
	): void
	{
		foreach ($classPairs AS $contentId => $classes)
		{
			$this->recordIconsFromClasses(
				$contentType,
				$contentId,
				$classes,
				$defaultIconVariant
			);
		}
	}

	/**
	 * @param int|string $contentId
	 */
	public function recordIconsFromHtml(
		string $contentType,
		$contentId,
		string $html,
		?string $defaultIconVariant = null
	): void
	{
		$htmlRegex = static::ICON_HTML_REGEX;
		$inlineRegex = static::ICON_HTML_INLINE_REGEX;
		if (preg_match_all("/{$htmlRegex}/i", $html, $matches, PREG_SET_ORDER))
		{
			foreach ($matches AS $match)
			{
				// string deliminators may be found in template expressions
				$classes = str_replace(['"', "'"], '', $match['classes']);

				$standalone = preg_match("/{$inlineRegex}/i", $match[0])
					? true
					: false;

				$this->recordIconsFromClasses(
					$contentType,
					$contentId,
					$classes,
					$defaultIconVariant,
					$standalone
				);
			}
		}

		$elementRegex = static::ICON_HTML_ELEMENT_REGEX;
		if (preg_match_all("/{$elementRegex}/i", $html, $matches))
		{
			foreach ($matches['classes'] AS $classes)
			{
				$this->recordIconsFromClasses(
					$contentType,
					$contentId,
					$classes,
					$defaultIconVariant
				);
			}
		}
	}

	/**
	 * @param string[] $htmlPairs
	 */
	public function recordIconsFromHtmlPairs(
		string $contentType,
		array $htmlPairs,
		?string $defaultIconVariant = null
	): void
	{
		foreach ($htmlPairs AS $contentId => $html)
		{
			$this->recordIconsFromHtml(
				$contentType,
				$contentId,
				$html,
				$defaultIconVariant
			);
		}
	}

	/**
	 * @param int|string $contentId
	 */
	public function recordIconsFromLess(
		string $contentType,
		$contentId,
		string $less
	): void
	{
		$iconRegex = IconRepository::ICON_LESS_REGEX;
		if (!preg_match_all("/@{$iconRegex}/i", $less, $matches, PREG_SET_ORDER))
		{
			return;
		}

		foreach ($matches AS $icon)
		{
			if (empty($icon['variant']))
			{
				// we support @fa-var-icon brand icons for backwards compatibility,
				// so we need to check brand status manually
				$metadata = $this->iconMetadata[$icon['name']] ?? null;
				$brand = $metadata['is_brand'] ?? false;
				$icon['variant'] = $brand ? 'brands' : null;
			}

			$this->recordIcon(
				$contentType,
				$contentId,
				'standalone',
				$icon['variant'],
				$icon['name']
			);
		}
	}

	/**
	 * @param string[] $lessPairs
	 */
	public function recordIconsFromLessPairs(
		string $contentType,
		array $lessPairs
	): void
	{
		foreach ($lessPairs AS $contentId => $less)
		{
			$this->recordIconsFromLess($contentType, $contentId, $less);
		}
	}

	/**
	 * @param int|string $contentId
	 */
	public function recordIconsFromJavascript(
		string $contentType,
		$contentId,
		string $contents
	): void
	{
		$jsRegex = static::ICON_JS_REGEX;
		if (!preg_match_all("/{$jsRegex}/", $contents, $matches, PREG_SET_ORDER))
		{
			return;
		}

		foreach ($matches AS $icon)
		{
			if (
				$icon['variant'] !== null &&
				!in_array($icon['variant'], IconRepository::ICON_VARIANTS)
			)
			{
				$icon['variant'] = 'regular';
			}

			if ($icon['variant'] === 'default')
			{
				$icon['variant'] = null;
			}

			$this->recordIcon(
				$contentType,
				$contentId,
				'sprite',
				$icon['variant'],
				$icon['name']
			);
		}
	}
}
