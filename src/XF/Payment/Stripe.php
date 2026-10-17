<?php

namespace XF\Payment;

use Stripe\Account;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Exception\ExceptionInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Invoice;
use Stripe\PaymentMethod;
use Stripe\Plan;
use Stripe\Product;
use Stripe\SetupIntent;
use Stripe\Subscription;
use Stripe\Webhook;
use XF\Entity\PaymentProfile;
use XF\Entity\PurchaseRequest;
use XF\Finder\PaymentProfileFinder;
use XF\Finder\PaymentProviderLogFinder;
use XF\Finder\PurchaseRequestFinder;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Mvc\Reply\AbstractReply;
use XF\Purchasable\Purchase;
use XF\Util\Str;
use XF\Validator\Email;

use function array_key_exists, in_array, intval, is_array, is_string, strlen, strval;

class Stripe extends AbstractProvider
{
	// Force a specific Stripe version so we can better control
	// when we are ready for potential code breaking changes.

	protected $stripeVersion = '2023-10-16';

	protected $customerCache = [];

	public function getTitle()
	{
		return 'Stripe';
	}

	public function renderConfig(PaymentProfile $profile)
	{
		$data = [
			'profile' => $profile,
			'events' => $this->getActionableEvents(),
		];
		return \XF::app()->templater()->renderTemplate('admin:payment_profile_' . $this->providerId, $data);
	}

	public function verifyConfig(array &$options, &$errors = [])
	{
		if (\XF::config('enableLivePayments'))
		{
			$keyName = 'live_secret_key';
		}
		else
		{
			$keyName = 'test_secret_key';
		}

		$secretKey = $options[$keyName];

		if ($secretKey)
		{
			try
			{
				\Stripe\Stripe::setAppInfo(
					'XenForo',
					\XF::$version,
					'https://xenforo.com/contact'
				);
				\Stripe\Stripe::setApiKey($secretKey);
				\Stripe\Stripe::setApiVersion($this->stripeVersion);

				$stripeAccount = Account::retrieve();
			}
			catch (\Exception $e)
			{
				$errors[] = \XF::phrase('cannot_verify_that_your_key_x_is_valid', ['keyName' => $keyName]);
				return false;
			}

			$options['stripe_country'] = $stripeAccount->country;
		}

		$statementDescriptor = $options['statement_descriptor'];
		if (strlen($statementDescriptor))
		{
			$statementDescriptor = $this->sanitizeStatementDescriptor($statementDescriptor);

			if (strlen($statementDescriptor) < 5 || strlen($statementDescriptor) > 22)
			{
				$errors[] = \XF::phrase('valid_statement_descriptor_must_be_correct_length_and_format');
				return false;
			}

			$options['statement_descriptor'] = $statementDescriptor;
		}
		else
		{
			// Validate that we can get a statement descriptor from the title
			$defaultStatementDescription = $this->getStatementDescriptor();

			if (strlen($defaultStatementDescription) < 5 || strlen($defaultStatementDescription) > 22)
			{
				$errors[] = \XF::phrase('stripe_custom_statement_descriptor_required');
				return false;
			}
		}

		return true;
	}

	protected function getStripeKey(PaymentProfile $paymentProfile, $type = 'secret')
	{
		if (\XF::config('enableLivePayments'))
		{
			$key = $paymentProfile->options['live_' . $type . '_key'];
		}
		else
		{
			$key = $paymentProfile->options['test_' . $type . '_key'];
		}

		return $key;
	}

	protected function setupStripe(PaymentProfile $paymentProfile)
	{
		\Stripe\Stripe::setAppInfo(
			'XenForo',
			\XF::$version,
			'https://xenforo.com/contact'
		);
		\Stripe\Stripe::setApiKey($this->getStripeKey($paymentProfile));
		\Stripe\Stripe::setApiVersion($this->stripeVersion);
	}

	protected function getChargeMetadata(PurchaseRequest $purchaseRequest)
	{
		return [
			'request_key' => $purchaseRequest->request_key,
		] + $this->getCustomerMetadata($purchaseRequest);
	}

	protected function getCustomerMetadata(PurchaseRequest $purchaseRequest)
	{
		/** @var Email $validator */
		$validator = \XF::app()->validator(Email::class);

		$email = null;

		if ($purchaseRequest->User)
		{
			$email = $validator->coerceValue($purchaseRequest->User->email);
			if (!$email || !$validator->isValid($email))
			{
				$email = null;
			}
		}

		if (!$email)
		{
			$email = 'invalid@example.com';
		}

		return [
			'user_id' => $purchaseRequest->user_id,
			'username' => $purchaseRequest->User
				? $purchaseRequest->User->username
				: \XF::phrase('guest'),
			'email' => $email,
		];
	}

