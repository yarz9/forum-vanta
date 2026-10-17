<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\StylePlugin;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Repository\LanguageRepository;
use XF\Style;

class AccountController extends AbstractController
{
	public function actionIndex(ParameterBag $params)
	{
		return $this->redirect($this->buildLink('index'));
	}

	public function actionLanguage()
	{
		$visitor = \XF::visitor();
		if (!$visitor->canChangeLanguage($error))
		{
			return $this->noPermission($error);
		}

		$redirect = $this->getDynamicRedirect(null, true);

		if ($this->request->exists('language_id'))
		{
			$this->assertValidCsrfToken($this->filter('t', 'str'));

			$languageId = $this->filter('language_id', 'uint');

			$visitor->Admin->admin_language_id = $languageId;
			$visitor->Admin->save();

			return $this->redirect($redirect);
		}
		else
		{
			$viewParams = [
				'redirect' => $redirect,
				'languageTree' => $this->repository(LanguageRepository::class)->getLanguageTree(false),
			];
			return $this->view('XF:Account\Language', 'language_chooser', $viewParams);
		}
	}

	public function actionStyleVariation(): AbstractReply
	{
		/** @var Style $style */
		$style = $this->app->get('style.fallback');

		$redirect = $this->getDynamicRedirectIfNot(
			$this->buildLink('account/style-variation')
		);

		$saveCallback = function (string $variation): void
		{
			$visitor = \XF::visitor();
			$visitor->Admin->admin_style_variation = $variation;
			$visitor->Admin->save();
		};

		$stylePlugin = $this->plugin(StylePlugin::class);
		return $stylePlugin->actionStyleVariation(
			$style,
			$redirect,
			$saveCallback
		);
	}

	public function actionToggleAdvanced()
	{
		$this->assertPostOnly();

		$admin = \XF::visitor()->Admin;
		$admin->advanced = $this->filter('advanced', 'bool');
		$admin->save();

		// TODO: better flash message
		return $this->redirect($this->getDynamicRedirect());
	}
}
