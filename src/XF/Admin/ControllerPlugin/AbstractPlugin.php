<?php

namespace XF\Admin\ControllerPlugin;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\Controller;

abstract class AbstractPlugin extends \XF\ControllerPlugin\AbstractPlugin
{
	public function __construct(Controller $controller)
	{
		if (!($controller instanceof AbstractController))
		{
			throw new \LogicException("Admin controller plugins only work with admin controllers");
		}

		parent::__construct($controller);
	}
}
