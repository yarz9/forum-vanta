<?php

namespace XF\Pub\Controller;

class IndexController extends AbstractController
{
	public function actionIndex()
	{
		return $this->reroutePath($this->app->router()->getIndexRoute());
	}
}
