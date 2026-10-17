<?php

namespace XF\Job;

use GuzzleHttp\Exception\TransferException;
use XF\Entity\Webhook;
use XF\Repository\WebhookRepository;

class WebhookSend extends AbstractJob
{
	use Retryable;

	protected $defaultData = [
		'webhook_id' => null,
		'content_type' => null,
		'content_id' => null,
		'event' => null,
		'payload' => null,
	];

	public function run($maxRunTime): JobResult
	{
		if (!$this->data['webhook_id'])
		{
			throw new \InvalidArgumentException('Cannot send webhook without valid webhook_id');
		}

		if (!$this->data['content_type'] || !$this->data['content_id'])
		{
			throw new \InvalidArgumentException('Cannot send webhook without valid content');
		}

		if (!$this->data['event'])
		{
			throw new \InvalidArgumentException('Cannot send webhook without valid associated event string');
		}

		$webhook = \XF::em()->find(Webhook::class, $this->data['webhook_id']);
		if (!$webhook)
		{
			return $this->complete();
		}

		$webhookRepo = $this->getWebhookRepo();
		$handler = $webhookRepo->getWebhookHandler($this->data['content_type']);
		if (!$handler)
		{
			return $this->complete();
		}

		if (!$handler->canSendForContentTypeEvent($this->data['event'], $webhook))
		{
			return $this->complete();
		}

		$criteriaClass = $handler->getCriteriaClass();
		if ($criteriaClass)
		{
			$criteriaClass = $this->app->webhookCriteria(
				$criteriaClass,
				$webhook->criteria[$this->data['content_type']],
				$this->data['content_type']
			);

			$matchesCriteria = $criteriaClass->isMatched($this->data['payload']);
			if (!$matchesCriteria)
			{
				return $this->complete();
			}
		}

		$app = $this->app;
		$client = $app->http()->client();
		$options = $this->getRequestOptions($webhook);

		try
		{
			$client->post($webhook->url, $options);
		}
		catch (TransferException $e)
		{
			\XF::logException($e, false, "Webhook to {$webhook->url} failed: ");
			return $this->attemptLaterOrComplete();
		}

		return $this->complete();
	}

	protected function getRequestOptions(Webhook $webhook): array
	{
		$options = [];

		$options['headers'] = [
			'XF-Content-Type' => $this->data['content_type'],
			'XF-Webhook-Event' => $this->data['event'],
			'XF-Webhook-Id' => $webhook->webhook_id,
		];

		if ($webhook->secret)
		{
			$options['headers']['XF-Webhook-Secret'] = $webhook->secret;
		}

		$payload = $this->getPayload($webhook);

		if ($webhook->content_type === WebhookRepository::CONTENT_TYPE_FORM)
		{
			$options['form_params'] = $payload;
		}
		else
		{
			$options['json'] = $payload;
		}

		if ($webhook->ssl_verify)
		{
			$options['verify'] = $webhook->ssl_verify;
		}

		return $options;
	}

	protected function getPayload(Webhook $webhook): array
	{
		return [
			'content_type' => $this->data['content_type'],
			'event' => $this->data['event'],
			'content_id' => $this->data['content_id'],
			'data' => $this->data['payload'],
		];
	}

	protected function getWebhookRepo(): WebhookRepository
	{
		return $this->app->repository(WebhookRepository::class);
	}
}
