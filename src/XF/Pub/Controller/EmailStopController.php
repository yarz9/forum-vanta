<?php

namespace XF\Pub\Controller;

use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Service\User\EmailStopService;

class EmailStopController extends AbstractController
{
	public function assertIpNotBanned()
	{
	}

	public function assertViewingPermissions($action)
	{
	}

	public function assertPolicyAcceptance($action)
	{
	}

	/**
	 * @param string $action
	 */
	public function checkCsrfIfNeeded($action, ParameterBag $params)
	{
		if (strtolower($action) == 'unsubscribe')
		{
			return;
		}

		parent::checkCsrfIfNeeded($action, $params);
	}

	public function actionIndex(ParameterBag $params)
	{
		if ($this->isPost())
		{
			$confirmKey = $this->filter('c', 'str');
			$emailStopper = $this->assertValidatedStopService($params->user_id, $confirmKey);

			$stopAction = $this->filter('stop', 'str');
			$emailStopper->stop($stopAction);

			return $this->message(\XF::phrase('your_email_notification_selections_have_been_updated'));
		}
		else
		{
			return $this->displayConfirmation($params);
		}
	}

	public function actionUnsubscribe(ParameterBag $params)
	{
		$this->assertPostOnly();

		$confirmKey = $this->filter('c', 'str');
		$includeDm = $this->filter('include_dm', 'bool');

		$emailStopper = $this->assertValidatedStopService($params->user_id, $confirmKey);

		$action = $includeDm ? 'all' : 'all_except_dm';
		$emailStopper->stop($action);

		if ($this->options()->sendUnsubscribeConfirmation)
		{
			$emailStopper->sendConfirmation($action);
		}

		return $this->message(\XF::phrase('your_email_notification_selections_have_been_updated'));
	}

	protected function displayConfirmation(ParameterBag $params, array $actions = [])
	{
		$confirmKey = $this->filter('c', 'str');
		$emailStopper = $this->assertValidatedStopService($params->user_id, $confirmKey);

		$actionOptions = $emailStopper->getActionOptions($actions);
		$defaultAction = $actionOptions ? key($actionOptions) : null;

		$viewParams = [
			'user' => $emailStopper->getUser(),
			'confirmKey' => $emailStopper->getConfirmKey(),
			'actions' => $actionOptions,
			'defaultAction' => $defaultAction,
		];
		return $this->view('XF:EmailStop\Confirm', 'email_stop_confirm', $viewParams);
	}

	/**
	 * @param integer $userId
	 * @param string $confirmKey
	 *
	 * @return EmailStopService
	 * @throws Exception
	 */
	protected function assertValidatedStopService($userId, $confirmKey)
	{
		$user = $this->app->find(User::class, $userId);
		if (!$user)
		{
			throw $this->exception(
				$this->error(\XF::phrase('this_link_is_not_usable_by_you'), 403)
			);
		}

		if ($confirmKey !== $user->email_confirm_key)
		{
			throw $this->exception(
				$this->error(\XF::phrase('this_link_could_not_be_verified'), 403)
			);
		}

		/** @var EmailStopService $emailStopper */
		$emailStopper = $this->service(EmailStopService::class, $user);
		return $emailStopper;
	}

	public function actionAll(ParameterBag $params)
	{
		return $this->displayConfirmation($params);
	}

	public function actionMailingList(ParameterBag $params)
	{
		return $this->displayConfirmation($params, ['list']);
	}

	public function actionActivitySummary(ParameterBag $params)
	{
		return $this->displayConfirmation($params, ['activity_summary']);
	}

	public function actionConversation(ParameterBag $params)
	{
		return $this->displayConfirmation($params, ['conversations']);
	}

	public function actionContent(ParameterBag $params)
	{
		$type = $this->filter('t', 'str');
		$id = $this->filter('id', 'str');
		if ($id)
		{
			$type .= ":$id";
		}

		return $this->displayConfirmation($params, [$type]);
	}

	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('managing_account_details');
	}
}
