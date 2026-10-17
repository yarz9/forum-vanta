<?php

namespace XF\Repository;

use XF\Job\IconUsage;
use XF\Mvc\Entity\Repository;
use XF\Service\Icon\SpriteGeneratorService;
use XF\Util\File;

use function array_key_exists, in_array;

class IconRepository extends Repository
{
	/**
	 * @var string
	 */
	public const ICON_VERSION = '5.15.3';

	/**
	 * @var string
	 */
	public const ICON_DIRECTORY = 'styles/fa/';

	/**
	 * @var string[]
	 */
	public const USAGE_TYPES = ['sprite', 'standalone'];

	/**
	 * @var string[]
	 */
	public const ICON_VARIANTS = [
		'fab' => 'brands',
		'fad' => 'duotone',
		'fal' => 'light',
		'far' => 'regular',
		'fas' => 'solid',
	];

	/**
	 * @var string
	 */
	public const ICON_DATA_REGEX = '<svg [^>]*viewBox="(?P<viewBox>[^"]+)"[^>]*>.*?(?:<defs>.*<\/defs>)?.*?(?P<icon>(?:<path [^>]*\/>|<g>.*?<\/g>)+)<\/svg>';

	/**
	 * @var string
	 */
	public const ICON_CLASS_REGEX = '^fa-(?!-)(?P<name>[a-z0-9-]+)$';

	/**
	 * @var string
	 */
	public const ICON_CLASS_BLOCKLIST_REGEX = '^fa-(xs|sm|lg|\d+x|fw|ul|li|rotate-\d+|flip-(horizontal|vertical|both)|spin|pulse|border|pull-(left|right)|stack(-\dx)?|inverse)$';

	/**
	 * @var string
	 */
	public const ICON_LESS_REGEX = 'fa-var-((?P<variant>brands|duotone|light|regular|solid)-)?(?P<name>[a-z0-9-]+)';

	/**
	 * @var string
	 */
	public const ICON_PHRASE_REGEX = '\{icon(:(?P<variant>fa[bdlrs]))?::(?<name>[a-z0-9-]+)\}';

	public function getSpriteIconUrl(
		string $variant,
		string $name,
		bool $canonical = false
	): string
	{
		$path = $this->getSpriteIconPath(
			$variant,
			$name,
			$this->options()->iconSpriteLastUpdate
		);

		return $this->app()->applyLocalDataUrl($path, $canonical);
	}

	public function getSpriteIconPath(
		string $variant,
		string $name,
		?string $version = null
	): string
	{
		if ($version === null)
		{
			$path = sprintf('icons/%s.svg#%s', $variant, $name);
		}
		else
		{
			$path = sprintf('icons/%s.svg?v=%s#%s', $variant, $version, $name);
		}

		return $path;
	}

	public function getStandaloneIconUrl(
		string $variant,
		string $name,
		bool $canonical = false
	)
	{
		$path = $this->getStandaloneIconPath(
			$variant,
			$name,
			static::ICON_VERSION
		);

		$pathType = $canonical ? 'canonical' : 'root-base';

		$pather = $this->app()->container('request.pather');
		return $pather($path, $pathType);
	}

	public function getStandaloneIconPath(
		string $variant,
		string $name,
		?string $version = null
	): string
	{
		$path = static::ICON_DIRECTORY . $variant . '/' . $name . '.svg';

		if ($version !== null)
		{
			$path .= '?v=' . $version;
		}

		return $path;
	}

	/**
	 * @return string[]
	 */
	public function getIconData(string $variant, string $name): ?array
	{
		$path = File::canonicalizePath($this->getStandaloneIconPath($variant, $name));
		if (!file_exists($path))
		{
			return null;
		}

		$file = file_get_contents($path);
		if ($file === false)
		{
			return null;
		}

		$regex = static::ICON_DATA_REGEX;
		if (!preg_match("/{$regex}/i", $file, $matches))
		{
			return null;
		}

		return [
			'viewBox' => $matches['viewBox'],
			'icon' => $matches['icon'],
		];
	}

