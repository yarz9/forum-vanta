<?php

namespace XF\Payment;

use GuzzleHttp\Exception\BadResponseException;
use Psr\Http\Message\ResponseInterface;
use XF\Entity\PaymentProfile;
use XF\Entity\PurchaseRequest;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Mvc\Reply\AbstractReply;
use XF\PrintableException;
use XF\Purchasable\Purchase;

use function in_array;

class PayPalRest extends AbstractProvider
{
	public function getTitle(): string
	{
		return 'PayPal';
	}

	public function renderConfig(PaymentProfile $profile): string
	{
		$data = [
			'profile' => $profile,
			'events' => $this->getActionableEvents(),
		];
		return \XF::app()->templater()->renderTemplate('admin:payment_profile_' . $this->providerId, $data);
	}

	public function getApiEndpoint(): string
	{
		if (\XF::config('enableLivePayments'))
		{
			return 'https://api-m.paypal.com/';
		}
		else
		{
			return 'https://api-m.sandbox.paypal.com/';
		}
	}

	public function verifyConfig(array &$options, &$errors = []): bool
	{
		$accessToken = $this->getAccessToken($options['client_id'], $options['secret_key'], $errors);

		if (!$accessToken)
		{
			return false;
		}

		$request = \XF::app()->request();
		$enableWebhook = $request->filter('enable_webhook', 'bool');

		if ($enableWebhook)
		{
			if (empty($options['webhook_id']))
			{
				$webhook = $this->createWebhook(
					$this->getWebhookUrl(),
					$this->getFormattedEvents(),
					$accessToken,
					$errors
				);

				if (!$webhook || !isset($webhook['id']) || $errors)
				{
					return false;
				}

				$options['webhook_id'] = $webhook['id'];
			}
		}
		else
		{
			if (!empty($options['webhook_id']))
			{
				$this->deleteWebhook($options['webhook_id'], $accessToken);
			}

			$options['webhook_id'] = '';
		}

		return true;
	}

	protected function getWebhookUrl(): string
	{
		return \XF::app()->options()->boardUrl . '/payment_callback.php?_xfProvider=' . $this->providerId;
	}

	protected function createWebhook(string $url, array $eventTypes, string $accessToken, &$errors = []): array
	{
		$params = [
			'url' => $url,
			'event_types' => $eventTypes,
		];
		return $this->makePayPalRequest('post', 'v1/notifications/webhooks', ['json' => $params], $accessToken, $errors);
	}

	protected function getWebhook(string $webhookId, string $accessToken, &$errors = []): array
	{
		return $this->makePayPalRequest('get', 'v1/notifications/webhooks/' . $webhookId, [], $accessToken, $errors);
	}

	protected function updateWebhookIfNeeded(string $webhookId, string $url, array $eventTypes, string $accessToken, &$errors = []): bool
	{
		$webhook = $this->getWebhook($webhookId, $accessToken, $errors);

		if (!$webhook || $errors)
		{
			\XF::logError('Error when fetching current webhook: ' . reset($errors));
			return true;
		}

		$urlMatch = $webhook['url'] === $url;

		$requiredEvents = array_column($eventTypes, 'name');
		sort($requiredEvents);

		$currentEvents = array_column($webhook['event_types'], 'name');
		sort($currentEvents);

		$eventsMatch = !array_diff_assoc($currentEvents, $requiredEvents);

		if (!$urlMatch || !$eventsMatch)
		{
			$this->updateWebhook($webhookId, $url, $eventTypes, $accessToken, $errors);
		}

		return true;
	}

	protected function updateWebhook(string $webhookId, string $url, array $eventTypes, string $accessToken, &$errors = []): array
	{
		$params = [
			[
				'op' => 'replace',
				'path' => '/url',
				'value' => $url,
			],
			[
				'op' => 'replace',
				'path' => '/event_types',
				'value' => $eventTypes,
			],
		];
		return $this->makePayPalRequest('patch', 'v1/notifications/webhooks/' . $webhookId, ['json' => $params], $accessToken, $errors);
	}

	protected function deleteWebhook(string $webhookId, string $accessToken, &$errors = []): bool
	{
		$this->makePayPalRequest('delete', 'v1/notifications/webhooks/' . $webhookId, [], $accessToken, $errors);
		return true;
	}

