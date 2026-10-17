<?php

namespace XF\Repository;

use XF\Entity\Webhook;
use XF\Finder\WebhookFinder;
use XF\Job\WebhookSend;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Webhook\Event\AbstractHandler;

use function is_array;

class WebhookRepository extends Repository
{
	public const CONTENT_TYPE_FORM = 'form_params';
	public const CONTENT_TYPE_JSON = 'json';

	public function findWebhooksForList(): Finder
	{
		return $this->finder(WebhookFinder::class)
			->order('webhook_id');
	}

	/**
	 * @return AbstractHandler[]
	 * @throws \Exception
	 */
	public function getWebhookHandlers(): array
	{
		$handlers = [];

		foreach (\XF::app()->getContentTypeField('webhook_handler_class') AS $contentType => $handlerClass)
		{
			if (class_exists($handlerClass))
			{
				$handlerClass = \XF::extendClass($handlerClass);
				$handlers[$contentType] = new $handlerClass($contentType);
			}
		}

		return $handlers;
	}

	public function getWebhookHandler(string $type, bool $throw = false): ?AbstractHandler
	{
		$handlerClass = \XF::app()->getContentTypeFieldValue($type, 'webhook_handler_class');
		if (!$handlerClass)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("No webhook handler for '$type'");
			}
			return null;
		}

		if (!class_exists($handlerClass))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("Webhook handler for '$type' does not exist: $handlerClass");
			}
			return null;
		}

		$handlerClass = \XF::extendClass($handlerClass);
		return new $handlerClass($type);
	}

	public function getWebhookCache(): array
	{
		$output = [];

		$webhooks = $this->finder(WebhookFinder::class)
			->where('active', 1);

		/** @var Webhook $webhook */
		foreach ($webhooks->fetch() AS $webhook)
		{
			foreach ($webhook->events AS $contentType => $events)
			{
				$output[$contentType][$webhook->webhook_id] = [
					'webhook_id' => $webhook->webhook_id,
				];
			}
		}

		return $output;
	}

	public function rebuildWebhookCache(): array
	{
		$cache = $this->getWebhookCache();

		\XF::registry()->set('webhookCache', $cache);

		return $cache;
	}

	public function queueWebhook(string $contentType, int $contentId, string $event, $entityOrData, $extraData = []): bool
	{
		$webhookCache = \XF::registry()->get('webhookCache');
		$applicableWebhooks = $webhookCache[$contentType] ?? [];

		if (empty($applicableWebhooks))
		{
			return false;
		}

		if ($entityOrData instanceof Entity)
		{
			$payload = $entityOrData->toWebhookResult()->render();
		}
		else if (is_array($entityOrData))
		{
			$payload = $entityOrData;
		}
		else
		{
			throw new \InvalidArgumentException("Webhook payload data must either be a valid entity or an array");
		}

		if (!empty($extraData))
		{
			$payload = array_merge($payload, $extraData);
		}

		foreach ($applicableWebhooks AS $webhookId => $cachedWebhook)
		{
			$this->app()->jobManager()->enqueue(WebhookSend::class, [
				'webhook_id' => $webhookId,
				'content_type' => $contentType,
				'content_id' => $contentId,
				'event' => $event,
				'payload' => $payload,
			]);
		}

		return true;
	}
}
