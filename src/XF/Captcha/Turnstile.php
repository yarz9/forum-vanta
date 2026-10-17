<?php

namespace XF\Captcha;

use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Utils;
use XF\App;
use XF\Template\Templater;

class Turnstile extends AbstractCaptcha
{
	/**
	 * Turnstile site key
	 *
	 * @var null|string
	 */
	protected $siteKey;

	/**
	 * Turnstile secret key
	 *
	 * @var null|string
	 */
	protected $secretKey;

	public function __construct(App $app)
	{
		parent::__construct($app);
		$extraKeys = $app->options()->extraCaptchaKeys;
		if (!empty($extraKeys['turnstileSiteKey']) && !empty($extraKeys['turnstileSecretKey']))
		{
			$this->siteKey = $extraKeys['turnstileSiteKey'];
			$this->secretKey = $extraKeys['turnstileSecretKey'];
		}
	}

	public function renderInternal(Templater $templater)
	{
		if (!$this->siteKey)
		{
			return '';
		}

		return $templater->renderTemplate('public:captcha_turnstile', [
			'siteKey' => $this->siteKey,
			'context' => $this->context,
		]);
	}

	public function isValid()
	{
		if (!$this->siteKey || !$this->secretKey)
		{
			return true; // if not configured, always pass
		}

		$request = $this->app->request();

		$captchaResponse = $request->filter('cf-turnstile-response', 'str');
		if (!$captchaResponse)
		{
			return false;
		}

		try
		{
			$client = $this->app->http()->client();

			$response = Utils::jsonDecode($client->post(
				'https://challenges.cloudflare.com/turnstile/v0/siteverify',
				[
					'form_params' => [
						'secret'   => $this->secretKey,
						'response' => $captchaResponse,
						'remoteip' => $request->getIp(),
					],
				]
			)->getBody()->getContents(), true);

			$this->setResponse($response);

			if (isset($response['success']) && isset($response['hostname']) && $response['hostname'] == $request->getHost())
			{
				return $response['success'];
			}

			return false;
		}
		catch (TransferException $e)
		{
			// this is an exception with the underlying request, so let it go through
			\XF::logException($e, false, 'Turnstile connection error: ');
			return true;
		}
	}
}
