<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\OAuthClient;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Repository\ApiRepository;
use XF\Repository\OAuthRepository;

class OAuth2Controller extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertAdminPermission('oauth2');
		$this->assertPasswordVerified(1800); // 30 minutes
	}

	public function actionClient(): AbstractReply
	{
		$this->setSectionContext('oAuthClients');

		$oAuthRepo = $this->getOAuthRepo();
		$clients = $oAuthRepo->findClientsForList()->fetch();

		$viewParams = [
			'clients' => $clients,
		];
		return $this->view('XF:OAuth\Client\List', 'oauth_client_list', $viewParams);
	}

	protected function clientAddEdit(OAuthClient $client): AbstractReply
	{
		$viewParams = [
			'client' => $client,
			'scopes' => $this->repository(ApiRepository::class)->findApiScopesForList()->usableForOAuth()->fetch(),
		];
		return $this->view('XF:OAuth\Client\Edit', 'oauth_client_edit', $viewParams);
	}

	public function actionClientAdd(): AbstractReply
	{
		$this->setSectionContext('oAuthClients');

		$client = $this->em()->create(OAuthClient::class);
		return $this->clientAddEdit($client);
	}

	public function actionClientEdit(ParameterBag $params): AbstractReply
	{
		$this->setSectionContext('oAuthClients');

		$client = $this->assertOAuthClientExists($params->client_id);
		return $this->clientAddEdit($client);
	}

	protected function clientSaveProcess(OAuthClient $client): FormAction
	{
		$form = $this->formAction();

		$input = $this->filter([
			'title' => 'str',
			'description' => 'str',
			'client_type' => 'str',
			'redirect_uris' => 'array-str',
			'homepage_url' => 'str',
			'image_url' => 'str',
			'active' => 'bool',
			'allowed_scopes' => 'array-str',
		]);

		$input['redirect_uris'] = array_filter($input['redirect_uris']);

		$form->basicEntitySave($client, $input);

		return $form;
	}

	public function actionClientSave(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		if ($params->client_id)
		{
			$client = $this->assertOAuthClientExists($params->client_id);
		}
		else
		{
			$client = $this->em()->create(OAuthClient::class);
		}

		$this->clientSaveProcess($client)->run();

		return $this->redirect($this->buildLink('oauth2/clients', $client));
	}

	public function actionClientRegenerate(ParameterBag $params)
	{
		$this->setSectionContext('oAuthClients');

		$client = $this->assertOAuthClientExists($params->client_id);

		if ($this->isPost())
		{
			$client->client_secret = $client->generateClientSecret();
			$client->save();

			return $this->redirect($this->buildLink('oauth2/clients/edit', $client));
		}

		$viewParams = [
			'client' => $client,
		];

		return $this->view('XF:OAuth\Client\Regenerate', 'oauth_client_regenerate', $viewParams);
	}

	public function actionClientDelete(ParameterBag $params)
	{
		$this->setSectionContext('oAuthClients');

		$client = $this->assertOAuthClientExists($params->client_id);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$client,
			$this->buildLink('oauth2/clients/delete', $client),
			$this->buildLink('oauth2/clients/edit', $client),
			$this->buildLink('oauth2/clients'),
			$client->title
		);
	}

	public function actionClientToggle(): AbstractReply
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(OAuthClient::class);
	}

	public function actionClientViewSecret(ParameterBag $params): AbstractReply
	{
		$this->setSectionContext('oAuthClients');

		$client = $this->assertOAuthClientExists($params->client_id);

		$viewParams = [
			'client' => $client,
		];
		return $this->view('XF:OAuth\Client\View', 'oauth_client_view', $viewParams);
	}

	protected function assertOAuthClientExists($id, $with = null, $phraseKey = null): OAuthClient
	{
		return $this->assertRecordExists(OAuthClient::class, $id, $with, $phraseKey);
	}

	protected function getOAuthRepo(): OAuthRepository
	{
		return $this->repository(OAuthRepository::class);
	}
}
