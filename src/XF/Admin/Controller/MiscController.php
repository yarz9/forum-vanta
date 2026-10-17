<?php

namespace XF\Admin\Controller;

use XF\Admin\ControllerPlugin\EmailOAuthPlugin;
use XF\ConnectedAccount\Service\MicrosoftEmailService;
use XF\Mvc\ParameterBag;

class MiscController extends AbstractController
{
	public function actionEmailOAuthSetup(ParameterBag $params)
	{
		/** @var EmailOAuthPlugin $oAuthPlugin */
		$oAuthPlugin = $this->plugin(EmailOAuthPlugin::class);

		$oAuthEmailSetup = $oAuthPlugin->assertOAuthEmailSetupData(false);

		$provider = $this->app->oAuth()->provider($oAuthEmailSetup['provider'], $oAuthEmailSetup['config']);
		if (method_exists($provider, 'setAccessType'))
		{
			$provider->setAccessType('offline');
		}

		$code = $this->filter('code', 'str');

		try
		{
			$token = $provider->requestAccessToken($code);
		}
		catch (\Exception $e)
		{
			\XF::logException($e);
			return $this->error(\XF::phrase('something_went_wrong_please_try_again'));
		}

		$oAuthEmailSetup['tokenData'] = [
			'token' => $token->getAccessToken(),
			'token_expiry' => $token->getEndOfLife(),
			'refresh_token' => $token->getRefreshToken(),
		];

		if ($provider instanceof MicrosoftEmailService)
		{
			$jwt = $token->getExtraParams()['id_token'];
			[$headers, $payload, $sig] = explode('.', $jwt);
			$payload = json_decode(base64_decode($payload), true);

			$oAuthEmailSetup['loginUserName'] = $payload['email'];
		}
		else
		{
			$oAuthEmailSetup['loginUserName'] = $oAuthPlugin->getLoginUserNameFromProvider(
				$oAuthEmailSetup['provider'],
				$provider
			);
		}

		$this->session()->oAuthEmailSetup = $oAuthEmailSetup;

		return $this->redirect($oAuthEmailSetup['returnUrl']);
	}
}