	protected function getTransactionMetadata(Purchase $purchase)
	{
		return [
			'purchasable_type_id' => $purchase->purchasableTypeId,
			'purchasable_id' => $purchase->purchasableId,
			'currency' => $purchase->currency,
			'cost' => $purchase->cost,
			'length_amount' => $purchase->lengthAmount,
			'length_unit' => $purchase->lengthUnit,
		];
	}

	protected function getCustomStatementDescriptor(PaymentProfile $paymentProfile): string
	{
		if (
			array_key_exists('statement_descriptor', $paymentProfile->options)
			&& strlen($paymentProfile->options['statement_descriptor']) > 0
		)
		{
			// note: already sanitized on save
			$statementDescriptor = $paymentProfile->options['statement_descriptor'];
		}
		else
		{
			$statementDescriptor = $this->getStatementDescriptor();
		}

		return $statementDescriptor;
	}

	protected function getStatementDescriptor()
	{
		return $this->sanitizeStatementDescriptor(\XF::app()->options()->boardTitle);
	}

	protected function getStatementDescriptorSuffix(Purchase $purchase): string
	{
		return $this->sanitizeStatementDescriptor($purchase->title, 10);
	}

	protected function sanitizeStatementDescriptor(string $descriptor, int $length = 22): string
	{
		$cleanDescriptor = str_replace(['\\', "'", '"', '*', '<', '>'], '', $descriptor);
		$cleanDescriptor = Str::transliterate($cleanDescriptor);

		$originalString = $cleanDescriptor;

		// Attempt to transliterate remaining UTF-8 characters to their ASCII equivalents
		$cleanDescriptor = @iconv('UTF-8', 'ASCII//TRANSLIT', $cleanDescriptor);
		if (!$cleanDescriptor)
		{
			// iconv failed so forget about it
			$cleanDescriptor = $originalString;
		}

		$cleanDescriptor = preg_replace('/[^a-zA-Z0-9_ -.]/', '', $cleanDescriptor);

		return \XF::app()->stringFormatter()->wholeWordTrim(
			$cleanDescriptor,
			$length,
			0,
			''
		);
	}

	protected function getPaymentParams(PurchaseRequest $purchaseRequest, Purchase $purchase)
	{
		$paymentProfile = $purchase->paymentProfile;
		$publishableKey = $this->getStripeKey($paymentProfile, 'publishable');

		return [
			'purchaseRequest' => $purchaseRequest,
			'paymentProfile' => $paymentProfile,
			'purchaser' => $purchase->purchaser,
			'purchase' => $purchase,
			'purchasableTypeId' => $purchase->purchasableTypeId,
			'purchasableId' => $purchase->purchasableId,
			'publishableKey' => $publishableKey,
			'cost' => $this->getStripeFormattedCost(
				$purchaseRequest,
				$purchase
			),
		];
	}

	protected function getCheckoutSessionParams(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase): array
	{
		$user = $purchaseRequest->User;
		$params = $this->getInitiatePaymentParams($controller, $purchaseRequest, $purchase);

		if ($purchase->recurring)
		{
			$lineItems = [
				[
					'price' => $params['plan']->id,
				],
			];
		}
		else
		{
			$lineItems = [
				[
					'price_data' => [
						'currency' => $purchase->currency,
						'product_data' => [
							'name' => $purchase->purchasableTitle,
						],
						'unit_amount' => $this->getStripeFormattedCost($purchaseRequest, $purchase),
					],
				],
			];
		}

		$lineItems[0]['quantity'] = 1;

		return [
			'customer' => $params['customer']->id ?? null,
			'customer_email' => isset($params['customer']) ? null : $user->email,
			'line_items' => $lineItems,
			'metadata' => $this->getChargeMetadata($purchaseRequest),
			'mode' => $purchase->recurring ? 'subscription' : 'payment',
			'payment_intent_data' => $purchase->recurring ? null : [
				'metadata' => $this->getChargeMetadata($purchaseRequest),
			],
			'subscription_data' => $purchase->recurring ? [
				'metadata' => $this->getChargeMetadata($purchaseRequest),
			] : null,
			'success_url' => $purchase->returnUrl,
			'cancel_url' => $purchase->cancelUrl,
		];
	}

