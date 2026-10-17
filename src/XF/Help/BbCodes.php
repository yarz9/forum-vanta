<?php

namespace XF\Help;

use XF\Mvc\Controller;
use XF\Mvc\Reply\View;
use XF\Repository\BbCodeRepository;

class BbCodes
{
	public static function renderBbCodes(Controller $controller, View &$response)
	{
		$finder = $controller->repository(BbCodeRepository::class)->findActiveBbCodes();
		$response->setParam('bbCodes', $finder->fetch());
	}
}
