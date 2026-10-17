<?php

namespace XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class GotoPageController extends AbstractController
{
	public function actionPost(ParameterBag $params)
	{
		$params->offsetSet('post_id', $this->filter('id', 'uint'));
		return $this->rerouteController(PostController::class, 'index', $params);
	}

	public function actionConvMessage(ParameterBag $params)
	{
		$params->offsetSet('message_id', $this->filter('id', 'uint'));
		return $this->rerouteController(ConversationController::class, 'messages', $params);
	}

	public static function getActivityDetails(array $activities)
	{
	}
}