	public function enqueueUsageAnalyzer(?string $contentType = null): void
	{
		$uniqueId = 'iconUsage';
		if ($contentType !== null)
		{
			$uniqueId .= '-' . $contentType;
		}

		$this->app()->jobManager()->enqueueUnique(
			$uniqueId,
			IconUsage::class,
			['content_type' => $contentType],
			false
		);
	}

	/**
	 * @param string[][][][] $icons
	 */
	public function recordUsage(array $icons): void
	{
		$inserts = [];

		foreach ($icons AS $contentType => $groupedIcons)
		{
			foreach ($groupedIcons AS $contentId => $icons)
			{
				foreach ($icons AS $icon)
				{
					$inserts[] = [
						'content_type' => $contentType,
						'content_id' => $contentId,
						'usage_type' => $icon['usage_type'],
						'icon_variant' => $icon['icon_variant'],
						'icon_name' => $icon['icon_name'],
					];
				}
			}
		}

		if ($inserts)
		{
			$this->db()->insertBulk('xf_icon_usage', $inserts);
		}
	}

	/**
	 * @return array<string, array<string, true>>
	 */
	public function fetchUsageByType(string $usageType): array
	{
		if (!in_array($usageType, static::USAGE_TYPES))
		{
			throw new \InvalidArgumentException(
				"Invalid usage type: {$usageType}"
			);
		}

		$icons = $this->db()->fetchAll(
			'SELECT DISTINCT icon_variant, icon_name
				FROM xf_icon_usage
				WHERE usage_type = ?
				ORDER BY icon_name',
			[$usageType]
		);

		$groupedIcons = [];

		foreach ($icons AS $icon)
		{
			$groupedIcons[$icon['icon_variant']][$icon['icon_name']] = true;
		}

		return $groupedIcons;
	}

	public function purgeUsageRecords(?string $contentType = null): void
	{
		if ($contentType === null)
		{
			$this->db()->emptyTable('xf_icon_usage');
		}
		else
		{
			$this->db()->delete(
				'xf_icon_usage',
				'content_type = ?',
				[$contentType]
			);
		}
	}

	public function isIconSprited(string $variant, string $name): bool
	{
		$iconSprite = $this->app()->container('iconSprite');
		return $iconSprite[$variant][$name] ?? false;
	}

	/**
	 * @return array<string, array<string, true>>
	 */
	public function rebuildIconSpriteCache(): array
	{
		$cache = $this->fetchUsageByType('sprite');
		\XF::registry()->set('iconSprite', $cache);
		return $cache;
	}

	public function runSpriteGenerator(): void
	{
		/** @var SpriteGeneratorService $spriteGenerator */
		$spriteGenerator = $this->app()->service(
			SpriteGeneratorService::class,
			$this->fetchUsageByType('sprite')
		);
		$spriteGenerator->generate();
	}

	/**
	 * @return string[]
	 */
	public function getDefaultVariants(): array
	{
		$weights = [];

		/** @var int[] $styleIds */
		$styleIds = array_merge(
			[0],
			$this->db()->fetchAllColumn('SELECT style_id FROM xf_style')
		);
		foreach ($styleIds AS $styleId)
		{
			if ($styleId === 0)
			{
				$style = $this->app()->container('style.fallback');
			}
			else
			{
				$style = $this->app()->style($styleId);
			}

			$weight = $style->getProperty('fontAwesomeWeight', 400);
			$weights[$styleId] = $weight;
		}

		return array_map(
			function (int $weight): string
			{
				return $this->getIconVariantFromWeight($weight);
			},
			$weights
		);
	}

	public function getIconVariantFromWeight(int $weight): string
	{
		switch ($weight)
		{
			case 300: return 'light';
			case 400: return 'regular';
			case 900: return 'solid';
		}

		return 'regular';
	}

