<?php

namespace XF\Api;

use XF\Api\Controller\AbstractController;
use XF\Api\Mvc\Dispatcher;
use XF\Api\Mvc\Renderer\Api;
use XF\Container;
use XF\Entity\OAuthClient;
use XF\Entity\OAuthToken;
use XF\Entity\User;
use XF\Http\Response;
use XF\Mvc\Renderer\AbstractRenderer;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Error;
use XF\Mvc\RouteMatch;
use XF\Repository\ApiRepository;
use XF\Repository\UserRepository;

class App extends \XF\App
{
	protected $preLoadLocal = [
		'forumTypes',
		'routesAdmin',
		'routesPublic',
		'routesApi',
		'userFieldsInfo',
		'threadFieldsInfo',
		'threadPrefixes',
		'threadTypes',
	];

	public $apiKeyOmitted = false;

	public function initializeExtra()
	{
		$container = $this->container;

		$container['app.classType'] = 'Api';
		$container['app.defaultType'] = 'api';

		$container['dispatcher'] = function (Container $c)
		{
			$class = $this->extendClass(Dispatcher::class);
			return new $class($this);
		};
		$container['router'] = function (Container $c)
		{
			return $c['router.api'];
		};
		$container['session'] = function (Container $c)
		{
			return $c['session.api'];
		};

		$container['templater'] = function (Container $c)
		{
			return $this->setupTemplaterObject($c, Templater::class);
		};

		$container['renderer.unknown'] = function ()
		{
			return function ($rendererType)
			{
				if ($rendererType === 'api')
				{
					// only register this in the API app so it can't be used elsewhere
					return Api::class;
				}

				return 'Html';
			};
		};
	}

	public function setup(array $options = [])
	{
		parent::setup($options);
		$this->assertConfigExists();

		$this->fire('app_api_setup', [$this]);
	}

	/**
	 * @return list<string>
	 */
	protected function getPreloadExtraKeys(): array
	{
		$keys = parent::getPreloadExtraKeys();

		$this->fire('app_api_registry_preload', [$this, &$keys]);

		return $keys;
	}

	public function start($allowShortCircuit = false)
	{
		parent::start($allowShortCircuit);

		if (!$this->config('enableApi'))
		{
			return $this->getApiErrorResponse(\XF::phrase('api_error.api_disabled'), $this->config('serviceUnavailableCode'));
		}

		$this->fire('app_api_start_begin', [$this]);

		$user = $this->validateUserFromApiHeader($error, $code);
		if ($user instanceof Response)
		{
			// probably app_api_validate_request overridden to return a raw response
			return $user;
		}
		else if (!$user)
		{
			return $this->getApiErrorResponse(\XF::phrase($error), $code);
		}

		if (!$user->user_id)
		{
			$guestUsername = $this->request()->filter('api_guest_username', 'string', '');

			$user->setReadOnly(false);
			$user->setAsSaved('username', $guestUsername);
			$user->setReadOnly(true);
		}

		\XF::setVisitor($user);

		$language = $this->userLanguage($user);
		$language->setTimeZone($user->timezone);
		\XF::setLanguage($language);

		if ($this->request()->filter('api_bypass_permissions', 'bool') && \XF::apiKey()->is_super_user)
		{
			\XF::setApiBypassPermissions(true);
		}

		$this->fire('app_api_start_end', [$this]);

		return null;
	}

	public function preDispatch(RouteMatch $match)
	{
		if ($this->apiKeyOmitted)
		{
			$controller = $this->controller($match->getController(), $this->request());
			if ($controller instanceof AbstractController)
			{
				if ($controller->allowUnauthenticatedRequest($match->getAction()))
				{
					// unauthenticated is ok, so continue the request
					return null;
				}
			}

			return $this->getApiErrorResponse(\XF::phrase('api_error.no_api_key_in_request'), 400);
		}

		return null;
	}

	protected function getApiErrorResponse($error, $code = 400)
	{
		$renderer = $this->renderer('api');
		$reply = new Error($error, $code);

		$renderer->setReply($reply);
		$renderer->setResponseCode($code);
		$content = $renderer->renderErrors($reply->getErrors());
		$content = $renderer->postFilter($content, $reply);

		$response = $renderer->getResponse();
		$response->body($content);

		return $response;
	}

