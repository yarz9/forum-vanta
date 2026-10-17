<?php

namespace XF\Behavior;

use XF\Mvc\Entity\Behavior;
use XF\Repository\WebhookRepository;

class Webhook extends Behavior
{
	protected function getDefaultOptions()
	{
		return [
			'enabled' => true,
		];
	}

	protected function verifyConfig()
	{
		if (!$this->contentType())
		{
			throw new \InvalidArgumentException('Structure must provide a contentType value');
		}
	}

	public function postSave()
	{
		if (!$this->options['enabled'])
		{
			return;
		}

		$action = $this->entity->isInsert() ? 'insert' : 'update';

		$this->getWebhookRepo()->queueWebhook(
			$this->contentType(),
			$this->entity->getEntityId(),
			$action,
			$this->entity
		);
	}

	public function postDelete()
	{
		if (!$this->options['enabled'])
		{
			return;
		}

		$this->getWebhookRepo()->queueWebhook(
			$this->contentType(),
			$this->entity->getEntityId(),
			'delete',
			$this->entity
		);
	}

	protected function getWebhookRepo(): WebhookRepository
	{
		return $this->app()->repository(WebhookRepository::class);
	}
}
