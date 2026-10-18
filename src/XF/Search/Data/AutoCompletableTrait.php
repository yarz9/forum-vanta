<?php

namespace XF\Search\Data;

use XF\Entity\User;

trait AutoCompletableTrait
{
	/**
	 * @param string      $text
	 * @param string      $url
	 * @param User|null   $user
	 * @param string|null $desc
	 *
	 * @return array{
	 *     text: string,
	 *     url: string,
	 *     desc?: string,
	 *     iconHtml?: string,
	 * }|null
	 */
	protected function getSimpleAutoCompleteResult(
		string $text,
		string $url,
		?string $desc = null,
		?User $user = null
	): ?array
	{
		if ($desc)
		{
			$stringFormatter = \XF::app()->stringFormatter();
			$desc = $stringFormatter->snippetString(
				$desc,
				150,
				['stripQuote' => true]
			);
		}

		$iconHtml = null;
		if ($user)
		{
			$templater = \XF::app()->templater();
			$iconHtml = $templater->func('avatar', [
				$user,
				'xxs',
				false,
				['href' => ''],
			]);
		}

		return [
			'text' => $text,
			'url' => $url,
			'iconHtml' => $iconHtml,
			'desc' => $desc,
		];
	}
}