	protected function validateUserFromApiHeader(&$error = '', &$code = null)
	{
		$request = $this->request();

		$result = null;
		$this->fire('app_api_validate_request', [$request, &$result, &$error, &$code]);
		if ($result !== null)
		{
			return $result;
		}

		$apiKeyValue = $request->getApiKey();
		$apiUserId = $request->getApiUser();
		$authorizationHeader = $request->getAuthorizationHeader();

		if ($authorizationHeader)
		{
			[$scheme, $tokenOrSecret] = array_replace(
				['', ''],
				explode(' ', $authorizationHeader, 2)
			);

			switch ($scheme)
			{
				case 'Basic':
					$tokenOrSecret = base64_decode($tokenOrSecret);
					[$clientId, $clientSecret] = explode(':', $tokenOrSecret, 2);

					$client = $this->finder(OAuthClient::class)->where([
						'client_id' => $clientId,
						'client_secret' => $clientSecret,
					])->fetchOne();

					if (!$client)
					{
						$error = 'api_error.unauthorized';
						$code = 401;
						return false;
					}

					return $this->repository(UserRepository::class)->getGuestUser();
				case 'Bearer':
					/** @var OAuthToken $token */
					$token = $this->finder(OAuthToken::class)
						->where('token', $tokenOrSecret)
						->fetchOne();

					if (!$token || !$token->isValid())
					{
						$error = 'api_error.unauthorized';
						$code = 401;
						return false;
					}

					if ($token->last_use_date < \XF::$time - 900)
					{
						$token->fastUpdate('last_use_date', \XF::$time);
					}

					\XF::setAccessToken($token);
					return $this->em()->find(User::class, $token->user_id);
			}
		}

		if (!$apiKeyValue)
		{
			// If no API key is presented, then don't immediately quit as we want the option to be able to
			// support unauthenticated controllers/actions. This will get picked up in preDispatch.
			$this->apiKeyOmitted = true;
			return $this->repository(UserRepository::class)->getGuestUser();
		}

		/** @var ApiRepository $apiRepo */
		$apiRepo = $this->repository(ApiRepository::class);
		$apiKey = $apiRepo->findApiKeyByKey($apiKeyValue);

		if (!$apiKey || $apiKeyValue !== $apiKey->api_key)
		{
			$error = 'api_error.api_key_not_found';
			$code = 401;
			return false;
		}

		if (!$apiKey->active)
		{
			$error = 'api_error.api_key_inactive';
			$code = 403;
			return false;
		}

		/** @var UserRepository $userRepo */
		$userRepo = $this->repository(UserRepository::class);

		if ($apiKey->is_super_user)
		{
			if ($apiUserId)
			{
				$visitor = $userRepo->getVisitor($apiUserId);

				if ($visitor->user_id != $apiUserId)
				{
					$error = 'api_error.user_id_not_valid';
					$code = 403;
					return false;
				}
			}
			else
			{
				$visitor = $this->repository(UserRepository::class)->getGuestUser();
			}
		}
		else
		{
			if ($apiUserId && $apiUserId !== $apiKey->user_id)
			{
				$error = 'api_error.user_id_not_allowed';
				$code = 403;
				return false;
			}

			$visitor = $userRepo->getVisitor($apiKey->user_id);

			if ($visitor->user_id != $apiKey->user_id)
			{
				$error = 'api_error.user_id_not_valid';
				$code = 403;
				return false;
			}
		}

		// only update this every 15 minutes as it should roughly be close enough and this avoids
		// writes on every page
		if ($apiKey->last_use_date < \XF::$time - 900)
		{
			$apiKey->fastUpdate('last_use_date', \XF::$time);
		}

		\XF::setApiKey($apiKey);

		return $visitor;
	}

	public function complete(Response $response)
	{
		parent::complete($response);

		$this->fire('app_api_complete', [$this, &$response]);
	}

	public function preRender(AbstractReply $reply, $responseType)
	{

	}

	protected function renderPageHtml($content, array $params, AbstractReply $reply, AbstractRenderer $renderer)
	{
		return $content;
	}

	/**
	 * @var bool
	 */
	protected $templaterInitialized = false;

	/**
	 * @return Templater
	 */
	public function templater()
	{
		$templater = parent::templater();

		if (!$this->templaterInitialized)
		{
			$this->templaterInitialized = true;
			$templater->addDefaultParam('xf', $this->getGlobalTemplateData());
		}

		return $templater;
	}
}
