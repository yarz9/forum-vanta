<?php

namespace XF\Purchasable;

use XF\Entity\PaymentProfile;
use XF\Entity\PurchaseRequest;
use XF\Entity\User;
use XF\Entity\UserUpgradeActive;
use XF\Entity\UserUpgradeExpired;
use XF\Finder\UserUpgradeFinder;
use XF\Http\Request;
use XF\Mvc\Entity\Entity;
use XF\Payment\CallbackState;
use XF\Repository\WebhookRepository;
use XF\Service\User\DowngradeService;
use XF\Service\User\UpgradeService;

use function in_array, is_array;

class UserUpgrade extends AbstractPurchasable
{
	public function getTitle()
	{
		return \XF::phrase('user_upgrades');
	}

	public function getPurchaseFromRequest(Request $request, User $purchaser, &$error = null)
	{
		if (!$purchaser->user_id)
		{
			$error = \XF::phrase('login_required');
			return false;
		}

		$profileId = $request->filter('payment_profile_id', 'uint');
		$paymentProfile = \XF::em()->find(PaymentProfile::class, $profileId);
		if (!$paymentProfile || !$paymentProfile->active)
		{
			$error = \XF::phrase('please_choose_valid_payment_profile_to_continue_with_your_purchase');
			return false;
		}

		$userUpgradeId = $request->filter('user_upgrade_id', 'uint');
		$userUpgrade = \XF::em()->find(\XF\Entity\UserUpgrade::class, $userUpgradeId);
		if (!$userUpgrade || !$userUpgrade->canPurchase())
		{
			$error = \XF::phrase('this_item_cannot_be_purchased_at_moment');
			return false;
		}

		if (!in_array($profileId, $userUpgrade->payment_profile_ids))
		{
			$error = \XF::phrase('selected_payment_profile_is_not_valid_for_this_purchase');
			return false;
		}

		return $this->getPurchaseObject($paymentProfile, $userUpgrade, $purchaser);
	}

	public function validatePurchasable(CallbackState $state, &$error = null): bool
	{
		$purchaseRequest = $state->getPurchaseRequest();
		$extraData = $purchaseRequest->extra_data;

		$userUpgrade = \XF::em()->find(\XF\Entity\UserUpgrade::class, $extraData['user_upgrade_id']);
		if (!$userUpgrade)
		{
			$error = "Unable to find user upgrade '$extraData[user_upgrade_id]'";
			return false;
		}

		return true;
	}

	public function getPurchasableFromExtraData(array $extraData)
	{
		$output = [
			'link' => '',
			'title' => '',
			'purchasable' => null,
		];
		$userUpgrade = \XF::em()->find(\XF\Entity\UserUpgrade::class, $extraData['user_upgrade_id']);
		if ($userUpgrade)
		{
			$output['link'] = \XF::app()->router('admin')->buildLink('user-upgrades/edit', $userUpgrade);
			$output['title'] = $userUpgrade->title;
			$output['purchasable'] = $userUpgrade;
		}
		return $output;
	}

	public function getPurchaseFromExtraData(array $extraData, PaymentProfile $paymentProfile, User $purchaser, &$error = null)
	{
		$userUpgrade = $this->getPurchasableFromExtraData($extraData);
		if (!$userUpgrade['purchasable'] || !$userUpgrade['purchasable']->canPurchase())
		{
			$error = \XF::phrase('this_item_cannot_be_purchased_at_moment');
			return false;
		}

		if (!in_array($paymentProfile->payment_profile_id, $userUpgrade['purchasable']->payment_profile_ids))
		{
			$error = \XF::phrase('selected_payment_profile_is_not_valid_for_this_purchase');
			return false;
		}

		return $this->getPurchaseObject($paymentProfile, $userUpgrade['purchasable'], $purchaser);
	}