	public function initiatePayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase)
	{
		$paymentProfile = $purchase->paymentProfile;
		$this->setupStripe($paymentProfile);

		$params = $this->getCheckoutSessionParams($controller, $purchaseRequest, $purchase);

		try
		{
			$checkoutSession = Session::create($params);
		}
		/** @noinspection PhpMultipleClassDeclarationsInspection */
		catch (ExceptionInterface $e)
		{
			\XF::logException($e);
			return $controller->error(\XF::phrase('something_went_wrong_please_try_again'));
		}

		return $controller->redirect($checkoutSession->url);
	}

	public function getInitiatePaymentParams(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase)
	{
		$params = $this->getPaymentParams($purchaseRequest, $purchase);

		$params['purchaseRequestId'] = $purchaseRequest->purchase_request_id;

		if ($purchase->recurring)
		{
			$product = $this->createStripeProduct($purchase->paymentProfile, $purchase, $error);
			if (!$product)
			{
				$errorPhrase = \XF::phrase('error_occurred_while_creating_stripe_product:');
				throw $controller->exception($controller->error("$errorPhrase $error"));
			}
			$params['product'] = $product;

			$plan = $this->createStripePlan($product, $purchase->paymentProfile, $purchase, $error);
			if (!$plan)
			{
				$errorPhrase = \XF::phrase('error_occurred_while_creating_stripe_plan:');
				throw $controller->exception($controller->error("$errorPhrase $error"));
			}
			$params['plan'] = $plan;
		}

		$customer = $this->getStripeCustomer($purchaseRequest, $purchase->paymentProfile, $purchase, $error);
		if (!$customer)
		{
			$errorPhrase = \XF::phrase('error_occurred_while_creating_stripe_customer:');
			throw $controller->exception($controller->error("$errorPhrase $error"));
		}
		$params['customer'] = $customer;

		return $params;
	}

	protected function getStripeProductAndPlanId(Purchase $purchase)
	{
		return $purchase->purchasableTypeId . '_' . md5(
			$purchase->currency . $purchase->cost . $purchase->lengthAmount . $purchase->lengthUnit
		);
	}

	protected function getStripeCustomer(PurchaseRequest $purchaseRequest, PaymentProfile $paymentProfile, Purchase $purchase, &$error = null)
	{
		$this->setupStripe($paymentProfile);
		$metadata = $this->getCustomerMetadata($purchaseRequest);

		$cacheId = "$paymentProfile->payment_profile_id-$metadata[email]";
		if (isset($this->customerCache[$cacheId]))
		{
			return $this->customerCache[$cacheId];
		}

		$customer = null;

		try
		{
			$customers = Customer::all([
				'limit' => 1,
				'email' => $metadata['email'],
			]);

			/** @var Customer $customer */
			$customer = reset($customers->data);
		}
		catch (ExceptionInterface $e)
		{
		}

		if (!$customer)
		{
			try
			{
				/** @var Customer $customer */
				$customer = Customer::create([
					'description' => $metadata['username'],
					'email' => $metadata['email'],
					'metadata' => $this->getCustomerMetadata($purchaseRequest),
				]);
			}
			catch (ExceptionInterface $e)
			{
				// failed to create
				$error = $e->getMessage();
				return false;
			}
		}

		$this->customerCache[$cacheId] = $customer;

		return $customer;
	}

	protected function getStripeFormattedCost(PurchaseRequest $purchaseRequest, Purchase $purchase)
	{
		return $this->prepareCost($purchase->cost, $purchase->currency);
	}

	protected function createStripeProduct(PaymentProfile $paymentProfile, Purchase $purchase, &$error = null)
	{
		$this->setupStripe($paymentProfile);
		$productId = $this->getStripeProductAndPlanId($purchase);

		try
		{
			/** @var Product $product */
			$product = Product::retrieve($productId);
		}
		catch (ExceptionInterface $e)
		{
			// likely means no existing product, so lets create it
			try
			{
				/** @var Product $product */
				$product = Product::create([
					'id' => $productId,
					'name' => $purchase->purchasableTitle,
					'type' => 'service',
					'metadata' => $this->getTransactionMetadata($purchase),
					'statement_descriptor' => $this->getCustomStatementDescriptor($paymentProfile),
				]);
			}
			catch (ExceptionInterface $e)
			{
				// failed to retrieve, failed to create
				$error = $e->getMessage();
				return false;
			}
		}

		return $product;
	}

	protected function createStripePlan(Product $product, PaymentProfile $paymentProfile, Purchase $purchase, &$error = null)
	{
		$this->setupStripe($paymentProfile);
		$planId = $this->getStripeProductAndPlanId($purchase);
		$expectedAmount = $this->prepareCost($purchase->cost, $purchase->currency);

		$findOrCreatePlan = function ($planId, $expectedAmount, &$error) use ($purchase, $product)
		{
			try
			{
				$plan = Plan::retrieve($planId);
			}
			catch (ExceptionInterface $e)
			{
				// likely means no existing plan, so lets create it
				try
				{
					$plan = Plan::create([
						'id' => $planId,
						'currency' => $purchase->currency,
						'amount' => $expectedAmount,
						'billing_scheme' => 'per_unit',
						'interval' => $purchase->lengthUnit,
						'interval_count' => $purchase->lengthAmount,
						'product' => $product->id,
						'metadata' => $this->getTransactionMetadata($purchase),
					]);
				}
				catch (ExceptionInterface $e)
				{
					// failed to retrieve, failed to create
					$error = $e->getMessage();
					return false;
				}
			}

			/** @var Plan $plan */
			return $plan;
		};

		$plan = $findOrCreatePlan($planId, $expectedAmount, $error);
		if (!$plan)
		{
			// got an error when creating the plan so don't try anything
			return false;
		}

		if ($plan->amount !== $expectedAmount)
		{
			// base plan is likely triggering the off-by-one cost bug so we need to make a new one
			$planId .= '_' . $expectedAmount;

			$plan = $findOrCreatePlan($planId, $expectedAmount, $error);
			if (!$plan)
			{
				return false;
			}
		}

		return $plan;
	}

	public function renderCancellationTemplate(PurchaseRequest $purchaseRequest)
	{
		return $this->renderCancellationDefault($purchaseRequest);
	}

	protected function getProviderMetadata(PurchaseRequest $purchaseRequest): array
	{
		$providerMetadata = json_decode($purchaseRequest->provider_metadata, true);
		if (is_array($providerMetadata))
		{
			return $providerMetadata;
		}
		return [];
	}

	protected function getSubscriberIdFromPurchaseRequest(PurchaseRequest $purchaseRequest): ?string
	{
		$subscriberId = null;
		$providerMetadata = $this->getProviderMetadata($purchaseRequest);

		if (!isset($providerMetadata['subscription']))
		{
			$logFinder = \XF::finder(PaymentProviderLogFinder::class)
				->where('purchase_request_key', $purchaseRequest->request_key)
				->where('provider_id', $this->providerId)
				->order('log_date', 'desc');

			$logs = $logFinder->fetch();

			foreach ($logs AS $log)
			{
				if ($log->subscriber_id && strpos($log->subscriber_id, 'sub_') === 0)
				{
					$subscriberId = $log->subscriber_id;
					break;
				}
			}
		}
		else
		{
			$subscriberId = $providerMetadata['subscription'];
		}

		return $subscriberId;
	}

	public function processCancellation(Controller $controller, PurchaseRequest $purchaseRequest, PaymentProfile $paymentProfile)
	{
		$subscriberId = $this->getSubscriberIdFromPurchaseRequest($purchaseRequest);

		if (!$subscriberId)
		{
			return $controller->error(\XF::phrase('could_not_find_subscriber_id_for_this_purchase_request'));
		}

		$this->setupStripe($paymentProfile);

		try
		{
			/** @var Subscription $subscription */
			$subscription = Subscription::retrieve($subscriberId);
			$cancelledSubscription = $subscription->cancel();

			if ($cancelledSubscription->status != 'canceled')
			{
				throw $controller->exception($controller->error(
					\XF::phrase('this_subscription_cannot_be_cancelled_maybe_already_cancelled')
				));
			}
		}
		catch (ExceptionInterface $e)
		{
			throw $controller->exception($controller->error(
				\XF::phrase('this_subscription_cannot_be_cancelled_maybe_already_cancelled')
			));
		}

		$purchasableHandler = $purchaseRequest->Purchasable->handler;
		$purchasableHandler->processCancellation($purchaseRequest);

		return $controller->redirect(
			$controller->getDynamicRedirect(),
			\XF::phrase('stripe_subscription_cancelled_successfully')
		);
	}

	public function renderChangePaymentTemplate(PurchaseRequest $purchaseRequest): string
	{
		return $this->renderChangePaymentDefault($purchaseRequest);
	}

	public function initiateChangePayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase): AbstractReply
	{
		$paymentProfile = $purchase->paymentProfile;
		$this->setupStripe($paymentProfile);

		$params = $this->getInitiatePaymentParams($controller, $purchaseRequest, $purchase);

		if (!$purchase->recurring)
		{
			return $controller->error(\XF::phrase('changing_payment_details_is_only_possible_on_recurring_payments'));
		}

		if (!isset($params['customer']))
		{
			return $controller->error(\XF::phrase('something_went_wrong_please_try_again'));
		}

		try
		{
			$checkoutSession = Session::create([
				'customer' => $params['customer']->id,
				'mode' => 'setup',
				'currency' => $purchase->currency,
				'metadata' => $this->getChargeMetadata($purchaseRequest),
				'success_url' => $purchase->updateUrl,
				'cancel_url' => $purchase->cancelUrl,
			]);
		}
		/** @noinspection PhpMultipleClassDeclarationsInspection */
		catch (ExceptionInterface $e)
		{
			\XF::logException($e);
			return $controller->error(\XF::phrase('something_went_wrong_please_try_again'));
		}

		return $controller->redirect($checkoutSession->url);
	}

	public function setupCallback(Request $request)
	{
		$state = new CallbackState();

		$inputRaw = $request->getInputRaw();
		$state->inputRaw = $inputRaw;
		$state->signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? null;

		$input = @json_decode($inputRaw, true);
		$filtered = \XF::app()->inputFilterer()->filterArray($input ?: [], [
			'data' => 'array',
			'id' => 'str',
			'type' => 'str',
		]);

		$event = $filtered['data'];

		$state->transactionId = $filtered['id'];
		$state->eventType = $filtered['type'];
		$state->event = $event['object'] ?? [];

		if (isset($state->event['metadata']['request_key']))
		{
			$state->requestKey = $state->event['metadata']['request_key'];
		}
		else
		{
			$identifiers = [
				'subscription' => $state->event['subscription'] ?? null,
				'payment_intent' => $state->event['payment_intent'] ?? null,
				'charge' => ($state->event['object'] === 'charge') ? $state->event['id'] : $state->event['charge'] ?? null,
			];

			$purchaseRequest = $this->findPurchaseRequestByMetadata($identifiers);
			if ($purchaseRequest)
			{
				$state->purchaseRequest = $purchaseRequest; // sets request key too
			}
		}

		// mostly legacy / fallback logic
		if (!$state->purchaseRequest)
		{
			if (isset($state->event['subscription'])
				&& is_string($state->event['subscription'])
				&& strpos($state->event['subscription'], 'sub_') === 0
			)
			{
				if (!$this->setPurchaseRequestFromSubscriptionId(
					$state->event['subscription'],
					$state
				))
				{
					// if we don't have the subscription ID listed yet
					if (isset($state->event['id']) && strpos($state->event['id'], 'in_') === 0)
					{
						$lines = $state->event['lines']['data'] ?? [];
						foreach ($lines AS $line)
						{
							$requestKey = $line['metadata']['request_key'] ?? null;
							if ($requestKey)
							{
								$state->requestKey = $requestKey;
								break;
							}
						}
					}
				}
			}
			else if (isset($state->event['object']) && ($state->event['object'] == 'review' || $state->event['object'] == 'dispute'))
			{
				// reviews/disputes don't have a metadata object, but set the payment intent or charge id
				$identifiers = [
					'payment_intent' => $state->event['payment_intent'] ?? null,
					'charge' => ($state->event['object'] === 'charge') ? $state->event['id'] : $state->event['charge'] ?? null,
				];

				$purchaseRequest = $this->findPurchaseRequestByMetadata($identifiers);
				if ($purchaseRequest)
				{
					$state->purchaseRequest = $purchaseRequest; // sets request key too
				}
			}
			else if (isset($state->event['object']) && $state->event['object'] == 'charge')
			{
				// generally for legacy one off payments where the charge object metadata doesn't contain the request key
				$chargeId = $state->event['id'];

				$identifiers = [
					'charge' => $chargeId,
				];
				$purchaseRequest = $this->findPurchaseRequestByMetadata($identifiers);

				if (!$purchaseRequest && !empty($state->event['invoice']) && $state->eventType === 'charge.refunded')
				{
					// ...or a subscription being refunded/terminated early.
					// Note that with the current code, we only want to check this for refunds as otherwise
					// this will pick up for charge.succeeded and potentially process an upgrade twice.
					$finder = \XF::finder(PaymentProfileFinder::class)->where('provider_id', 'stripe');
					foreach ($finder->fetch() AS $profile)
					{
						$this->setupStripe($profile);

						try
						{
							$invoice = Invoice::retrieve($state->event['invoice']);
							if ($invoice && $invoice->subscription)
							{
								$this->setPurchaseRequestFromSubscriptionId(
									$invoice->subscription,
									$state
								);
								break;
							}
						}
						catch (ExceptionInterface $e)
						{
							// just continue on
						}
					}
				}
				else
				{
					$state->purchaseRequest = $purchaseRequest; // sets request key too
				}
			}
		}

		if ($state->purchaseRequest)
		{
			$this->synchronizeProviderMetadata($state, $state->purchaseRequest);
		}

		return $state;
	}

	protected function setPurchaseRequestFromSubscriptionId(
		string $subscriptionId,
		CallbackState $state
	): bool
	{
		$state->subscriberId = $subscriptionId;

		$purchaseRequest = $this->getPurchaseRequestFromSubscriptionId($subscriptionId);

		if ($purchaseRequest)
		{
			$state->purchaseRequest = $purchaseRequest; // sets requestKey too
			return true;
		}

		return false;
	}

	protected function findPurchaseRequestByMetadata(array $identifiers): ?PurchaseRequest
	{
		foreach ($identifiers AS $key => $value)
		{
			if (!$value)
			{
				continue;
			}

			$purchaseRequestFinder = \XF::finder(PurchaseRequestFinder::class);
			$purchaseRequestFinder
				->where('provider_metadata', 'LIKE', $purchaseRequestFinder->escapeLike(
					'"' . $key . '":"' . $value . '"',
					'%?%'
				));

			$purchaseRequest = $purchaseRequestFinder->fetchOne();
			if ($purchaseRequest)
			{
				return $purchaseRequest;
			}
		}

		return null;
	}

	protected function synchronizeProviderMetadata(CallbackState $state, PurchaseRequest $purchaseRequest): void
	{
		$existingMetadata = $this->getProviderMetadata($purchaseRequest);

		$identifiers = [
			'subscription' => $state->event['subscription'] ?? null,
			'payment_intent' => $state->event['payment_intent'] ?? null,
			'charge' => ($state->event['object'] === 'charge') ? $state->event['id'] : $state->event['charge'] ?? null,
		];
		$identifiers = array_merge($existingMetadata, array_filter($identifiers));

		if ($identifiers)
		{
			ksort($identifiers);
			$purchaseRequest->provider_metadata = json_encode($identifiers);
			$purchaseRequest->save();
		}
	}

	protected function getPurchaseRequestFromSubscriptionId(string $subscriptionId): ?PurchaseRequest
	{
		$purchaseRequest = $this->findPurchaseRequestByMetadata([
			'subscription' => $subscriptionId,
		]);

		if ($purchaseRequest)
		{
			return $purchaseRequest;
		}

		$logFinder = \XF::finder(PaymentProviderLogFinder::class)
			->where('subscriber_id', $subscriptionId)
			->where('provider_id', $this->providerId)
			->order('log_date', 'desc');

		foreach ($logFinder->fetch() AS $log)
		{
			if ($log->purchase_request_key)
			{
				$purchaseRequest = \XF::em()->find(PurchaseRequest::class, $log->purchase_request_key);

				if ($purchaseRequest)
				{
					break;
				}
			}
		}

		return $purchaseRequest ?: null;
	}

	protected function validateExpectedValues(CallbackState $state)
	{
		return ($state->getPurchaseRequest() && $state->getPaymentProfile());
	}

	protected function verifyWebhookSignature(CallbackState $state)
	{
		$paymentProfile = $state->getPaymentProfile();

		if (empty($paymentProfile->options['signing_secret']))
		{
			return true; // not enabled so pass
		}

		if (empty($state->signature))
		{
			return false; // enabled but signature missing so fail
		}

		$secret = $paymentProfile->options['signing_secret'];
		$payload = $state->inputRaw;
		$signature = $state->signature;

		try
		{
			$this->setupStripe($paymentProfile);
			$verifiedEvent = Webhook::constructEvent($payload, $signature, $secret);
		}
		catch (UnexpectedValueException|SignatureVerificationException $e)
		{
			return false;
		}

		return $verifiedEvent;
	}

	protected function getActionableEvents()
	{
		// charge.dispute.created doesn't automatically indicate a loss of funds
		// charge.dispute.funds_withdrawn is the indicator for that, so we will ignore creation.
		return [
			'charge.dispute.funds_withdrawn',
			'charge.dispute.funds_reinstated',
			'charge.refunded',
			'charge.succeeded',
			'checkout.session.completed',
			'invoice.payment_succeeded',
			'review.closed',
			'review.opened',
		];
	}

	protected function isEventSkippable(CallbackState $state)
	{
		$eventType = $state->eventType;

		if (!in_array($eventType, $this->getActionableEvents()))
		{
			return true;
		}

		if ($eventType === 'invoice.payment_succeeded' && (array_key_exists('charge', $state->event) && $state->event['charge'] === null))
		{
			// no charge associated so we already charged in a separate transaction
			// this is likely the initial invoice payment so we can skip this.
			return true;
		}

		return false;
	}

	public function validateCallback(CallbackState $state)
	{
		if ($this->isEventSkippable($state))
		{
			// Stripe sends a lot of webhooks, we shouldn't log them.
			// They are viewable verbosely in the Stripe Dashboard.
			$state->httpCode = 200;
			return false;
		}

		if (!$this->validateExpectedValues($state))
		{
			if (
				!empty($state->eventType)
				&& $state->eventType === 'charge.succeeded'
				&& !empty($state->event['invoice'])
			)
			{
				// if there's an invoice, that would indicate a recurring subscription and we generally
				// listen for the invoice payment event
				$state->httpCode = 200;
				return false;
			}

			$state->logType = 'error';
			$state->logMessage = 'Event data received from Stripe does not contain the expected values.';
			if (!$state->requestKey)
			{
				$state->httpCode = 200; // Not likely to recover from this error so send a successful response.
			}
			return false;
		}

		if (!$this->verifyWebhookSignature($state))
		{
			$state->logType = 'error';
			$state->logMessage = 'Webhook received from Stripe could not be verified as being valid.';
			$state->httpCode = 400;

			return false;
		}

		return true;
	}

	public function validateCost(CallbackState $state)
	{
		$purchaseRequest = $state->getPurchaseRequest();

		$currency = $purchaseRequest->cost_currency;
		$cost = $this->prepareCost($purchaseRequest->cost_amount, $currency);

		$amountPaid = null;
		$balanceChange = 0;

		switch ($state->eventType)
		{
			case 'charge.succeeded':
				$amountPaid = $state->event['amount'];
				break;
			case 'invoice.payment_succeeded':
				$amountPaid = $state->event['amount_paid'];

				if (isset($state->event['ending_balance']) && isset($state->event['starting_balance']))
				{
					$balanceChange = $state->event['starting_balance'] - $state->event['ending_balance'];
				}
				break;
		}

		if ($amountPaid !== null)
		{
			$costValidated = (
				$amountPaid === $cost
				&& strtoupper($state->event['currency']) === $currency
			);

			if (!$costValidated && $balanceChange)
			{
				// manipulating subscription dates in Stripe can lead to customer balances rather than a charge
				// so account for the balance change adjusting the amount paid
				$costValidated = (
					$amountPaid === ($cost + $balanceChange)
					&& strtoupper($state->event['currency']) === $currency
				);
			}

			if (!$costValidated && $state->eventType == 'invoice.payment_succeeded')
			{
				// due to previous approach in prepareCost, the cost could have gone through
				// to Stripe with a 1 cent (or equivalent) lower value than expected, so allow that,
				// provided Stripe is telling us that they've paid what's due
				$costValidated = (
					$amountPaid === ($cost - 1)
					&& $state->event['amount_due'] === $amountPaid
					&& strtoupper($state->event['currency']) === $currency
				);
			}

			if (!$costValidated)
			{
				$state->logType = 'error';
				$state->logMessage = 'Invalid cost amount';
				return false;
			}

			return true;
		}

		return true;
	}

	public function getPaymentResult(CallbackState $state)
	{
		switch ($state->eventType)
		{
			// used only for updating payment details
			case 'checkout.session.completed':
				// indicates a SetupIntent
				if ($state->event['mode'] === 'setup')
				{
					$purchaseRequest = $state->getPurchaseRequest();
					$subscriberId = $this->getSubscriberIdFromPurchaseRequest($purchaseRequest);

					if (!$subscriberId || !$state->event['setup_intent'])
					{
						$state->logType = 'error';
						$state->logMessage = 'Cannot ascertain subscriber ID for SetupIntent';
						$state->httpCode = 400;
					}

					$this->setupStripe($state->paymentProfile);

					try
					{
						$setupIntent = SetupIntent::retrieve($state->event['setup_intent']);

						$paymentMethod = PaymentMethod::retrieve(
							$setupIntent->payment_method
						);

						Subscription::update($subscriberId, [
							'default_payment_method' => $paymentMethod->id,
						]);

						$state->paymentResult = CallbackState::PAYMENT_UPDATED;
					}
					catch (ExceptionInterface $e)
					{
						$state->logType = 'error';
						$state->logMessage = 'Unable to update payment method for subscription';
						$state->httpCode = 200;
					}
				}
				break;

			case 'charge.succeeded':
				if ($state->event['outcome']['type'] == 'authorized')
				{
					$state->paymentResult = CallbackState::PAYMENT_RECEIVED;
				}
				else
				{
					$state->logType = 'info';
					$state->logMessage = 'Charge succeeded but not authorized, it may require review in the Stripe Dashboard.';
				}

				$purchaseRequest = $state->purchaseRequest;
				$providerMetadata = $this->getProviderMetadata($purchaseRequest);

				if ($purchaseRequest
					&& !empty($providerMetadata['subscription'])
					&& !empty($state->event['payment_method'])
				)
				{
					$this->setupStripe($state->paymentProfile);

					try
					{
						/** @var Subscription $subscription */
						$subscription = Subscription::retrieve(
							$providerMetadata['subscription']
						);

						/** @var PaymentMethod $paymentMethod */
						$paymentMethod = PaymentMethod::retrieve(
							$state->event['payment_method']
						);

						Subscription::update($subscription->id, [
							'default_payment_method' => $paymentMethod->id,
						]);
					}
					catch (ExceptionInterface $e)
					{
					}
				}

				break;

			case 'invoice.payment_succeeded':
				$state->paymentResult = CallbackState::PAYMENT_RECEIVED;
				break;

			case 'review.closed':
				if ($state->event['reason'] == 'approved')
				{
					$state->paymentResult = CallbackState::PAYMENT_RECEIVED;
				}
				else
				{
					$state->logType = 'info';
					$state->logMessage = 'Previous payment review now closed, but not approved.';
				}
				break;

			case 'review.opened':
				$state->logType = 'info';
				$state->logMessage = 'A previous payment has been placed under review.';
				break;

			case 'charge.refunded':
			case 'charge.dispute.funds_withdrawn':
				$state->paymentResult = CallbackState::PAYMENT_REVERSED;
				break;

			case 'charge.dispute.funds_reinstated':
				$state->paymentResult = CallbackState::PAYMENT_REINSTATED;
				break;
		}
	}

	public function prepareLogData(CallbackState $state)
	{
		$state->logDetails = $state->event;
		$state->logDetails['eventType'] = $state->eventType;
	}

	protected $supportedCurrencies = [
		'AED', 'AFN', 'ALL', 'AMD', 'AOA',
		'ARS', 'AUD', 'AWG', 'AZN', 'BAM',
		'BBD', 'BDT', 'BGN', 'BIF', 'BMD',
		'BND', 'BOB', 'BRL', 'BWP', 'BZD',
		'CAD', 'CDF', 'CHF', 'CLP', 'CNY',
		'COP', 'CRC', 'CVE', 'CZK', 'DJF',
		'DKK', 'DOP', 'DZD', 'EGP', 'ETB',
		'EUR', 'GBP', 'GEL', 'GNF', 'GTQ',
		'GYD', 'HKD', 'HNL', 'HRK', 'HUF',
		'IDR', 'ILS', 'INR', 'ISK', 'JMD',
		'JPY', 'KES', 'KHR', 'KMF', 'KRW',
		'KZT', 'LBP', 'LKR', 'LRD', 'MAD',
		'MDL', 'MGA', 'MKD', 'MOP', 'MUR',
		'MXN', 'MYR', 'MZN', 'NAD', 'NGN',
		'NIO', 'NOK', 'NPR', 'NZD', 'PAB',
		'PEN', 'PHP', 'PKR', 'PLN', 'PYG',
		'QAR', 'RON', 'RSD', 'RUB', 'RWF',
		'SAR', 'SEK', 'SGD', 'SOS', 'STD',
		'THB', 'TOP', 'TRY', 'TTD', 'TWD',
		'TZS', 'UAH', 'UGX', 'USD', 'UYU',
		'UZS', 'VND', 'XAF', 'XOF', 'ZAR',
	];

	/**
	 * List of zero-decimal currencies as defined by Stripe's documentation. If we're dealing with one of these,
	 * this is already the smallest currency unit, and can be passed as-is. Otherwise convert it.
	 *
	 * @var array
	 */
	protected $zeroDecimalCurrencies = [
		'BIF', 'CLP', 'DJF', 'GNF', 'JPY',
		'KMF', 'KRW', 'MGA', 'PYG', 'RWF',
		'VND', 'VUV', 'XAF', 'XOF', 'XPF',
	];

	/**
	 * Given a cost and a currency, this will return the cost as an integer converted to the smallest currency unit.
	 *
	 * @param $cost
	 * @param $currency
	 *
	 * @return int
	 */
	protected function prepareCost($cost, $currency)
	{
		if (!in_array($currency, $this->zeroDecimalCurrencies))
		{
			$cost *= 100;
		}
		return intval(strval($cost));
	}

	public function verifyCurrency(PaymentProfile $paymentProfile, $currencyCode)
	{
		return (in_array($currencyCode, $this->supportedCurrencies));
	}

	/**
	 * @return string[]
	 */
	public function getCookieThirdParties(): array
	{
		return ['stripe'];
	}
}