	protected function makePayPalRequest(string $method, string $endpoint, array $options = [], ?string $token = null, &$errors = []): array
	{
		$options = array_merge([
			'http_errors' => false,
			'headers' => [
				'Content-Type' => 'application/json',
			],
		], $options);

		if ($token !== null)
		{
			$options['headers']['Authorization'] = 'Bearer ' . $token;
		}

		$client = \XF::app()->http()->client();

		try
		{
			/** @var ResponseInterface $response */
			$response = $client->$method($this->getApiEndpoint() . $endpoint, $options);
			$responseBody = json_decode($response->getBody()->getContents(), true);

			if ($response->getStatusCode() > 204)
			{
				$errors[] = $responseBody['message'] ?? $responseBody['error_description'] ?? 'Unknown error';
			}

			return $responseBody ?? [];
		}
		catch (BadResponseException $e)
		{
			$errors[] = "PayPal API error ($endpoint): " . $e->getMessage();
		}

		return [];
	}

	protected $accessToken;

	protected function getAccessToken(string $clientId, string $secretKey, &$errors = []): ?string
	{
		if (!$this->accessToken)
		{
			$response = $this->makePayPalRequest('post', 'v1/oauth2/token', [
				'auth' => [$clientId, $secretKey],
				'form_params' => [
					'grant_type' => 'client_credentials',
				],
			], null, $errors);

			$this->accessToken = $response['access_token'] ?? null;
		}

		return $this->accessToken;
	}

	protected function assertAccessToken(string $clientId, string $secretKey): ?string
	{
		$accessToken = $this->getAccessToken($clientId, $secretKey, $errors);

		if (!$accessToken)
		{
			$errors = $errors ?? [];
			\XF::logError('Attempt to get PayPal access token failed: ' . reset($errors), true);
			throw new PrintableException(\XF::phrase('something_went_wrong_please_try_again'));
		}

		return $accessToken;
	}

	protected function getSubscriptionParams(string $planId, PurchaseRequest $purchaseRequest, Purchase $purchase): array
	{
		return [
			'plan_id' => $planId,
			'quantity' => '1',
			'custom_id' => $purchaseRequest->request_key,
			'application_context' => [
				'return_url' => $purchase->returnUrl,
				'cancel_url' => $purchase->cancelUrl,
			],
		];
	}

	protected function getPaymentParams(PurchaseRequest $purchaseRequest, Purchase $purchase): array
	{
		return [
			'intent' => 'CAPTURE',
			'purchase_units' => [
				[
					'amount' => [
						'currency_code' => $purchase->currency,
						'value' => $purchase->cost,
					],
					'description' => $purchase->title,
					'custom_id' => $purchaseRequest->request_key,
				],
			],
			'payment_source' => [
				'paypal' => [
					'experience_context' => [
						'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
						'user_action' => 'PAY_NOW',
						'return_url' => $purchase->returnUrl,
						'cancel_url' => $purchase->cancelUrl,
					],
				],
			],
		];
	}

	public function getPlanByProductId(Purchase $purchase, string $productId): array
	{
		$paymentProfile = $purchase->paymentProfile;
		$options = $paymentProfile->options;

		$accessToken = $this->assertAccessToken($options['client_id'], $options['secret_key']);
		$planDetails = $this->makePayPalRequest('get', 'v1/billing/plans', [
			'query' => [
				'product_id' => $productId,
				'page_size' => 1,
			],
		], $accessToken);

		$plan = reset($planDetails['plans']);
		if (isset($plan['id']))
		{
			if ($plan['name'] !== substr($purchase->purchasableTitle, 0, 127))
			{
				$patchData = [
					[
						'op' => 'replace',
						'path' => '/name',
						'value' => substr($purchase->purchasableTitle, 0, 127),
					],
				];

				$this->makePayPalRequest(
					'patch',
					"v1/billing/plans/{$plan['id']}",
					['json' => $patchData],
					$accessToken
				);

				$planDetails = $this->makePayPalRequest('get', 'v1/billing/plans', [
					'query' => [
						'product_id' => $productId,
						'page_size' => 1,
					],
				], $accessToken);
				$plan = reset($planDetails['plans']);
			}

			return $plan;
		}

		$params = [
			'product_id' => $productId,
			'name' => substr($purchase->purchasableTitle, 0, 127),
			'status' => 'ACTIVE',
			'billing_cycles' => [
				[
					'frequency' => [
						'interval_unit' => strtoupper($purchase->lengthUnit),
						'interval_count' => $purchase->lengthAmount,
					],
					'tenure_type' => 'REGULAR',
					'sequence' => 1,
					'total_cycles' => 0,
					'pricing_scheme' => [
						'fixed_price' => [
							'value' => $purchase->cost,
							'currency_code' => $purchase->currency,
						],
					],
				],
			],
			'payment_preferences' => [
				'payment_failure_threshold' => 2,
			],
		];

		$plan = $this->makePayPalRequest('post', 'v1/billing/plans', [
			'json' => $params,
		], $accessToken, $errors);

		if ($errors)
		{
			\XF::logError("Problem creating PayPal plan for product ($productId): " . reset($errors));
			throw new PrintableException(\XF::phrase('something_went_wrong_please_try_again'));
		}

		return $plan;
	}