	/**
	 * @param PaymentProfile $paymentProfile
	 * @param \XF\Entity\UserUpgrade $purchasable
	 * @param User $purchaser
	 *
	 * @return Purchase
	 */
	public function getPurchaseObject(PaymentProfile $paymentProfile, $purchasable, User $purchaser)
	{
		$purchase = new Purchase();

		$purchase->title = \XF::phrase('account_upgrade') . ': ' . $purchasable->title . ' (' . $purchaser->username . ')';
		$purchase->description = $purchasable->description;
		$purchase->cost = $purchasable->cost_amount;
		$purchase->currency = $purchasable->cost_currency;
		$purchase->recurring = ($purchasable->recurring && $purchasable->length_unit);
		$purchase->lengthAmount = $purchasable->length_amount;
		$purchase->lengthUnit = $purchasable->length_unit;
		$purchase->purchaser = $purchaser;
		$purchase->paymentProfile = $paymentProfile;
		$purchase->purchasableTypeId = $this->purchasableTypeId;
		$purchase->purchasableId = $purchasable->user_upgrade_id;
		$purchase->purchasableTitle = $purchasable->title;
		$purchase->extraData = [
			'user_upgrade_id' => $purchasable->user_upgrade_id,
		];

		$router = \XF::app()->router('public');

		$purchase->returnUrl = $router->buildLink('canonical:account/upgrade-purchase');
		$purchase->updateUrl = $router->buildLink('canonical:account/upgrade-updated');
		$purchase->cancelUrl = $router->buildLink('canonical:account/upgrades');

		return $purchase;
	}

	public function completePurchase(CallbackState $state)
	{
		if ($state->legacy)
		{
			$purchaseRequest = null;
			$userUpgradeId = $state->userUpgrade->user_upgrade_id;
			$userUpgradeRecordId = $state->userUpgradeRecordId;
		}
		else
		{
			$purchaseRequest = $state->getPurchaseRequest();
			$userUpgradeId = $purchaseRequest->extra_data['user_upgrade_id'];
			$userUpgradeRecordId = $purchaseRequest->extra_data['user_upgrade_record_id'] ?? null;
		}

		$paymentResult = $state->paymentResult;
		$purchaser = $state->getPurchaser();

		$userUpgrade = \XF::em()->find(
			\XF\Entity\UserUpgrade::class,
			$userUpgradeId,
			'Active|' . $purchaser->user_id
		);

		/** @var UpgradeService $upgradeService */
		$upgradeService = \XF::app()->service(UpgradeService::class, $userUpgrade, $purchaser);

		if ($state->extraData && is_array($state->extraData))
		{
			$upgradeService->setExtraData($state->extraData);
		}

		$activeUpgrade = null;

		switch ($paymentResult)
		{
			case CallbackState::PAYMENT_RECEIVED:
				$upgradeService->setPurchaseRequestKey($state->requestKey);
				$activeUpgrade = $upgradeService->upgrade();

				\XF::repository(WebhookRepository::class)->queueWebhook(
					$userUpgrade->getEntityContentType(),
					$userUpgrade->getEntityId(),
					'purchase_complete',
					$userUpgrade,
					[
						'purchaser' => [
							'user_id' => $purchaser->user_id,
							'username' => $purchaser->username,
						],
					]
				);

				$state->logType = 'payment';
				$state->logMessage = 'Payment received, upgraded/extended.';
				break;

			case CallbackState::PAYMENT_REINSTATED:
				if ($userUpgradeRecordId)
				{
					$existingRecord = \XF::em()->find(UserUpgradeActive::class, $userUpgradeRecordId);
					$endColumn = 'end_date';

					if (!$existingRecord)
					{
						$existingRecord = \XF::em()->find(UserUpgradeExpired::class, $userUpgradeRecordId);
						$endColumn = 'original_end_date';
					}
					if ($existingRecord)
					{
						$upgradeService->setEndDate($existingRecord->$endColumn);
					}
					$upgradeService->ignoreUnpurchasable(true);
					$activeUpgrade = $upgradeService->upgrade();

					\XF::repository(WebhookRepository::class)->queueWebhook(
						$userUpgrade->getEntityContentType(),
						$userUpgrade->getEntityId(),
						'purchase_reinstate',
						$userUpgrade,
						[
							'purchaser' => [
								'user_id' => $purchaser->user_id,
								'username' => $purchaser->username,
							],
						]
					);

					$state->logType = 'payment';
					$state->logMessage = 'Reversal cancelled, upgrade reactivated.';
				}
				else
				{
					// We can't reinstate the upgrade because there doesn't appear to be an existing record.
					$state->logType = 'info';
					$state->logMessage = 'OK, no action.';
				}
				break;
		}

		if ($activeUpgrade && $purchaseRequest)
		{
			$extraData = $purchaseRequest->extra_data;
			$extraData['user_upgrade_record_id'] = $activeUpgrade->user_upgrade_record_id;
			$purchaseRequest->extra_data = $extraData;
			$purchaseRequest->save();
		}
	}

