<?php

namespace XF\Job;

use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use XF\Entity\User;
use XF\Repository\UserPushRepository;

use function in_array;

class PushSend extends AbstractJob
{
	use Retryable;

	/**
	 * @var string
	 */
	protected const GCM_URL = 'https://android.googleapis.com/gcm/send';

	/**
	 * @var array<string, mixed>
	 */
	protected $defaultData = [
		'payload' => null,
		'receiverUserId' => 0,
		'subscriptions' => null,
	];

	/**
	 * @param float $maxRunTime
	 */
	public function run($maxRunTime): JobResult
	{
		$payload = $this->data['payload']
			? json_encode($this->data['payload'])
			: null;
		if (!$payload)
		{
			throw new \InvalidArgumentException(
				'Cannot send push notification without a valid payload'
			);
		}

		$receiver = $this->app->find(
			User::class,
			$this->data['receiverUserId']
		);
		if (!$receiver)
		{
			return $this->complete();
		}

		$userPushRepo = $this->getUserPushRepository();
		$userSubscriptions = $userPushRepo->getUserSubscriptions($receiver);

		if ($this->data['subscriptions'] === null)
		{
			$this->data['subscriptions'] = array_fill_keys(
				array_keys($userSubscriptions),
				true
			);
		}

		$userSubscriptions = array_intersect_key(
			$userSubscriptions,
			$this->data['subscriptions']
		);
		if (!$userSubscriptions)
		{
			return $this->complete();
		}

		$webPush = $this->getWebPushObject();

		foreach ($userSubscriptions AS $userSubscription)
		{
			if (strpos($userSubscription['endpoint'], static::GCM_URL) === 0)
			{
				// GCM is deprecated, skip it
				continue;
			}

			$authData = json_decode($userSubscription['data'], true);

			try
			{
				$webPush->setAutomaticPadding(
					$this->canUseAutomaticPaddingForEndpoint(
						$userSubscription['endpoint']
					)
				);

				$subscription = Subscription::create([
					'endpoint' => $userSubscription['endpoint'],
					'publicKey' => $authData['key'],
					'authToken' => $authData['token'],
					'contentEncoding' => $authData['encoding'],
				]);

				$webPush->queueNotification($subscription, $payload);
			}
			catch (\Exception $e)
			{
				// generally indicates that the payload is too big
				// which at ~3000 bytes shouldn't happen for a typical alert...
				if (\XF::$debugMode)
				{
					\XF::logException($e);
				}
			}
		}

		foreach ($webPush->flush() AS $report)
		{
			$this->handleReport($report);
		}

		if ($this->data['subscriptions'])
		{
			return $this->attemptLaterOrComplete();
		}

		return $this->complete();
	}

	protected function getWebPushObject(): WebPush
	{
		$options = $this->app->options();
		$auth = [
			'VAPID' => array_merge(
				['subject' => $options->boardUrl],
				$options->pushKeysVAPID
			),
		];

		$defaultOptions = [
			'TTL' => 86400, // expire if undelivered after 1 day
		];

		$timeout = 10;

		$clientOptions = $this->app->http()->getDefaultClientOptions();
		$config = $this->app->config();
		if ($config['http']['proxy'])
		{
			$clientOptions['proxy'] = $config['http']['proxy'];
		}

		$webPush = new WebPush(
			$auth,
			$defaultOptions,
			$timeout,
			$clientOptions
		);
		$webPush->setReuseVAPIDHeaders(true);

		return $webPush;
	}

	protected function canUseAutomaticPaddingForEndpoint(string $endpoint): bool
	{
		if (strpos($endpoint, 'mozilla') !== false)
		{
			// firefox, at least on android, has an issue with automatic padding
			// which is used to make encryption more secure at the cost of speed
			// see: https://github.com/web-push-libs/web-push-php/issues/108
			// TODO: check if mozilla fixes this or library works around it
			return false;
		}

		if (strpos($endpoint, '.ucweb.com') !== false)
		{
			// See https://xenforo.com/community/threads/158252/
			return false;
		}

		if (strpos($endpoint, '.vivoglobal.com') !== false)
		{
			// See https://xenforo.com/community/threads/222022/
			return false;
		}

		return true;
	}

	protected function handleReport(MessageSentReport $report): void
	{
		if (!$report->isSuccess())
		{
			$this->handleReportError($report);
			return;
		}

		$this->handleReportSuccess($report);
	}

	protected function handleReportSuccess(MessageSentReport $report): void
	{
		$endpointHash = $this->getReportEndpointHash($report);
		unset($this->data['subscriptions'][$endpointHash]);

		$this->app->db()->update(
			'xf_user_push_subscription',
			[
				'last_seen' => time(),
			],
			'endpoint_hash = ?',
			[$endpointHash]
		);
	}

	protected function handleReportError(MessageSentReport $report): void
	{
		$response = $report->getResponse();
		if (!$response && $this->willBeRetried())
		{
			// do nothing -- will be retried later
			return;
		}

		$code = $response ? $response->getStatusCode() : null;

		if (in_array($code, $this->getTemporaryErrorCodes(), true))
		{
			// do nothing -- will give up silently or be retried later
			return;
		}

		$endpointHash = $this->getReportEndpointHash($report);
		unset($this->data['subscriptions'][$endpointHash]);

		if (in_array($code, $this->getPermanentErrorCodes(), true))
		{
			$this->app->db()->delete(
				'xf_user_push_subscription',
				'endpoint_hash = ?',
				[$endpointHash]
			);
			return;
		}

		\XF::logError('Push notification failure: ' . $report->getReason());
	}

	/**
	 * @return list<int>
	 */
	protected function getTemporaryErrorCodes(): array
	{
		return [
			406, // not acceptable (likely due to rate limiting)
			408, // request timeout
			429, // too many requests (likely due to rate limiting)
			500, // internal server error
			502, // bad gateway
			503, // service unavailable
			504, // gateway timeout
		];
	}

	/**
	 * @return list<int>
	 */
	protected function getPermanentErrorCodes(): array
	{
		return [
			401, // unauthorized (likely due to VAPID keys changing)
			403, // forbidden (likely due to VAPID keys changing)
			404, // not found
			410, // gone
		];
	}

	protected function getReportEndpointHash(MessageSentReport $report): string
	{
		$endpoint = (string) $report->getRequest()->getUri();

		return $this->getUserPushRepository()->getEndpointHash($endpoint);
	}

	protected function calculateNextAttemptDate(int $previousAttempts): ?int
	{
		switch ($previousAttempts)
		{
			case 0: $delay = 5 * 60; break; // 5 minutes
			case 1: $delay = 1 * 60 * 60; break; // 1 hour
			case 2: $delay = 2 * 60 * 60; break; // 2 hours
			case 3: $delay = 3 * 60 * 60; break; // 3 hours
			case 4: $delay = 4 * 60 * 60; break; // 4 hours
			default: return null; // give up
		}

		return time() + $delay;
	}

	protected function getUserPushRepository(): UserPushRepository
	{
		return $this->app->repository(UserPushRepository::class);
	}
}
