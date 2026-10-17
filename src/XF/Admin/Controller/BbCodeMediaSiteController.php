<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\BbCodeMediaSite;
use XF\Finder\BbCodeMediaSiteFinder;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Repository\BbCodeMediaSiteRepository;

class BbCodeMediaSiteController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('bbCodeSmilie');
	}

	public function actionIndex()
	{
		$sites = $this->getBbCodeMediaSiteRepo()->findBbCodeMediaSitesForList()->fetch();

		$viewParams = [
			'sites' => $sites,
		];
		return $this->view('XF:BbCodeMediaSite\Listing', 'bb_code_media_site_list', $viewParams);
	}

	public function bbCodeMediaSiteAddEdit(BbCodeMediaSite $site)
	{
		$viewParams = [
			'site' => $site,
		];
		return $this->view('XF:BbCodeMediaSite\Edit', 'bb_code_media_site_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$site = $this->assertBbCodeMediaSiteExists($params['media_site_id']);
		return $this->bbCodeMediaSiteAddEdit($site);
	}

	public function actionAdd()
	{
		$site = $this->em()->create(BbCodeMediaSite::class);

		return $this->bbCodeMediaSiteAddEdit($site);
	}

	protected function bbCodeMediaSiteSaveProcess(BbCodeMediaSite $site)
	{
		$entityInput = $this->filter([
			'media_site_id' => 'str',
			'site_title' => 'str',
			'site_url' => 'str',
			'match_urls' => 'str',
			'match_is_regex' => 'bool',
			'match_callback_class' => 'str',
			'match_callback_method' => 'str',
			'embed_html_callback_class' => 'str',
			'embed_html_callback_method' => 'str',
			'cookie_third_parties' => 'str',
			'oembed_enabled' => 'bool',
			'oembed_api_endpoint' => 'str',
			'oembed_url_scheme' => 'str',
			'oembed_retain_scripts' => 'bool',
			'supported' => 'bool',
			'active' => 'bool',
			'addon_id' => 'str',
		]);

		$form = $this->formAction();
		$form->basicEntitySave($site, $entityInput);

		$embedHtml = $this->filter('embed_html', 'str');
		$template = $site->getMasterTemplate();

		$form->validate(function (FormAction $form) use ($embedHtml, $site, $template)
		{
			if ($site->exists()
				&& $template->exists()
				&& $embedHtml != $template->template
				&& !$site->canEdit()
			)
			{
				$form->logError('You cannot edit the embed template after it has been set.', 'embed_html');
			}

			if ($embedHtml != $template->template)
			{
				$site->setOption('requires_dev_output_update', true);
			}

			if ($embedHtml === '')
			{
				$form->logError(\XF::phrase('please_enter_embed_html'), 'embed_html');
			}
			else if (!$template->set('template', $embedHtml))
			{
				$form->logErrors($template->getErrors());
			}
			$site->addCascadedSave($template);
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params['media_site_id'])
		{
			$site = $this->assertBbCodeMediaSiteExists($params['media_site_id']);
		}
		else
		{
			$site = $this->em()->create(BbCodeMediaSite::class);
		}

		$this->bbCodeMediaSiteSaveProcess($site)->run();

		if ($this->request->exists('exit'))
		{
			$redirect = $this->buildLink('bb-code-media-sites') . $this->buildLinkHash($site->media_site_id);
		}
		else
		{
			$redirect = $this->buildLink('bb-code-media-sites/edit', $site);
		}

		return $this->redirect($redirect);
	}

	public function actionDelete(ParameterBag $params)
	{
		$site = $this->assertBbCodeMediaSiteExists($params['media_site_id']);
		if (!$site->canEdit())
		{
			return $this->error(\XF::phrase('item_cannot_be_deleted_associated_with_addon_explain'));
		}

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$site,
			$this->buildLink('bb-code-media-sites/delete', $site),
			$this->buildLink('bb-code-media-sites/edit', $site),
			$this->buildLink('bb-code-media-sites'),
			$site->site_title
		);
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(BbCodeMediaSiteFinder::class);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return BbCodeMediaSite
	 */
	protected function assertBbCodeMediaSiteExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(BbCodeMediaSite::class, $id, $with, $phraseKey);
	}

	/**
	 * @return BbCodeMediaSiteRepository
	 */
	protected function getBbCodeMediaSiteRepo()
	{
		return $this->repository(BbCodeMediaSiteRepository::class);
	}
}