	public function reversePurchase(CallbackState $state)
	{
		if ($state->legacy)
		{
			$purchaseRequest = null;
			$userUpgradeId = $state->userUpgrade->user_upgrade_id;
		}
		else
		{
			$purchaseRequest = $state->getPurchaseRequest();
			$userUpgradeId = $purchaseRequest->extra_data['user_upgrade_id'];
		}

		$purchaser = $state->getPurchaser();

		$userUpgrade = \XF::em()->find(
			\XF\Entity\UserUpgrade::class,
			$userUpgradeId,
			'Active|' . $purchaser->user_id
		);

		/** @var DowngradeService $downgradeService */
		$downgradeService = \XF::app()->service(DowngradeService::class, $userUpgrade, $purchaser);
		$downgradeService->setSendAlert(false);
		$downgradeService->downgrade();

		\XF::repository(WebhookRepository::class)->queueWebhook(
			$userUpgrade->getEntityContentType(),
			$userUpgrade->getEntityId(),
			'purchase_reverse',
			$userUpgrade,
			[
				'purchaser' => [
					'user_id' => $purchaser->user_id,
					'username' => $purchaser->username,
				],
			]
		);

		$state->logType = 'cancel';
		$state->logMessage = 'Payment refunded/reversed, downgraded.';
	}

	public function getActiveFromPurchaseRequest(PurchaseRequest $purchaseRequest): ?Entity
	{
		/** @var \XF\Entity\UserUpgrade $purchasable */
		$purchasable = $this->getPurchasableFromExtraData($purchaseRequest->extra_data)['purchasable'] ?? null;

		if (!$purchasable)
		{
			return null;
		}

		$userId = $purchaseRequest->user_id;
		return $purchasable->Active[$userId] ?? null;
	}

	public function isCancelled(PurchaseRequest $purchaseRequest): bool
	{
		/** @var UserUpgradeActive $active */
		$active = $this->getActiveFromPurchaseRequest($purchaseRequest);

		if (!$active)
		{
			return false;
		}

		return $active->is_cancelled;
	}

	public function processCancellation(PurchaseRequest $purchaseRequest): void
	{
		/** @var UserUpgradeActive $active */
		$active = $this->getActiveFromPurchaseRequest($purchaseRequest);

		if ($active && $active->isValidColumn('is_cancelled'))
		{
			$active->fastUpdate('is_cancelled', true);
		}
	}

	public function getPurchasablesByProfileId($profileId)
	{
		$finder = \XF::finder(UserUpgradeFinder::class);

		$quotedProfileId = $finder->quote($profileId);
		$columnName = $finder->columnSqlName('payment_profile_ids');

		$router = \XF::app()->router('admin');
		$upgrades = $finder->whereSql("FIND_IN_SET($quotedProfileId, $columnName)")->fetch();
		return $upgrades->pluck(function (\XF\Entity\UserUpgrade $upgrade, $key) use ($router)
		{
			return ['user_upgrade_' . $key, [
				'title' => $this->getTitle() . ': ' . $upgrade->title,
				'link' => $router->buildLink('user-upgrades/edit', $upgrade),
			]];
		}, false);
	}
}
