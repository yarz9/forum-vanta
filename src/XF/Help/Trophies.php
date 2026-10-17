<?php

namespace XF\Help;

use XF\Mvc\Controller;
use XF\Mvc\Reply\View;
use XF\Repository\TrophyRepository;

class Trophies
{
	public static function renderTrophies(Controller $controller, View &$response)
	{
		if (!$controller->options()->enableTrophies)
		{
			throw $controller->exception($controller->redirect($controller->buildLink('help')));
		}

		/** @var TrophyRepository $trophyRepo */
		$trophyRepo = $controller->repository(TrophyRepository::class);
		$trophies = $trophyRepo->findTrophiesForList();
		$response->setParam('trophies', $trophies->fetch());
	}
}
