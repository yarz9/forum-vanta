<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\CaptchaQuestion;
use XF\Finder\CaptchaQuestionFinder;
use XF\Mvc\ParameterBag;
use XF\Repository\CaptchaQuestionRepository;

class CaptchaQuestionController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('option');
	}

	public function actionIndex()
	{
		$questionRepo = $this->getCaptchaQuestionRepo();
		$questions = $questionRepo->findCaptchaQuestionsForList()->fetch();

		$viewParams = [
			'questions' => $questions,
		];
		return $this->view('XF:CaptchaQuestion\Listing', 'captcha_question_list', $viewParams);
	}

	protected function questionAddEdit(CaptchaQuestion $question)
	{
		$viewParams = [
			'question' => $question,
		];
		return $this->view('XF:CaptchaQuestion\Edit', 'captcha_question_edit', $viewParams);
	}

	public function actionAdd()
	{
		$question = $this->em()->create(CaptchaQuestion::class);
		return $this->questionAddEdit($question);
	}

	public function actionEdit(ParameterBag $params)
	{
		$question = $this->assertCaptchaQuestionExists($params['captcha_question_id']);
		return $this->questionAddEdit($question);
	}

	protected function questionSaveProcess(CaptchaQuestion $question)
	{
		$input = $this->filter([
			'question' => 'str',
			'answers' => 'array-str',
			'active' => 'bool',
		]);

		$form = $this->formAction();
		$form->basicEntitySave($question, $input);

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params['captcha_question_id'])
		{
			$question = $this->assertCaptchaQuestionExists($params['captcha_question_id']);
		}
		else
		{
			$question = $this->em()->create(CaptchaQuestion::class);
		}

		$form = $this->questionSaveProcess($question);
		$form->run();

		return $this->redirect($this->buildLink('captcha-questions') . $this->buildLinkHash($question->captcha_question_id));
	}

	public function actionDelete(ParameterBag $params)
	{
		$question = $this->assertCaptchaQuestionExists($params['captcha_question_id']);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$question,
			$this->buildLink('captcha-questions/delete', $question),
			$this->buildLink('captcha-questions/edit', $question),
			$this->buildLink('captcha-questions'),
			$question->question
		);
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(CaptchaQuestionFinder::class);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return CaptchaQuestion
	 */
	protected function assertCaptchaQuestionExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(CaptchaQuestion::class, $id, $with, $phraseKey);
	}

	/**
	 * @return CaptchaQuestionRepository
	 */
	protected function getCaptchaQuestionRepo()
	{
		return $this->repository(CaptchaQuestionRepository::class);
	}
}
