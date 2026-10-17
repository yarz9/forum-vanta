<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\ApiKey;
use XF\Finder\ApiKeyFinder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Repository\ApiRepository;
use XF\Service\ApiKey\ManagerService;

class ApiKeyController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertSuperAdmin();
		$this->assertPasswordVerified(1800); // 30 minutes
	}

	public function actionIndex()
	{
		$repo = $this->getApiRepo();
		$apiKeys = $repo->findApiKeysForList()->fetch();

		$newKeyId = $this->filter('new_key_id', 'uint');
		if ($newKeyId)
		{
			$newKey = $this->em()->find(ApiKey::class, $newKeyId);
		}
		else
		{
			$newKey = null;
		}

		$viewParams = [
			'apiKeys' => $apiKeys,
			'newKey' => $newKey,
		];
		return $this->view('XF:ApiKey\List', 'api_key_list', $viewParams);
	}

	protected function apiKeyAddEdit(ApiKey $apiKey)
	{
		$viewParams = [
			'apiKey' => $apiKey,
			'scopes' => $this->getApiRepo()->findApiScopesForList()->fetch(),
		];
		return $this->view('XF:ApiKey\Edit', 'api_key_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$apiKey = $this->assertApiKeyExists($params->api_key_id, ['User', 'Creator']);
		return $this->apiKeyAddEdit($apiKey);
	}

	public function actionAdd()
	{
		$apiKey = $this->em()->create(ApiKey::class);
		return $this->apiKeyAddEdit($apiKey);
	}

	protected function apiKeySaveProcess(ManagerService $keyManager)
	{
		$form = $this->formAction();

		$form->basicValidateServiceSave($keyManager, function () use ($keyManager)
		{
			$input = $this->filter([
				'title' => 'str',
				'active' => 'bool',
				'key_type' => 'str',
				'username' => 'str',
				'allow_all_scopes' => 'bool',
				'scopes' => 'array-str',
			]);

			$keyManager->setTitle($input['title']);
			$keyManager->setActive($input['active']);
			$keyManager->setScopes($input['allow_all_scopes'], $input['scopes']);
			$keyManager->setKeyType($input['key_type'], $input['username']);
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->api_key_id)
		{
			$apiKey = $this->assertApiKeyExists($params->api_key_id);
			$newKey = false;
		}
		else
		{
			$apiKey = $this->em()->create(ApiKey::class);
			$newKey = true;
		}

		/** @var ManagerService $keyManager */
		$keyManager = $this->service(ManagerService::class, $apiKey);

		$this->apiKeySaveProcess($keyManager)->run();

		if ($newKey)
		{
			$params = ['new_key_id' => $apiKey->api_key_id];
		}
		else
		{
			$params = [];
		}

		return $this->redirect($this->buildLink('api-keys', null, $params));
	}

	public function actionRegenerate(ParameterBag $params)
	{
		$apiKey = $this->assertApiKeyExists($params->api_key_id);

		if ($this->isPost())
		{
			/** @var ManagerService $keyManager */
			$keyManager = $this->service(ManagerService::class, $apiKey);
			$keyManager->regenerate();

			if (!$keyManager->validate($errors))
			{
				return $this->error($errors);
			}

			$keyManager->save();

			return $this->redirect($this->buildLink('api-keys', null, ['new_key_id' => $apiKey->api_key_id]));
		}
		else
		{
			$viewParams = [
				'apiKey' => $apiKey,
			];
			return $this->view('XF:ApiKey\Regenerate', 'api_key_regenerate', $viewParams);
		}
	}

	public function actionDelete(ParameterBag $params)
	{
		$apiKey = $this->assertApiKeyExists($params->api_key_id);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$apiKey,
			$this->buildLink('api-keys/delete', $apiKey),
			$this->buildLink('api-keys/edit', $apiKey),
			$this->buildLink('api-keys'),
			$apiKey->title
		);
	}

	public function actionViewKey(ParameterBag $params)
	{
		$apiKey = $this->assertApiKeyExists($params->api_key_id);

		$viewParams = [
			'apiKey' => $apiKey,
		];
		return $this->view('XF:ApiKey\View', 'api_key_view', $viewParams);
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(ApiKeyFinder::class);
	}

	/**
	 * @param string $apiKeyId
	 * @param null|string|array $with
	 * @param null|string $phraseKey
	 *
	 * @return ApiKey
	 *
	 * @throws Exception
	 */
	protected function assertApiKeyExists($apiKeyId, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(ApiKey::class, $apiKeyId, $with, $phraseKey);
	}

	/**
	 * @return ApiRepository
	 */
	protected function getApiRepo()
	{
		return $this->repository(ApiRepository::class);
	}
}
