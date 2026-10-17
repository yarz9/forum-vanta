<?php

namespace XF\Pub\Controller;

use XF\Entity\PaymentProfile;
use XF\Entity\Purchasable;
use XF\Entity\PurchaseRequest;
use XF\Mvc\ParameterBag;
use XF\Purchasable\AbstractPurchasable;
use XF\Repository\PurchaseRepository;

class PurchaseController extends AbstractController
{
	public function actionIndex(ParameterBag $params)
	{
		$purchasable = $this->assertPurchasableExists($params->purchasable_type_id);

		if (!$purchasable->isActive())
		{
			throw $this->exception($this->error(\XF::phrase('items_of_this_type_cannot_be_purchased_at_moment')));
		}

		/** @var AbstractPurchasable $purchasableHandler */
		$purchasableHandler = $purchasable->handler;

		$purchase = $purchasableHandler->getPurchaseFromRequest($this->request, \XF::visitor(), $error);
		if (!$purchase)
		{
			throw $this->exception($this->error($error));
		}

		$purchaseRequest = $this->repository(PurchaseRepository::class)->insertPurchaseRequest($purchase);

		$providerHandler = $purchase->paymentProfile->getPaymentHandler();
		return $providerHandler->initiatePayment($this, $purchaseRequest, $purchase);
	}

	public function actionProcess()
	{
		$purchaseRequest = $this->em()->findOne(PurchaseRequest::class, $this->filter(['request_key' => 'str']), 'User');
		if (!$purchaseRequest)
		{
			throw $this->exception($this->error(\XF::phrase('invalid_purchase_request')));
		}

		/** @var PaymentProfile $paymentProfile */
		$paymentProfile = $this->em()->find(PaymentProfile::class, $purchaseRequest->payment_profile_id);
		if (!$paymentProfile)
		{
			throw $this->exception($this->error(\XF::phrase('purchase_request_contains_invalid_payment_profile')));
		}

		$purchasable = $this->assertPurchasableExists($purchaseRequest->purchasable_type_id);

		/** @var AbstractPurchasable $purchasableHandler */
		$purchasableHandler = $purchasable->handler;

		$purchase = $purchasableHandler->getPurchaseFromExtraData($purchaseRequest->extra_data, $paymentProfile, \XF::visitor(), $error);
		if (!$purchase)
		{
			throw $this->exception($this->error($error));
		}

		$providerHandler = $paymentProfile->Provider->handler;
		$result = $providerHandler->processPayment($this, $purchaseRequest, $paymentProfile, $purchase);
		if (!$result)
		{
			return $this->redirect($purchase->returnUrl);
		}

		return $result;
	}

	public function actionCancelRecurring(ParameterBag $params)
	{
		$purchaseRequest = $this->em()->findOne(PurchaseRequest::class, $this->filter(['request_key' => 'str']), 'User');
		if (!$purchaseRequest)
		{
			throw $this->exception($this->error(\XF::phrase('invalid_purchase_request')));
		}

		/** @var PaymentProfile $paymentProfile */
		$paymentProfile = $this->em()->find(PaymentProfile::class, $purchaseRequest->payment_profile_id);
		if (!$paymentProfile)
		{
			throw $this->exception($this->error(\XF::phrase('purchase_request_contains_invalid_payment_profile')));
		}

		$purchasable = $this->assertPurchasableExists($purchaseRequest->purchasable_type_id);

		/** @var AbstractPurchasable $purchasableHandler */
		$purchasableHandler = $purchasable->handler;
		$purchasableItem = $purchasableHandler->getPurchasableFromExtraData($purchaseRequest->extra_data);

		$providerHandler = $paymentProfile->Provider->handler;

		if ($this->isPost())
		{
			return $providerHandler->processCancellation($this, $purchaseRequest, $paymentProfile);
		}
		else
		{
			$viewParams = [
				'purchaseRequest' => $purchaseRequest,
				'paymentProfile' => $paymentProfile,
				'purchasableItem' => $purchasableItem,
			];
			return $this->view('XF:Purchase/CancelRecurring', 'payment_cancel_recurring_confirm', $viewParams);
		}
	}

	public function actionChangePayment(ParameterBag $params)
	{
		$purchaseRequest = $this->em()->findOne(PurchaseRequest::class, $this->filter(['request_key' => 'str']), 'User');
		if (!$purchaseRequest)
		{
			throw $this->exception($this->error(\XF::phrase('invalid_purchase_request')));
		}

		/** @var PaymentProfile $paymentProfile */
		$paymentProfile = $this->em()->find(PaymentProfile::class, $purchaseRequest->payment_profile_id);
		if (!$paymentProfile)
		{
			throw $this->exception($this->error(\XF::phrase('purchase_request_contains_invalid_payment_profile')));
		}

		$purchasable = $this->assertPurchasableExists($purchaseRequest->purchasable_type_id);

		/** @var AbstractPurchasable $purchasableHandler */
		$purchasableHandler = $purchasable->handler;

		$extraData = $purchaseRequest->extra_data;
		$purchasable = $purchasableHandler->getPurchasableFromExtraData($extraData);
		$purchase = $purchasableHandler->getPurchaseObject($paymentProfile, $purchasable['purchasable'], \XF::visitor());

		$providerHandler = $paymentProfile->Provider->handler;
		return $providerHandler->initiateChangePayment($this, $purchaseRequest, $purchase);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return Purchasable
	 */
	protected function assertPurchasableExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(Purchasable::class, $id, $with, $phraseKey);
	}

	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('managing_account_details');
	}
}
