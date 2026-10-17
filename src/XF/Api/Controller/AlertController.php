<?php

namespace XF\Api\Controller;

use XF\Entity\UserAlert;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Repository\UserAlertRepository;

/**
 * @api-group Alerts
 */
class AlertController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		if (strtolower($action) == 'postmark')
		{
			$this->assertApiScope('alert:read');
		}
		else
		{
			$this->assertApiScopeByRequestMethod('alert');
		}

		$this->assertRegisteredUser();
	}

	/**
	 * @api-desc Gets information about the specified alert
	 *
	 * @api-out UserAlert $alert
	 */
	public function actionGet(ParameterBag $params)
	{
		$alert = $this->assertViewableAlert($params->alert_id);

		$result = $alert->toApiResult(Entity::VERBOSITY_VERBOSE);

		return $this->apiResult(['alert' => $result]);
	}

	/**
	 * @api-desc Marks the alert as viewed/read/unread. (Marking as unviewed is not supported.)
	 *
	 * @api-in bool $read If specified, marks the alert as read.
	 * @api-in bool $unread If specified, marks the alert as unread.
	 * @api-in bool $viewed If specified, marks all alerts as viewed.
	 *
	 * @api-out true $success
	 */
	public function actionPostMark(ParameterBag $params)
	{
		$alert = $this->assertViewableAlert($params->alert_id);

		if ($this->filter('viewed', 'bool'))
		{
			$this->getAlertRepo()->markUserAlertViewed($alert);
		}
		else if ($this->filter('read', 'bool'))
		{
			$this->getAlertRepo()->markUserAlertRead($alert);
		}
		else if ($this->filter('unread', 'bool'))
		{
			$this->getAlertRepo()->markUserAlertUnread($alert);
		}
		else
		{
			$this->assertRequiredApiInput(['viewed', 'read', 'unread']);
		}

		return $this->apiSuccess();
	}

	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return UserAlert
	 *
	 * @throws Exception
	 */
	protected function assertViewableAlert($id, $with = 'api')
	{
		/** @var UserAlert $alert */
		$alert = $this->assertRecordExists(UserAlert::class, $id, $with);

		if (\XF::isApiCheckingPermissions())
		{
			if ($alert->alerted_user_id != \XF::visitor()->user_id)
			{
				throw $this->exception($this->notFound());
			}

			if (!$alert->canView())
			{
				throw $this->exception($this->noPermission());
			}
		}

		return $alert;
	}

	/**
	 * @return UserAlertRepository
	 */
	protected function getAlertRepo()
	{
		return $this->repository(UserAlertRepository::class);
	}
}
