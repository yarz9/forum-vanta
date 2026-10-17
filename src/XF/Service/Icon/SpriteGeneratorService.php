<?php

namespace XF\Service\Icon;

use XF\App;
use XF\Repository\IconRepository;
use XF\Repository\OptionRepository;
use XF\Service\AbstractService;
use XF\Util\File;

use function in_array;

class SpriteGeneratorService extends AbstractService
{
	/**
	 * @var string
	 */
	protected const SPRITE_FILE_FORMAT = 'local-data://icons/%s.svg';

	/**
	 * @var array<string, array<string, true>>
	 */
	protected $icons;

	/**
	 * @var IconRepository
	 */
	protected $iconRepo;

	/**
	 * @var string[]
	 */
	protected $defaultVariants;

	/**
	 * @var array<string, array<string, true>>
	 */
	public function __construct(App $app, array $icons)
	{
		parent::__construct($app);

		$this->icons = $icons;

		$this->iconRepo = $this->repository(IconRepository::class);
		$this->defaultVariants = array_unique(
			$this->iconRepo->getDefaultVariants()
		);
	}

	public function generate(): void
	{
		foreach (IconRepository::ICON_VARIANTS AS $variant)
		{
			$this->generateForVariant($variant);
		}

		$this->app->registry()->delete('iconSprite');
		$optionRepo = $this->repository(OptionRepository::class);
		$optionRepo->updateOption('iconSpriteLastUpdate', time());
	}

	protected function generateForVariant(string $variant): void
	{
		$prepend = $this->getSpritePrependForVariant($variant);

		$icons = [];
		foreach ($this->getIconsForVariant($variant) AS $name)
		{
			$data = $this->iconRepo->getIconData($variant, $name);
			if ($data === null)
			{
				continue;
			}

			$icons[$name] = $this->getIconFromTemplate(
				$name,
				$data['viewBox'],
				$data['icon']
			);
		}

		File::writeToAbstractedPath(
			sprintf(static::SPRITE_FILE_FORMAT, $variant),
			$this->getSpriteFromTemplate($prepend, $icons)
		);
	}

	/**
	 * @return list<string>
	 */
	protected function getSpritePrependForVariant(string $variant): array
	{
		$prepend = [];

		if ($variant === 'duotone')
		{
			$prepend[] = "\t<defs><style>.fa-secondary{opacity:.4}</style></defs>";
		}

		return $prepend;
	}

	/**
	 * @return list<string>
	 */
	protected function getIconsForVariant(string $variant): array
	{
		$icons = $this->icons[$variant] ?? [];

		if (
			!empty($this->icons['default']) &&
			in_array($variant, $this->defaultVariants)
		)
		{
			$icons = array_merge($icons, $this->icons['default']);
		}

		ksort($icons);
		return array_keys($icons);
	}

	/**
	 * @param list<string> $prepend
	 * @param array<string, string> $icons
	 */
	protected function getSpriteFromTemplate(
		array $prepend,
		array $icons
	): string
	{
		return strtr($this->getSpriteTemplate(), [
			'%PREPEND%' => implode("\n", $prepend),
			'%ICONS%' => implode("\n", $icons),
		]);
	}

	protected function getIconFromTemplate(
		string $name,
		string $viewBox,
		string $icon
	): string
	{
		return strtr($this->getIconTemplate(), [
			'%NAME%' => $name,
			'%VIEWBOX%' => $viewBox,
			'%ICON%' => $icon,
		]);
	}

	protected function getSpriteTemplate(): string
	{
		return <<<TEMPLATE
<?xml version="1.0" encoding="UTF-8"?>
<!--
Font Awesome Pro by @fontawesome - https://fontawesome.com
License - https://fontawesome.com/license (Commercial License)
-->
<svg xmlns="http://www.w3.org/2000/svg">
%PREPEND%
%ICONS%
</svg>
TEMPLATE;
	}

	protected function getIconTemplate(): string
	{
		return <<<TEMPLATE
	<symbol id="%NAME%" viewBox="%VIEWBOX%">
		%ICON%
	</symbol>
TEMPLATE;
	}
}
