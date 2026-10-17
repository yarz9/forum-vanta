<?php

namespace XF\AdminSearch;

use XF\Finder\PhraseMapFinder;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Router;

use function intval;

class PhraseHandler extends AbstractHandler
{
	protected $languageId = 0;

	public function getDisplayOrder()
	{
		return 60;
	}

	public function search($text, $limit, array $previousMatchIds = [])
	{
		// TODO: this is duplicated from the phrase controller, not ideal
		$languageId = $this->app->request()->getCookie('edit_language_id', null);
		if ($languageId === null)
		{
			$languageId = \XF::$developmentMode ? 0 : $this->app->options()->defaultLanguageId;
		}
		$languageId = intval($languageId);

		if ($languageId == 0 && !\XF::$developmentMode)
		{
			$languageId = $this->app->options()->defaultLanguageId;
		}
		$this->languageId = $languageId;

		$finder = $this->app->finder(PhraseMapFinder::class);

		$conditions = [
			[$finder->columnUtf8('title'), 'like', $finder->escapeLike($text, '%?%')],
		];
		if ($previousMatchIds)
		{
			$conditions[] = ['phrase_id', $previousMatchIds];
		}

		$finder
			->where('language_id', $languageId)
			->whereOr($conditions)
			->with('Phrase')
			->order($finder->columnUtf8('title'))
			->limit($limit);

		return $finder->fetch()->pluckNamed('Phrase', 'phrase_id');
	}

	public function getTemplateData(Entity $record)
	{
		/** @var Router $router */
		$router = $this->app->container('router.admin');

		return [
			'link' => $router->buildLink('phrases/edit', $record, ['language_id' => $this->languageId]),
			'title' => $record->title,
		];
	}

	public function isSearchable()
	{
		return \XF::visitor()->hasAdminPermission('language');
	}
}
