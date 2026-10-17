<?php

namespace XF\AdminSearch;

use XF\Entity\BbCode;
use XF\Finder\BbCodeFinder;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Router;
use XF\Util\Str;

class BbCodeHandler extends AbstractHandler
{
	public function getDisplayOrder()
	{
		return 80;
	}

	public function search($text, $limit, array $previousMatchIds = [])
	{
		if (preg_match('/[^a-z\d\[\]]/i', $text))
		{
			return false;
		}

		$text = trim(Str::strtolower(str_replace(['[', ']'], '', $text)));
		$ids = [];

		foreach ($this->app->registry()['bbCodeCustom'] AS $tag => $foo)
		{
			if (Str::strpos(Str::strtolower($tag), $text) !== false)
			{
				$ids[] = $tag;
			}
		}

		$finder = $this->app->finder(BbCodeFinder::class)->whereIds($ids);
		return $finder->fetch();
	}

	public function getTemplateData(Entity $tag)
	{
		/** @var Router $router */
		$router = $this->app->container('router.admin');

		/** @var BbCode $tag */
		return [
			'link' => $router->buildLink('bb-codes/edit', $tag),
			'title' => $tag->title,
			'extra' => sprintf('[%s]', Str::strtoupper($tag->bb_code_id)),
		];
	}
}
