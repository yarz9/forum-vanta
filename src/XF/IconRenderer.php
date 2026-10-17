<?php

namespace XF;

use XF\Repository\IconRepository;

class IconRenderer
{
	/**
	 * @var App
	 */
	protected $app;

	/**
	 * @var Style
	 */
	protected $style;

	/**
	 * @var IconRepository
	 */
	protected $iconRepo;

	public function __construct(App $app, ?Style $style = null)
	{
		$this->app = $app;
		$this->style = $style;
		$this->iconRepo = $app->repository(IconRepository::class);
	}

	public function setStyle(Style $style): void
	{
		$this->style = $style;
	}

	public function getStyle(): Style
	{
		return $this->style;
	}

	public function render(string $iconClasses, array $options = []): string
	{
		$options = $this->standardizeOptions($iconClasses, $options);

		$icon = $this->getIcon($iconClasses);
		if ($icon === null)
		{
			return '';
		}

		$options = $this->getIconOptionOverrides($icon, $options);

		if ($options['inline'])
		{
			return $this->renderInlineSvg($icon, $options);
		}

		return $this->renderSpriteSvg($icon, $options);
	}

	protected function standardizeOptions(
		string $iconClasses,
		array $options
	): array
	{
		$options = array_replace([
			'inline' => false,
			'class' => '',
			'title' => '',
			'attributes' => '',
		], $options);

		$options['class'] = $iconClasses . ' ' . $options['class'];

		return $options;
	}

	protected function getIcon(string $iconClasses): ?array
	{
		$icons = $this->iconRepo->getIconsFromClasses($iconClasses);
		if (!$icons)
		{
			return null;
		}

		$icon = reset($icons);

		if ($icon['variant'] === null)
		{
			$icon['variant'] = $this->getDefaultVariant();
		}

		return $icon;
	}

	protected function getDefaultVariant(): string
	{
		$weight = $this->style->getProperty('fontAwesomeWeight', 400);

		return $this->iconRepo->getIconVariantFromWeight($weight);
	}

	protected function getIconOptionOverrides(array $icon, array $options): array
	{
		$options['inline'] = !$this->isIconSprited(
			$icon['variant'],
			$icon['name']
		);

		if ($icon['variant'] === 'duotone')
		{
			// blink and webkit do not yet support styling in SVG sprites
			$options['inline'] = true;
		}

		if (!preg_match('/(^|\s)fa[bdlrs]($|\s)/', $options['class']))
		{
			$variantClass = 'fa' . substr($icon['variant'], 0, 1);
			$options['class'] = $variantClass . ' ' . $options['class'];
		}

		return $options;
	}

	protected function isIconSprited(string $variant, string $name): bool
	{
		if (
			$variant === $this->getDefaultVariant() &&
			$this->iconRepo->isIconSprited('default', $name)
		)
		{
			return true;
		}

		return $this->iconRepo->isIconSprited($variant, $name);
	}

	protected function renderInlineSvg(array $icon, array $options): string
	{
		$iconData = $this->iconRepo->getIconData(
			$icon['variant'],
			$icon['name']
		);

		$class = $options['class'];
		$title = $options['title'];
		$attributes = $options['attributes'];

		return '<i class="fa--xf ' . $class . '">'
			. '<svg xmlns="http://www.w3.org/2000/svg" '
			. 'viewBox="' . $iconData['viewBox'] . '" '
			. 'role="img" '
			. ($title ? '' : 'aria-hidden="true" ')
			. $attributes . '>'
			. ($title ? '<title>' . $title . '</title>' : '')
			. $iconData['icon']
			. '</svg>'
			. '</i>';
	}

	protected function renderSpriteSvg(array $icon, array $options): string
	{
		$url = $this->iconRepo->getSpriteIconUrl(
			$icon['variant'],
			$icon['name']
		);

		$class = $options['class'];
		$title = $options['title'];
		$attributes = $options['attributes'];

		return '<i class="fa--xf ' . $class . '">'
			. '<svg xmlns="http://www.w3.org/2000/svg" '
			. 'role="img" '
			. ($title ? '' : 'aria-hidden="true" ')
			. $attributes . '>'
			. ($title ? '<title>' . $title . '</title>' : '')
			. '<use href="' . htmlspecialchars($url) . '"></use>'
			. '</svg>'
			. '</i>';
	}
}
