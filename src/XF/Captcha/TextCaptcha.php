<?php

namespace XF\Captcha;

use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Utils;
use XF\Entity\CaptchaLog;
use XF\Template\Templater;

use function in_array;

class TextCaptcha extends AbstractCaptcha
{
	public function renderInternal(Templater $templater)
	{
		$extraKeys = $this->app->options()->extraCaptchaKeys;
		$apiKey = !empty($extraKeys['textCaptchaApiKey']) ? $extraKeys['textCaptchaApiKey'] : null;

		try
		{
			$client = $this->app->http()->client();
			$response = Utils::jsonDecode(
				$client->get("https://api.textcaptcha.com/$apiKey.json")->getBody()->getContents(),
				true
			);
		}
		catch (TransferException $e)
		{
			// this is an exception with the underlying request, so let it go through
			\XF::logException($e, false, 'Error fetching textCAPTCHA: ');
			$response = null;
		}
		if (!$response)
		{
			$response = [
				'q' => '',
				'a' => ['failed'],
			];
		}
		$response['hash'] = md5($response['q'] . uniqid(microtime(), true));

		$captchaLog = $this->app->em()->create(CaptchaLog::class);
		$captchaLog->bulkSet([
			'hash' => $response['hash'],
			'captcha_type' => 'TextCaptcha',
			'captcha_data' => implode(',', $response['a']),
			'captcha_date' => time(),
		]);
		$captchaLog->save();

		return $templater->renderTemplate('public:captcha_textcaptcha', [
			'question' => $response,
		]);
	}

	public function isValid()
	{
		$request = $this->app->request();

		$answer = $request->filter('captcha_question_answer', 'str');
		$hash = $request->filter('captcha_question_hash', 'str');

		$isCorrect = false;

		/** @var CaptchaLog $captchaLog */
		$captchaLog = $this->app->em()->find(CaptchaLog::class, $hash);
		if ($captchaLog)
		{
			if ($captchaLog->captcha_type == 'TextCaptcha')
			{
				if ($captchaLog->captcha_data == 'failed')
				{
					// request failed, we need to pass this every time
					$isCorrect = true;
				}
				else
				{
					$answerMd5 = md5(strtolower(trim($answer)));
					$correct = explode(',', $captchaLog->captcha_data);

					$isCorrect = in_array($answerMd5, $correct, true);
				}
			}

			$this->setResponse($captchaLog);

			$captchaLog->delete();
		}

		return $isCorrect;
	}
}
