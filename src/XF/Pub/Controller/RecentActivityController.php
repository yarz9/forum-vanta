<?php

namespace XF\Pub\Controller;

class RecentActivityController extends AbstractController
{
	public function actionIndex()
	{
		return $this->redirectPermanently($this->buildLink('whats-new/latest-activity'));
	}
}