	protected function getProductById(Purchase $purchase, string $productId): array
	{
		$paymentProfile = $purchase->paymentProfile;
		$options = $paymentProfile->options;

		$accessToken = $this->assertAccessToken($options['client_id'], $options['secret_key']);
		$productDetails = $this->makePayPalRequest('get', "v1/catalogs/products/$productId", [], $accessToken);

		if (isset($productDetails['id']))
		{
			if ($productDetails['name'] !== $purchase->purchasableTitle)
			{
				$patchData = [
					[
						'op' => 'replace',
						'path' => '/name',
						'value' => $purchase->purchasableTitle,
					],
				];

				$this->makePayPalRequest(
					'patch',
					"v1/catalogs/products/$productId",
					['json' => $patchData],
					$accessToken
				);

				$productDetails = $this->makePayPalRequest('get', "v1/catalogs/products/$productId", [], $accessToken);
			}

			return $productDetails;
		}

		$productDetails = $this->makePayPalRequest('post', 'v1/catalogs/products', [
			'json' => [
				'id' => $productId,
				'name' => $purchase->purchasableTitle,
				'type' => 'DIGITAL',
				'category' => 'MISCELLANEOUS_GENERAL_SERVICES',
			],
		], $accessToken, $errors);

		if ($errors)
		{
			\XF::logError("Problem creating PayPal product ($productId): " . reset($errors));
			throw new PrintableException(\XF::phrase('something_went_wrong_please_try_again'));
		}

		return $productDetails;
	}

