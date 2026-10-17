<?php

namespace XF\ControllerPlugin;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;
use XF\PreRegAction\AbstractHandler;
use XF\Repository\PreRegActionRepository;

class PreRegActionPlugin extends AbstractPlugin
{
	public function actionPreRegAction($actionType, Entity $containerContent, array $actionData)
	{
		if (!\XF::visitor()->canTriggerPreRegAction())
		{
			return $this->noPermission();
		}

		$preRegActionRepo = $this->getPreRegActionRepo();

		/** @var AbstractHandler $handler */
		$handler = $preRegActionRepo->getActionHandler($actionType);
		$action = $handler->saveAction($containerContent, $actionData);

		$session = $this->controller->session();

		$existingActionKey = $session->preRegActionKey;
		if ($existingActionKey)
		{
			$preRegActionRepo->deleteActionByKey($existingActionKey);
		}

		$session->preRegActionKey = $action->guest_key;

		return $this->redirect($this->buildLink('register'));
	}

	/**
	 * @return Repository|PreRegActionRepository
	 */
	protected function getPreRegActionRepo()
	{
		return $this->repository(PreRegActionRepository::class);
	}
}
