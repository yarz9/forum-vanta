<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Payment\AbstractProvider;
use XF\Repository\PaymentRepository;

/**
 * COLUMNS
 * @property string $provider_id
 * @property string $provider_class
 * @property string $addon_id
 *
 * GETTERS
 * @property-read string $title
 * @property-read AbstractProvider|null $handler
 *
 * RELATIONS
 * @property-read AddOn|null $AddOn
 */
class PaymentProvider extends Entity
{
	public function isDeprecated(): bool
	{
		$handler = $this->handler;
		return $handler ? $handler->isDeprecated() : false;
	}

	/**
	 * @return string
	 */
	public function getTitle()
	{
		$handler = $this->handler;
		return $handler ? $handler->getTitle() : '';
	}

	public function renderConfig(PaymentProfile $profile)
	{
		$handler = $this->handler;
		if (!$handler)
		{
			return '';
		}
		return $handler->renderConfig($profile);
	}

	public function renderCancellation(UserUpgradeActive $active)
	{
		$handler = $this->handler;
		if (!$handler || !$active->PurchaseRequest)
		{
			return '';
		}
		return $handler->renderCancellationTemplate($active->PurchaseRequest);
	}

	public function renderChangePayment(UserUpgradeActive $active)
	{
		$handler = $this->handler;
		if (!$handler || !$active->PurchaseRequest)
		{
			return '';
		}
		return $handler->renderChangePaymentTemplate($active->PurchaseRequest);
	}

	/**
	 * @return string[]
	 */
	public function getCookieThirdParties(): array
	{
		$handler = $this->handler;
		if (!$handler)
		{
			return [];
		}

		return $handler->getCookieThirdParties();
	}

	/**
	 * @return AbstractProvider|null
	 */
	public function getHandler()
	{
		$paymentRepo = $this->repository(PaymentRepository::class);

		return $paymentRepo->getPaymentProviderHandler(
			$this->provider_id,
			$this->provider_class
		);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_payment_provider';
		$structure->shortName = 'XF:PaymentProvider';
		$structure->primaryKey = 'provider_id';
		$structure->columns = [
			'provider_id' => ['type' => self::STR, 'maxLength' => 25, 'match' => self::MATCH_ALPHANUMERIC, 'required' => true],
			'provider_class' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
			'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'default' => ''],
		];
		$structure->getters = [
			'title' => false,
			'handler' => true,
		];
		$structure->relations = [
			'AddOn' => [
				'entity' => 'XF:AddOn',
				'type' => self::TO_ONE,
				'conditions' => 'addon_id',
				'primary' => true,
			],
		];

		return $structure;
	}
}
