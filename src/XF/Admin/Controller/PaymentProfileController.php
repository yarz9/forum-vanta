<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\PaymentProfile;
use XF\Entity\PaymentProvider;
use XF\Finder\PaymentProfileFinder;
use XF\Finder\PurchasableFinder;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Purchasable\AbstractPurchasable;
use XF\Repository\PaymentRepository;

class PaymentProfileController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('payment');
	}

	public function actionIndex()
	{
		$paymentRepo = $this->getPaymentRepo();

		$profiles = $paymentRepo->findPaymentProfilesForList()->fetch();
		$providers = $profiles->pluckNamed('Provider', 'provider_id');

		$viewParams = [
			'totalProfiles' => $profiles->count(),
			'groupedProfiles' => $profiles->groupBy('provider_id'),
			'providers' => $providers,
		];
		return $this->view('XF:PaymentProfile\Listing', 'payment_profile_list', $viewParams);
	}

	public function profileAddEdit(PaymentProfile $profile, PaymentProvider $provider)
	{
		$viewParams = [
			'profile' => $profile,
			'provider' => $provider,
		];
		return $this->view('XF:PaymentProfile\Edit', 'payment_profile_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$profile = $this->assertProfileExists($params->payment_profile_id);

		if (!$profile->active)
		{
			return $this->error(\XF::phrase('this_payment_profile_is_no_longer_active_so_it_cannot_be_edited'));
		}

		return $this->profileAddEdit($profile, $profile->Provider);
	}

	public function actionAdd()
	{
		$providerId = $this->filter('provider_id', 'str');

		if (!$providerId)
		{
			if ($this->isPost())
			{
				return $this->error(\XF::phrase('you_must_select_payment_provider_to_continue'));
			}
			else
			{
				$providers = $this->getPaymentRepo()
					->findActivePaymentProviders()
					->pluckFrom('title', 'provider_id');

				if (!$providers)
				{
					throw $this->exception(
						$this->notFound(\XF::phrase('you_cannot_create_payment_profile_as_there_no_valid_payment_providers'))
					);
				}

				$viewParams = [
					'providers' => $providers,
				];
				return $this->view('XF:PaymentProfile\ChooseProvider', 'payment_profile_choose_provider', $viewParams);
			}
		}

		/** @var PaymentProfile $profile */
		$profile = $this->em()->create(PaymentProfile::class);
		$provider = $this->assertProviderExists($providerId);
		$profile->provider_id = $provider->provider_id;

		if ($this->isPost())
		{
			return $this->redirect(
				$this->buildLink('payment-profiles/add', null, ['provider_id' => $provider->provider_id]),
				''
			);
		}

		return $this->profileAddEdit($profile, $provider);
	}

	protected function profileSaveProcess(PaymentProfile $profile, PaymentProvider $provider)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'provider_id' => 'str',
			'title' => 'str',
			'display_title' => 'str',
		]);

		$options = $this->filter('options', 'array-str');

		$form->validate(function (FormAction $form) use ($profile, $provider, $options)
		{
			$provider->getHandler()->verifyConfig($options, $errors);
			if ($errors)
			{
				$form->logErrors($errors);
			}
			$profile->options = $options;
		});

		$form->basicEntitySave($profile, $input);

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->payment_profile_id)
		{
			$profile = $this->assertProfileExists($params->payment_profile_id);

			if (!$profile->active)
			{
				return $this->error(\XF::phrase('this_payment_profile_is_no_longer_active_so_it_cannot_be_edited'));
			}
		}
		else
		{
			$profile = $this->em()->create(PaymentProfile::class);

			$providerId = $this->filter('provider_id', 'str');
			$provider = $this->assertProviderExists($providerId);

			$profile->provider_id = $provider->provider_id;
		}

		$this->profileSaveProcess($profile, $profile->Provider)->run();

		return $this->redirect($this->buildLink('payment-profiles') . $this->buildLinkHash($profile->payment_profile_id));
	}

	public function actionDelete(ParameterBag $params)
	{
		$profile = $this->assertProfileExists($params->payment_profile_id);

		$profileUsed = [];

		$purchasableTypes = $this->finder(PurchasableFinder::class)->fetch();
		foreach ($purchasableTypes AS $purchasableType)
		{
			/** @var AbstractPurchasable $handler */
			$handler = $purchasableType->handler;
			if ($handler)
			{
				$purchasableItems = $handler->getPurchasablesByProfileId($profile->payment_profile_id);
				$profileUsed = array_merge($profileUsed, $purchasableItems ?: []);
			}
		}

		if ($this->isPost())
		{
			$profile->active = false;
			$profile->save();

			return $this->redirect($this->buildLink('payment-profiles'));
		}
		else
		{
			$viewParams = [
				'profile' => $profile,
				'profileUsed' => $profileUsed,
			];
			return $this->view('XF:PaymentProfile\Delete', 'payment_profile_delete', $viewParams);
		}
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(PaymentProfileFinder::class);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return PaymentProvider
	 */
	protected function assertProviderExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(PaymentProvider::class, $id, $with, $phraseKey);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return PaymentProfile
	 */
	protected function assertProfileExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(PaymentProfile::class, $id, $with, $phraseKey);
	}

	/**
	 * @return PaymentRepository
	 */
	protected function getPaymentRepo()
	{
		return $this->repository(PaymentRepository::class);
	}
}
