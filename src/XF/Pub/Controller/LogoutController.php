<?php

namespace XF\Pub\Controller;

use XF\ControllerPlugin\LoginPlugin;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Pub\App;

class LogoutController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		App::$allowPageCache = false;
	}

	public function actionIndex()
	{
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		/** @var LoginPlugin $loginPlugin */
		$loginPlugin = $this->plugin(LoginPlugin::class);
		$loginPlugin->logoutVisitor();

		return $this->redirect($this->buildLink('index'));
	}

	public function updateSessionActivity($action, ParameterBag $params, AbstractReply &$reply)
	{
	}

	public function assertNotRejected($action)
	{
	}

	public function assertNotDisabled($action)
	{
	}

	public function assertViewingPermissions($action)
	{
	}

	public function assertCorrectVersion($action)
	{
	}

	public function assertBoardActive($action)
	{
	}

	public function assertTfaRequirement($action)
	{
	}

	public function assertPolicyAcceptance($action)
	{
	}

	public function assertNotSecurityLocked($action)
	{
	}
}
