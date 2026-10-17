<?php

namespace XF\Api\ControllerPlugin;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Controller;

/**
 * @mixin AbstractController
 */
abstract class AbstractPlugin extends \XF\ControllerPlugin\AbstractPlugin
{
	public function __construct(Controller $controller)
	{
		if (!($controller instanceof AbstractController))
		{
			throw new \LogicException("API controller plugins only work with API controllers");
		}

		parent::__construct($controller);
	}
}
