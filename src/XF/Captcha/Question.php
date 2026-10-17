<?php

namespace XF\Captcha;

use XF\Entity\CaptchaLog;
use XF\Finder\CaptchaQuestionFinder;
use XF\Template\Templater;

class Question extends AbstractCaptcha
{
	public function renderInternal(Templater $templater)
	{
		$finder = $this->app->finder(CaptchaQuestionFinder::class);

		$question = $finder->where('active', 1)
			->order($finder->expression('RAND()'))
			->fetchOne();

		return $templater->renderTemplate('public:captcha_question', [
			'question' => $question,
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
			$this->setResponse($captchaLog);

			if ($captchaLog->Question)
			{
				$isCorrect = $captchaLog->Question->isCorrect($answer);
			}
			$captchaLog->delete();
		}

		return $isCorrect;
	}
}
