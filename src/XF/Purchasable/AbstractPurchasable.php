<?php

namespace XF\Purchasable;

use XF\Entity\PaymentProfile;
use XF\Entity\PurchaseRequest;
use XF\Entity\User;
use XF\Http\Request;
use XF\Mvc\Entity\Entity;
use XF\Payment\CallbackState;

abstract class AbstractPurchasable
{
	protected $purchasableTypeId;

	/**
	 * Title of this Purchasable type.
	 *
	 * @return mixed
	 */
	abstract public function getTitle();

	/**
	 * Prepares all the data required to create the Purchase object.
	 *
	 * @param Request $request
	 * @param User $purchaser
	 * @param null $error
	 *
	 * @return Purchase
	 */
	abstract public function getPurchaseFromRequest(Request $request, User $purchaser, &$error = null);

	/**
	 * Given a purchase request's extra data, the purchasable item will be found and returned.
	 *
	 * @param array $extraData
	 *
	 * @return mixed
	 */
	abstract public function getPurchasableFromExtraData(array $extraData);

	/**
	 * Prepares all the data required to create the Purchase object from a purchase request's extra data.
	 *
	 * @param array $extraData
	 * @param PaymentProfile $paymentProfile
	 * @param User $purchaser
	 * @param string $error
	 *
	 * @return Purchase
	 */
	abstract public function getPurchaseFromExtraData(array $extraData, PaymentProfile $paymentProfile, User $purchaser, &$error = null);

	/**
	 * Creates the Purchase object which represents all of the data required to request a payment.
	 *
	 * @param PaymentProfile $paymentProfile
	 * @param $purchasable
	 * @param User $purchaser
	 *
	 * @return mixed
	 */
	abstract public function getPurchaseObject(PaymentProfile $paymentProfile, $purchasable, User $purchaser);

	/**
	 * @param CallbackState $state
	 *
	 * @return mixed
	 */
	abstract public function completePurchase(CallbackState $state);

	/**
	 * @param CallbackState $state
	 *
	 * @return mixed
	 */
	abstract public function reversePurchase(CallbackState $state);

	public function getActiveFromPurchaseRequest(PurchaseRequest $purchaseRequest): ?Entity
	{
		return null;
	}


	public function isCancelled(PurchaseRequest $purchaseRequest): bool
	{
		return false;
	}

	public function processCancellation(PurchaseRequest $purchaseRequest): void
	{
	}

	/**
	 * Method should be implemented to validate an existing payment callback pertains
	 * to a purchasable that still exists and is valid.
	 *
	 * @param CallbackState $state
	 * @param mixed $error Returns an error message explaining the issue if returning false
	 *
	 * @return bool
	 */
	public function validatePurchasable(CallbackState $state, &$error = null): bool
	{
		return true;
	}

	public function validatePurchaser(CallbackState $state, &$error = null)
	{
		if (!$state->getPurchaser())
		{
			if ($state->getPurchaseRequest()->user_id)
			{
				$error = 'Could not find user with user_id ' . $state->getPurchaseRequest()->user_id . '.';
			}
			else
			{
				$error = 'Purchasable type ' . $this->purchasableTypeId . ' does not support payments from guests.';
			}

			return false;
		}
		return true;
	}

	/**
	 * Given a payment profile ID, we can enumerate the purchasable items
	 * which are used by these profiles. Useful to block accidental deletion
	 * of payment profiles which may be legitimately in use.
	 *
	 * This method should return an array of purchasables which are in use by the given profile ID.
	 *
	 * @param $profileId
	 *
	 * @return array
	 */
	abstract public function getPurchasablesByProfileId($profileId);

	public function __construct($purchasableTypeId)
	{
		$this->purchasableTypeId = $purchasableTypeId;
	}

	public function sendPaymentReceipt(CallbackState $state)
	{
		$purchaser = $state->getPurchaser();
		if (!$purchaser || !$purchaser->email)
		{
			return;
		}

		switch ($state->paymentResult)
		{
			case CallbackState::PAYMENT_RECEIVED:
				$purchaseRequest = $state->getPurchaseRequest();
				if ($purchaseRequest)
				{
					$purchasable = $this->getPurchasableFromExtraData($purchaseRequest->extra_data);

					$params = [
						'purchaser' => $purchaser,
						'purchaseRequest' => $purchaseRequest,
						'purchasable' => $purchasable,
					];

					\XF::app()->mailer()->newMail()
						->setToUser($purchaser)
						->setTemplate('payment_received_receipt_' . $this->purchasableTypeId, $params)
						->send();
				}
		}
	}

	public function handlePaymentUpdated(CallbackState $state): void
	{
		$purchaser = $state->getPurchaser();
		if (!$purchaser || !$purchaser->email)
		{
			return;
		}

		if ($state->paymentResult == CallbackState::PAYMENT_UPDATED)
		{
			$purchaseRequest = $state->getPurchaseRequest();
			if ($purchaseRequest)
			{
				$purchasable = $this->getPurchasableFromExtraData($purchaseRequest->extra_data);

				$params = [
					'purchaser' => $purchaser,
					'purchaseRequest' => $purchaseRequest,
					'purchasable' => $purchasable,
				];

				\XF::app()->mailer()->newMail()
					->setToUser($purchaser)
					->setTemplate('payment_update_confirmation_' . $this->purchasableTypeId, $params)
					->send();
			}
		}
	}

	public function postCompleteTransaction(CallbackState $state)
	{
	}
}