	/**
	 * @return list<{name: string, variant: string}>
	 */
	public function getIconsFromClasses(string $classes): array
	{
		$names = [];
		$variants = [];

		$classes = explode(' ', $classes);
		$classRegex = static::ICON_CLASS_REGEX;
		$blocklistRegex = static::ICON_CLASS_BLOCKLIST_REGEX;
		$validVariants = static::ICON_VARIANTS;
		foreach ($classes AS $class)
		{
			if (preg_match("/{$blocklistRegex}/i", $class))
			{
				continue;
			}

			if (array_key_exists($class, $validVariants))
			{
				$variants[] = $validVariants[$class];
				continue;
			}

			if (preg_match("/{$classRegex}/i", $class, $matches))
			{
				$names[] = $matches['name'];
				continue;
			}
		}

		$icons = [];

		if (!$names)
		{
			return [];
		}

		if (!$variants)
		{
			$variants = [null];
		}

		foreach ($variants AS $variant)
		{
			foreach ($names AS $name)
			{
				$icons[] = [
					'name' => $name,
					'variant' => $variant,
				];
			}
		}

		return $icons;
	}

	/**
	 * @return string[]
	 */
	public function getButtonIconMap(): array
	{
		return [
			'add' => 'fa-plus-square',
			'confirm' => 'fa-check',
			'write' => 'fa-edit',
			'import' => 'fa-upload',
			'export' => 'fa-download',
			'download' => 'fa-download',
			'redirect' => 'fa-external-link',
			'disable' => 'fa-power-off',
			'edit' => 'fa-edit',
			'save' => 'fa-save',
			'reply' => 'fa-reply',
			'quote' => 'fa-quote-left',
			'purchase' => 'fa-credit-card',
			'payment' => 'fa-credit-card',
			'convert' => 'fa-bolt',
			'search' => 'fa-search',
			'sort' => 'fa-sort',
			'upload' => 'fa-upload',
			'attach' => 'fa-paperclip',
			'login' => 'fa-lock',
			'rate' => 'fa-star',
			'config' => 'fa-cog',
			'refresh' => 'fa-sync-alt',
			'translate' => 'fa-globe',
			'vote' => 'fa-check-circle',
			'result' => 'fa-chart-bar',
			'history' => 'fa-history',
			'cancel' => 'fa-ban',
			'close' => 'fa-times',
			'preview' => 'fa-eye',
			'conversation' => 'fa-comments',
			'bolt' => 'fa-bolt',
			'list' => 'fa-list',
			'prev' => 'fa-chevron-left',
			'next' => 'fa-chevron-right',
			'markRead' => 'fa-check-square',
			'user' => 'fa-user',
			'userCircle' => 'fa-user-circle',

			'notificationsOn' => 'fa-bell',
			'notificationsOff' => 'fa-bell-slash',

			'show' => 'fa-eye',
			'hide' => 'fa-eye-slash',

			'merge' => 'fa-compress',
			'move' => 'fa-share',
			'copy' => 'fa-copy',
			'approve' => 'fa-shield',
			'unapprove' => 'fa-shield',
			'delete' => 'fa-trash-alt',
			'undelete' => 'fa-trash-alt',
			'stick' => 'fa-thumbtack',
			'unstick' => 'fa-thumbtack',
			'lock' => 'fa-lock',
			'unlock' => 'fa-unlock',
		];
	}

	/**
	 * @return string[]
	 */
	public function getExtraIcons(): array
	{
		$icons = [];

		$icons[] = 'fa-exclamation-triangle'; // user activity error icon

		// style variation icons
		$icons[] = 'fa-adjust';
		$icons[] = 'fa-sun';
		$icons[] = 'fa-moon';

		$this->app()->fire('icon_usage_analyzer_extra', [&$icons]);

		return $icons;
	}
}
