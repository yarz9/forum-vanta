<?php

namespace XF\ConnectedAccount\Provider;

use XF\ConnectedAccount\ProviderData\XenForoProviderData;
use XF\ConnectedAccount\Service\XenForoService;
use XF\Entity\ConnectedAccountProvider;
use XF\Entity\User;
use XF\Phrase;

class XenForoProvider extends AbstractProvider
{
	public function getOAuthServiceName(): string
	{
		return XenForoService::class;
	}

	public function getProviderDataClass(): string
	{
		return XenForoProviderData::class;
	}

	public function getDefaultOptions(): array
	{
		return [
			'display_title' => '',
			'board_url' => '',
			'client_id' => '',
			'client_secret' => '',
		];
	}

	public function getOAuthConfig(ConnectedAccountProvider $provider, $redirectUri = null): array
	{
		return [
			'key' => $provider->options['client_id'],
			'secret' => $provider->options['client_secret'],
			'scopes' => ['user:read'],
			'redirect' => $redirectUri ?: $this->getRedirectUri($provider),
		];
	}

	public function getTitle(): Phrase
	{
		return \XF::phrase('con_acc.xenforo');
	}

	public function getDescription(): Phrase
	{
		return \XF::phrase('con_acc_desc.xenforo');
	}

	public function renderConfig(ConnectedAccountProvider $provider): string
	{
		return \XF::app()->templater()->renderTemplate('admin:connected_account_provider_xenforo', [
			'options' => $this->getEffectiveOptions($provider->options),
		]);
	}

	public function getTestTemplateName(): string
	{
		return 'admin:connected_account_provider_test_xenforo';
	}

	public function renderAssociated(ConnectedAccountProvider $provider, User $user): string
	{
		return \XF::app()->templater()->renderTemplate('public:connected_account_associated_xenforo', [
			'provider' => $provider,
			'user' => $user,
			'providerData' => $provider->getUserInfo($user),
			'connectedAccounts' => $user->Profile->connected_accounts,
		]);
	}
}
