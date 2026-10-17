<?php

namespace XF\ControllerPlugin;

use XF\App;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Mvc\Entity\Manager;
use XF\Session\Session;

use function call_user_func_array;

/**
 * @property-read App $app
 * @property-read Manager $em
 * @property-read Request $request
 * @property-read Session $session
 *
 * @property string $responseType
 * @property ?string $sectionContext
 *
 * @mixin Controller
 */
abstract class AbstractPlugin
{
	/**
	 * @var Controller
	 */
	protected $controller;

	public function __construct(Controller $controller)
	{
		$this->controller = $controller;
	}

	public function __get($key)
	{
		switch ($key)
		{
			case 'app': return $this->controller->app();
			case 'em': return $this->controller->em();
			case 'request': return $this->controller->request();
			case 'session': return $this->controller->session();
			case 'responseType': return $this->controller->responseType();
			case 'sectionContext': return $this->controller->sectionContext();
		}

		return $this->controller->$key;
	}

	public function __set($key, $value)
	{
		switch ($key)
		{
			case 'responseType': $this->controller->setResponseType($value); return;
			case 'sectionContext': $this->controller->setSectionContext($value); return;
		}

		$this->controller->$key = $value;
	}

	public function __call($method, array $args)
	{
		return call_user_func_array([$this->controller, $method], $args);
	}
}
