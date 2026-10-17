<?php

namespace XF\ControllerPlugin;

use XF\Entity\Style;
use XF\Mvc\Reply\AbstractReply;
use XF\Repository\StyleRepository;

use function in_array, intval;

class StylePlugin extends AbstractPlugin
{
	public function getActiveStyleId()
	{
		$styleId = $this->request->getCookie('edit_style_id', null);
		if ($styleId === null)
		{
			$styleId = \XF::$developmentMode ? 0 : $this->options()->defaultStyleId;
		}
		$styleId = intval($styleId);

		if ($styleId == 0 && !\XF::$developmentMode)
		{
			$styleId = $this->options()->defaultStyleId;
		}

		return $styleId;
	}

	/**
	 * Gets the active editable style.
	 *
	 * @return Style
	 */
	public function getActiveEditStyle()
	{
		$styleId = $this->getActiveStyleId();

		if ($styleId == 0)
		{
			/** @var StyleRepository $styleRepo */
			$styleRepo = $this->repository(StyleRepository::class);
			$style = $styleRepo->getMasterStyle();
		}
		else
		{
			$style = $this->em()->find(Style::class, $styleId);
		}

		/** @var $style \XF\Entity\Style */
		if (!$style || !$style->canEdit())
		{
			$style = $this->em()->find(Style::class, $this->options()->defaultStyleId);
		}

		return $style;
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return Style
	 */
	public function assertStyleExists($id, $with = null, $phraseKey = null)
	{
		if ($id === 0 || $id === "0")
		{
			/** @var StyleRepository $styleRepo */
			$styleRepo = $this->repository(StyleRepository::class);
			return $styleRepo->getMasterStyle();
		}

		return $this->controller->assertRecordExists(Style::class, $id, $with, $phraseKey);
	}

	/**
	 * @param callable(string): void $saveCallback
	 */
	public function actionStyleVariation(
		\XF\Style $style,
		string $redirect,
		callable $saveCallback
	): AbstractReply
	{
		if ($this->request->exists('variation'))
		{
			$variation = $this->filter('variation', 'str');
			if (!in_array($variation, $style->getVariations()))
			{
				$variation = '';
			}
		}
		else if ($this->filter('reset', 'bool'))
		{
			$variation = '';
		}
		else
		{
			$variation = null;
		}

		if ($variation !== null)
		{
			$this->assertValidCsrfToken($this->filter('t', 'str') ?: null);

			$saveCallback($variation);

			$icon = $style->getVariationIcon($variation);

			$colorScheme = $variation
				? $style->getPropertyVariation('styleType', $variation)
				: '';

			$properties = [];
			foreach ($this->getStyleVariationProperties() AS $property => $callback)
			{
				$properties[$property] = $this->getStyleVariationProperty(
					$style,
					$property,
					$variation,
					$callback
				);
			}

			$reply = $this->redirect($redirect);
			$reply->setJsonParams([
				'variation' => $variation,
				'colorScheme' => $colorScheme,
				'icon' => $icon,
				'properties' => $properties,
			]);
			return $reply;
		}

		$viewParams = [
			'style' => $style,
			'redirect' => $redirect,
		];
		return $this->view(
			'XF:Style\StyleVariation',
			'style_variation_chooser',
			$viewParams
		);
	}

	protected function getStyleVariationProperties(): array
	{
		return [
			'metaThemeColor' => [$this, 'parseLessColor'],
		];
	}

	protected function parseLessColor(string $color): ?string
	{
		if (!$color)
		{
			return null;
		}

		return $this->app()->templater()->func(
			'parse_less_color',
			[$color]
		);
	}

	/**
	 * @param (callable(string): string|null)|null $callback
	 *
	 * @return array<string, string>|string
	 */
	protected function getStyleVariationProperty(
		\XF\Style $style,
		string $property,
		string $variation,
		?callable $callback = null
	)
	{
		if ($callback === null)
		{
			$callback = function (string $value): ?string
			{
				if (!$value)
				{
					return null;
				}

				return $value;
			};
		}

		if ($variation)
		{
			return $callback($style->getPropertyVariation(
				$property,
				$variation
			));
		}

		if ($style->hasAlternateStyleTypeVariation())
		{
			$defaultStyleType = $style->getDefaultStyleType();
			$alternateStyleType = $style->getAlternateStyleType();

			return [
				$defaultStyleType => $callback($style->getPropertyVariation(
					$property,
					\XF\Style::VARIATION_DEFAULT
				)),
				$alternateStyleType => $callback($style->getPropertyVariation(
					$property,
					$style->getAlternateStyleTypeVariation()
				)),
			];
		}

		return $callback($style->getPropertyVariation(
			$property,
			\XF\Style::VARIATION_DEFAULT
		));
	}

	public function getEquivalentStyleVariation(
		\XF\Style $currentStyle,
		\XF\Style $newStyle,
		string $variation
	): string
	{
		if ($variation === '')
		{
			return $variation;
		}

		if ($variation === $currentStyle->getStyleTypeVariation('light'))
		{
			return $newStyle->getStyleTypeVariation('light') ?? '';
		}

		if ($variation === $currentStyle->getStyleTypeVariation('dark'))
		{
			return $newStyle->getStyleTypeVariation('dark') ?? '';
		}

		return $variation;
	}
}