	public function initiatePayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase): AbstractReply
	{
		$paymentProfile = $purchase->paymentProfile;
		$options = $paymentProfile->options;

		$accessToken = $this->assertAccessToken($options['client_id'], $options['secret_key']);

		if ($purchase->recurring)
		{
			$productId = $this->getPayPalProductId($purchase);

			// note: creates product if it doesn't exist so must be called first
			$product = $this->getProductById($purchase, $productId);
			$plan = $this->getPlanByProductId($purchase, $product['id']);

			$setupResponse = $this->makePayPalRequest('post', 'v1/billing/subscriptions', [
				'json' => $this->getSubscriptionParams($plan['id'], $purchaseRequest, $purchase),
			], $accessToken, $errors);
		}
		else
		{
			$setupResponse = $this->makePayPalRequest('post', 'v2/checkout/orders', [
				'json' => $this->getPaymentParams($purchaseRequest, $purchase),
			], $accessToken, $errors);
		}

		if (isset($setupResponse['id']))
		{
			$approvalUrl = $purchase->recurring
				? $setupResponse['links'][0]['href'] // approve subscription
				: $setupResponse['links'][1]['href']; // authorize payment

			return $controller->redirect($approvalUrl);
		}
		else
		{
			throw $controller->exception($controller->error('Failed to create PayPal order: ' . reset($errors)));
		}
	}

	protected function getPayPalProductId(Purchase $purchase): string
	{
		return substr($purchase->purchasableTypeId . '_' . md5(
			$purchase->currency . $purchase->cost . $purchase->lengthAmount . $purchase->lengthUnit
		), 0, 50);
	}

	public function setupCallback(Request $request): CallbackState
	{
		$state = new CallbackState();

		$inputRaw = $request->getInputRaw();
		$state->webhookBody = $inputRaw;

		$input = @json_decode($inputRaw, true);
		$filtered = \XF::app()->inputFilterer()->filterArray($input ?: [], [
			'id' => 'str',
			'event_type' => 'str',
			'resource' => 'array',
		]);

		$state->webhookEventId = $filtered['id'];
		$state->eventType = $filtered['event_type'];
		$state->resource = $filtered['resource'];
		$state->webhookEvent = $input;

		if (!$state->resource)
		{
			return $state;
		}

		// ridiculous - why is the same thing described in at least three different ways??
		$state->requestKey = $state->resource['purchase_units'][0]['custom_id']
			?? $state->resource['disputed_transactions'][0]['custom']
			?? $state->resource['custom']
			?? $state->resource['custom_id']
			?? null;

		$state->transactionId = $state->resource['disputed_transactions'][0]['seller_transaction_id']
			?? $state->resource['id'];

		$state->subscriberId = $state->resource['billing_agreement_id'] ?? null;

		$paymentProfile = $state->getPaymentProfile();

		if (!$paymentProfile)
		{
			return $state;
		}

		$state->webhookHeaders = [
			'auth_algo' => $request->getServer('HTTP_PAYPAL_AUTH_ALGO'),
			'cert_url' => $request->getServer('HTTP_PAYPAL_CERT_URL'),
			'transmission_id' => $request->getServer('HTTP_PAYPAL_TRANSMISSION_ID'),
			'transmission_sig' => $request->getServer('HTTP_PAYPAL_TRANSMISSION_SIG'),
			'transmission_time' => $request->getServer('HTTP_PAYPAL_TRANSMISSION_TIME'),
		];

		$state->webhookId = $paymentProfile->options['webhook_id'];

		return $state;
	}

	public function validateCallback(CallbackState $state): bool
	{
		$paymentProfile = $state->getPaymentProfile();
		if (!$paymentProfile)
		{
			return false;
		}

		$options = $paymentProfile->options;

		$webhookId = $state->webhookId ?? null;
		$accessToken = $this->getAccessToken($options['client_id'], $options['secret_key']);

		if ($webhookId && $accessToken)
		{
			// if the request reaches here, ensure webhook is updated with correct parameters
			// before going on to verify webhook signature; if webhook verification fails
			// on this attempt it should go through on the next try; however it is possible
			// that customer may need to toggle webhook off/on in some cases.
			$this->updateWebhookIfNeeded(
				$webhookId,
				$this->getWebhookUrl(),
				$this->getFormattedEvents(),
				$accessToken
			);
		}

		if ($this->isEventSkippable($state))
		{
			// skip any irrelevant webhooks
			$state->httpCode = 200;
			return false;
		}

		if (!$this->verifyWebhookSignature($state))
		{
			$state->logType = 'error';
			$state->logMessage = 'Webhook received from PayPal could not be verified as being valid. Try toggling "Enable webhook verification" off and on again in the payment profile if the issue persists.';
			$state->httpCode = 400;

			return false;
		}

		return true;
	}

	protected function isEventSkippable(CallbackState $state): bool
	{
		$eventType = $state->eventType;

		if (!in_array($eventType, $this->getActionableEvents()))
		{
			return true;
		}

		return false;
	}

	protected function getFormattedEvents(): array
	{
		$events = [];
		$actionableEvents = $this->getActionableEvents();

		foreach ($actionableEvents AS $event)
		{
			$events[] = ['name' => $event];
		}

		return $events;
	}

	protected function getActionableEvents(): array
	{
		return [
			'CHECKOUT.ORDER.APPROVED',
			'PAYMENT.CAPTURE.COMPLETED',
			'PAYMENT.CAPTURE.REFUNDED',
			'PAYMENT.SALE.COMPLETED',
			'PAYMENT.SALE.REFUNDED',
			'CUSTOMER.DISPUTE.CREATED',
			'CUSTOMER.DISPUTE.RESOLVED',
		];
	}

	protected $authAlgoMap = [
		'SHA256withRSA' => 'sha256WithRSAEncryption',
	];

	protected function verifyWebhookSignature(CallbackState $state): bool
	{
		if (!extension_loaded('openssl'))
		{
			\XF::logError('PayPal REST webhook signature verification requires the openssl extension. Signature verification will be skipped.');
			return true;
		}

		$webhookBody = $state->webhookBody;
		$webhookHeaders = $state->webhookHeaders;
		$transmissionId = $webhookHeaders['transmission_id'] ?? null;
		$transmissionSig = $webhookHeaders['transmission_sig'] ?? null;
		$transmissionTime = $webhookHeaders['transmission_time'] ?? null;
		$webhookId = $state->webhookId;

		$algo = $this->authAlgoMap[$webhookHeaders['auth_algo']] ?? null;

		if (!in_array($algo, openssl_get_md_methods(true)))
		{
			\XF::logError("PayPal REST webhook signature verification requires the $algo hashing algorithm. Signature verification will be skipped.");
			return true;
		}

		$cert = $this->fetchWebhookCert($state);
		if ($cert === null)
		{
			return false;
		}

		$x509 = openssl_x509_read($cert);
		if ($x509 === false)
		{
			return false;
		}

		$crc = crc32($webhookBody);
		$sigString = "$transmissionId|$transmissionTime|$webhookId|$crc";

		$publicKey = openssl_pkey_get_public($cert);
		if ($publicKey === false)
		{
			return false;
		}

		$decodedSignature = base64_decode($transmissionSig);
		$verifyStatus = openssl_verify($sigString, $decodedSignature, $publicKey, $algo);

		return $verifyStatus === 1;
	}

	protected function fetchWebhookCert(CallbackState $state): ?string
	{
		$reader = \XF::app()->http()->reader();
		$certUrl = $state->webhookHeaders['cert_url'];

		$response = $reader->get($certUrl);
		if (!$response)
		{
			return null;
		}

		return $response->getBody()->getContents();
	}

	public function validateTransaction(CallbackState $state): bool
	{
		if (!$state->requestKey)
		{
			$state->logType = 'info';
			$state->logMessage = 'No purchase request key. Unrelated payment, no action to take.';
			return false;
		}

		if (!$state->getPurchaseRequest())
		{
			$state->logType = 'info';
			$state->logMessage = 'Invalid request key. Unrelated payment, no action to take.';
			return false;
		}

		if (!$state->transactionId && !$state->subscriberId)
		{
			$state->logType = 'info';
			$state->logMessage = 'No transaction or subscriber ID. No action to take.';
			return false;
		}

		return true;
	}

	public function validatePurchaseRequest(CallbackState $state): bool
	{
		// validated in validateTransaction
		return true;
	}

	public function validateCost(CallbackState $state): bool
	{
		if ($state->eventType === 'CUSTOMER.DISPUTE.CREATED' || $state->eventType === 'CUSTOMER.DISPUTE.RESOLVED')
		{
			// cost validation does not matter at this point
			return true;
		}

		$purchaseRequest = $state->getPurchaseRequest();

		$currency = $purchaseRequest->cost_currency;
		$cost = $purchaseRequest->cost_amount;

		$amount = $state->resource['amount'] ?? $state->resource['purchase_units'][0]['amount'];

		$costValidated = (
			($amount['total'] ?? $amount['value']) === $cost
			&& ($amount['currency'] ?? $amount['currency_code']) === strtoupper($currency)
		);

		if (!$costValidated)
		{
			$state->logType = 'error';
			$state->logMessage = 'Invalid cost amount';
			return false;
		}

		return true;
	}

	public function getPaymentResult(CallbackState $state): void
	{
		switch ($state->eventType)
		{
			case 'CHECKOUT.ORDER.APPROVED':
				if ($state->resource['status'] === 'APPROVED')
				{
					$options = $state->getPaymentProfile()->options;
					$accessToken = $this->getAccessToken($options['client_id'], $options['secret_key']);

					if (!$accessToken)
					{
						return;
					}

					$orderId = $state->resource['id'];

					$captureResponse = $this->makePayPalRequest(
						'post',
						"v2/checkout/orders/$orderId/capture",
						[],
						$accessToken,
						$errors
					);

					if ($errors || $captureResponse['status'] !== 'COMPLETED')
					{
						$state->logType = 'error';
						$state->logMessage = reset($errors) ?: 'Order capture failed.';
						return;
					}
				}
				break;

			case 'PAYMENT.CAPTURE.COMPLETED':
			case 'PAYMENT.SALE.COMPLETED':
				$status = $state->resource['state'] ?? $state->resource['status'];
				if (strtoupper($status) === 'COMPLETED')
				{
					$state->paymentResult = CallbackState::PAYMENT_RECEIVED;
				}
				break;

			case 'PAYMENT.SALE.REFUNDED':
			case 'PAYMENT.CAPTURE.REFUNDED':
				$status = $state->resource['state'] ?? $state->resource['status'];
				if (strtoupper($status) === 'COMPLETED')
				{
					$state->paymentResult = CallbackState::PAYMENT_REVERSED;
				}
				break;

			case 'CUSTOMER.DISPUTE.CREATED':
				$state->paymentResult = CallbackState::PAYMENT_REVERSED;
				break;

			case 'CUSTOMER.DISPUTE.RESOLVED':
				$outcome = $state->resource['dispute_outcome']['outcome_code'] ?? null;

				switch ($outcome)
				{
					case 'RESOLVED_BUYER_FAVOUR':
					case 'RESOLVED_WITH_PAYOUT':
						// no action, already reversed
						break;

					case 'RESOLVED_SELLER_FAVOUR':
					case 'CANCELED_BY_BUYER':
						$state->paymentResult = CallbackState::PAYMENT_REINSTATED;
						break;
				}
				break;
		}
	}

	public function prepareLogData(CallbackState $state): void
	{
		$state->logDetails = $state->webhookEvent;
	}
}
