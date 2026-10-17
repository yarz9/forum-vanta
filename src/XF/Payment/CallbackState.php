<?php

namespace XF\Payment;

use XF\Entity\PaymentProfile;
use XF\Entity\Purchasable;
use XF\Entity\PurchaseRequest;
use XF\Entity\User;
use XF\Purchasable\AbstractPurchasable;

/**
 * @property PurchaseRequest $purchaseRequest
 * @property AbstractPurchasable $purchasableHandler
 * @property PaymentProfile $paymentProfile
 * @property User $purchaser ;
 *
 * @property int $paymentResult
 *
 * @property string $requestKey
 *
 * @property string $transactionId
 * @property string $subscriberId
 * @property string $paymentCountry
 *
 * @property string $logType
 * @property string $logMessage
 * @property array $logDetails
 * @property int $httpCode
 */
#[\AllowDynamicProperties]
class CallbackState
{
	protected $purchaseRequest;
	protected $purchasableHandler;
	protected $paymentProfile;
	protected $purchaser;
	protected $paymentResult;

	public const PAYMENT_RECEIVED = 1; // received payment
	public const PAYMENT_REVERSED = 2; // refund/reversal
	public const PAYMENT_REINSTATED = 3; // reversal cancelled
	public const PAYMENT_UPDATED = 4; // updated payment method

	public function getPurchaseRequest()
	{
		return $this->purchaseRequest;
	}

	/**
	 * @return AbstractPurchasable|false
	 */
	public function getPurchasableHandler()
	{
		if ($this->purchasableHandler)
		{
			return $this->purchasableHandler;
		}

		$purchaseRequest = $this->getPurchaseRequest();
		if (!$purchaseRequest)
		{
			return false;
		}

		/** @var Purchasable $purchasable */
		$purchasable = \XF::em()->find(Purchasable::class, $purchaseRequest->purchasable_type_id);
		if (!$purchasable || !$purchasable->handler)
		{
			return false;
		}

		$this->purchasableHandler = $purchasable->handler;
		return $this->purchasableHandler;
	}

	public function getPaymentProfile()
	{
		if ($this->paymentProfile)
		{
			return $this->paymentProfile;
		}

		$purchaseRequest = $this->getPurchaseRequest();
		if (!$purchaseRequest)
		{
			return false;
		}

		$paymentProfile = \XF::em()->find(PaymentProfile::class, $purchaseRequest->payment_profile_id);
		if (!$paymentProfile)
		{
			return false;
		}

		$this->paymentProfile = $paymentProfile;
		return $this->paymentProfile;
	}

	public function getPurchaser()
	{
		if ($this->purchaser)
		{
			return $this->purchaser;
		}

		$purchaseRequest = $this->purchaseRequest;
		if (!$purchaseRequest)
		{
			return false;
		}

		$user = \XF::em()->find(User::class, $purchaseRequest->user_id);
		if (!$user)
		{
			return false;
		}

		$this->purchaser = $user;
		return $this->purchaser;
	}

	public function __get($name)
	{
		return $this->{$name} ?? null;
	}

	public function __set($name, $value)
	{
		switch ($name)
		{
			case 'purchaseRequest':
				$this->purchaseRequest = $value;
				if ($value)
				{
					$this->requestKey = $value->request_key;
				}
				break;

			case 'requestKey':
				$this->purchaseRequest = \XF::em()->findOne(PurchaseRequest::class, ['request_key' => $value]);
				$this->requestKey = $value;
				break;

			default:
				$this->{$name} = $value;
				break;
		}
	}
}
