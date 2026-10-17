<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\ErrorPlugin;
use XF\Mvc\Controller;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Reroute;
use XF\Repository\AdminLogRepository;

abstract class AbstractController extends Controller
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws Reply\Exception
	 */
	protected function preDispatchType($action, ParameterBag $params)
	{
		$this->assertAdmin();
		$this->assertCorrectVersion($action);
		$this->assertNotDisabled($action);
		$this->assertNotSecurityLocked($action);
		$this->preDispatchController($action, $params);
	}

	protected function preDispatchController($action, ParameterBag $params)
	{
	}

	protected function postDispatchType($action, ParameterBag $params, AbstractReply &$reply)
	{
		$this->postDispatchController($action, $params, $reply);

		if ($this->canAdminLogRequest($action, $params, $reply))
		{
			$this->adminLogRequest($action, $params, $reply);
		}
	}

	protected function postDispatchController($action, ParameterBag $params, AbstractReply &$reply)
	{
	}

	protected function canAdminLogRequest($action, ParameterBag $params, AbstractReply $reply)
	{
		if ($this->request->isGet() || $this->request->isHead())
		{
			return false;
		}

		if ($reply instanceof Reroute)
		{
			// next one will be responsible
			return false;
		}

		return true;
	}

	protected function adminLogRequest($action, ParameterBag $params, AbstractReply $reply)
	{
		$visitor = \XF::visitor();
		$request = $this->request;

		/** @var AdminLogRepository $adminLogRepo */
		$adminLogRepo = $this->repository(AdminLogRepository::class);
		$adminLogRepo->logAdminRequest(
			$visitor->user_id,
			$request->getRoutePath(),
			$request->getInputForLogs(),
			$request->getIp()
		);
	}

	/**
	 * @throws Reply\Exception
	 */
	public function assertAdmin()
	{
		if (!\XF::visitor()->is_admin)
		{
			if ($this->responseType == 'html')
			{
				throw $this->exception(
					$this->rerouteController(LoginController::class, 'form')
				);
			}
			else
			{
				throw $this->exception($this->noPermission(\XF::phrase('action_not_completed_because_no_longer_logged_in')));
			}
		}
	}

	/**
	 * @throws Reply\Exception
	 */
	public function assertSuperAdmin()
	{
		if (!\XF::visitor()->is_super_admin)
		{
			throw $this->exception($this->noPermission(\XF::phrase('you_must_be_super_admin_to_access_this_page')));
		}
	}

	/**
	 * @param $permission
	 * @throws Reply\Exception
	 */
	public function assertAdminPermission($permission)
	{
		if (!\XF::visitor()->hasAdminPermission($permission))
		{
			throw $this->exception($this->noPermission());
		}
	}

	/**
	 * @throws Reply\Exception
	 */
	public function assertDebugMode()
	{
		if (!\XF::$debugMode)
		{
			throw $this->exception($this->noPermission(
				\XF::phrase('page_only_available_debug_mode')
			));
		}
	}

	/**
	 * @throws Reply\Exception
	 */
	public function assertDevelopmentMode()
	{
		if (!\XF::$developmentMode)
		{
			throw $this->exception($this->noPermission(
				\XF::phrase('this_page_is_only_available_when_development_mode_is_enabled')
			));
		}
	}

	public function assertNotDisabled($action)
	{
		if (\XF::visitor()->user_state == 'disabled')
		{
			throw $this->exception(
				$this->plugin(ErrorPlugin::class)->actionDisabled()
			);
		}
	}

	/**
	 * @throws Reply\Exception
	 */
	public function assertNotSecurityLocked($action)
	{
		$visitor = \XF::visitor();
		if ($visitor->user_id && $visitor->security_lock)
		{
			throw $this->exception($this->noPermission(
				\XF::phrase('your_account_is_currently_security_locked')
			));
		}
	}

	protected function toggleProcess($identifier, $key = 'active')
	{
		$activeState = $this->filter($key, 'array-bool');
		$entities = $this->em()->findByIds($identifier, array_keys($activeState));

		foreach ($entities AS $id => $entity)
		{
			if ($entity->getExistingValue($key) != $activeState[$id])
			{
				$entity->{$key} = $activeState[$id];
				$entity->save();
			}
		}
	}
}
