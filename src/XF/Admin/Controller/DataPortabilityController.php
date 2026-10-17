<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\XmlPlugin;
use XF\Entity\User;
use XF\Finder\UserFinder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

class DataPortabilityController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('user');
	}

	public function actionIndex()
	{
		return $this->rerouteController(self::class, 'export');
	}

	public function actionExport()
	{
		if ($this->isPost())
		{
			$username = $this->filter('username', 'str');
			if ($username === '')
			{
				return $this->error(\XF::phrase('please_enter_valid_name'));
			}

			$userFinder = $this->finder(UserFinder::class)
				->where('username', $username);

			/** @var User $user */
			$user = $userFinder->fetchOne();

			if (!$user)
			{
				return $this->notFound(\XF::phrase('no_matching_users_were_found'));
			}

			return $this->plugin(XmlPlugin::class)->actionExport($userFinder, 'XF:User\Export');
		}
		else
		{
			$user = null;

			$userId = $this->filter('user_id', 'uint');
			if ($userId)
			{
				$user = $this->em()->find(User::class, $userId);
			}

			$viewParams = [
				'user' => $user,
			];
			return $this->view('XF:DataPortability\Export', 'data_portability_export', $viewParams);
		}
	}

	public function actionImport()
	{
		$reply = $this->plugin(XmlPlugin::class)->actionImport('data-portability', 'data_portability_user', 'XF:User\Import');

		if ($this->isPost())
		{
			return $this->redirect($this->buildLink('users/list', null, ['order' => 'register_date']));
		}
		else
		{
			if ($reply instanceof View)
			{
				$reply->setTemplateName('data_portability_import');
			}
			return $reply;
		}
	}
}
