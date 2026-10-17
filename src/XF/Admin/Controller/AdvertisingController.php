<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\DescLoaderPlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\Advertising;
use XF\Entity\AdvertisingPosition;
use XF\Entity\Option;
use XF\Finder\AdvertisingFinder;
use XF\Finder\AdvertisingPositionFinder;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Repository\AdvertisingRepository;
use XF\Repository\UserGroupRepository;

use function count;

class AdvertisingController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		if (preg_match('/^(position)/i', $action))
		{
			$this->assertDevelopmentMode();
		}
		else
		{
			$this->assertAdminPermission('advertising');
		}
	}

	public function actionIndex()
	{
		$advertisingRepo = $this->getAdvertisingRepo();

		$options = $this->em()->find(Option::class, 'adsDisallowedTemplates');

		$adsFinder = $advertisingRepo->findAdsForList();
		$ads = $adsFinder->fetch()->groupBy('position_id');

		$positionsFinder = $advertisingRepo->findAdvertisingPositionsForList();
		$positions = $positionsFinder->fetch();

		$viewParams = [
			'ads' => $ads,
			'options' => [$options],
			'positions' => $positions,
			'totalAds' => $advertisingRepo->getTotalGroupedAds($ads),
		];
		return $this->view('XF:Advertising\Listing', 'advertising_list', $viewParams);
	}

	protected function advertisingAddEdit(Advertising $ad)
	{
		$advertisingRepo = $this->getAdvertisingRepo();
		$advertisingPositions = $advertisingRepo
			->findAdvertisingPositionsForList(true)
			->fetch()
			->pluckNamed('title', 'position_id');

		/** @var UserGroupRepository $userGroupRepo */
		$userGroupRepo = $this->app->repository(UserGroupRepository::class);
		$userGroups = $userGroupRepo->getUserGroupTitlePairs();

		$viewParams = [
			'ad' => $ad,
			'advertisingPositions' => $advertisingPositions,
			'userGroups' => $userGroups,
		];
		return $this->view('XF:Advertising\Edit', 'advertising_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$ad = $this->assertAdExists($params->ad_id);
		return $this->advertisingAddEdit($ad);
	}

	public function actionAdd()
	{
		$ad = $this->em()->create(Advertising::class);
		return $this->advertisingAddEdit($ad);
	}

	protected function adSaveProcess(Advertising $ad)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'title' => 'str',
			'position_id' => 'str',
			'ad_html' => 'str',
			'display_criteria' => 'array',
			'display_order' => 'uint',
			'active' => 'bool',
		]);

		$form->basicEntitySave($ad, $input);

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->ad_id)
		{
			$ad = $this->assertAdExists($params->ad_id);
		}
		else
		{
			$ad = $this->em()->create(Advertising::class);
		}

		$this->adSaveProcess($ad)->run();

		return $this->redirect($this->buildLink('advertising') . $this->buildLinkHash($ad->ad_id));
	}

	public function actionDelete(ParameterBag $params)
	{
		$ad = $this->assertAdExists($params->ad_id);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$ad,
			$this->buildLink('advertising/delete', $ad),
			$this->buildLink('advertising/edit', $ad),
			$this->buildLink('advertising'),
			$ad->title
		);
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(AdvertisingFinder::class);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return Advertising
	 */
	protected function assertAdExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(Advertising::class, $id, $with, $phraseKey);
	}

	public function actionPosition()
	{
		$advertisingRepo = $this->getAdvertisingRepo();
		$advertisingPositionsFinder = $advertisingRepo->findAdvertisingPositionsForList();

		$viewParams = [
			'advertisingPositions' => $advertisingPositionsFinder->fetch(),
		];
		return $this->view('XF:Advertising\Position\Listing', 'advertising_position_list', $viewParams);
	}

	protected function positionAddEdit(AdvertisingPosition $advertisingPosition)
	{
		$viewParams = [
			'advertisingPosition' => $advertisingPosition,
			'nextCounter' => count($advertisingPosition->arguments),
		];
		return $this->view('XF:Advertising\Position\Edit', 'advertising_position_edit', $viewParams);
	}

	public function actionPositionEdit(ParameterBag $params)
	{
		$advertisingPosition = $this->assertAdvertisingPositionExists($params->position_id);
		return $this->positionAddEdit($advertisingPosition);
	}

	public function actionPositionAdd()
	{
		$advertisingPosition = $this->em()->create(AdvertisingPosition::class);
		return $this->positionAddEdit($advertisingPosition);
	}

	protected function advertisingPositionSaveProcess(AdvertisingPosition $advertisingPosition)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'position_id' => 'str',
			'active' => 'bool',
			'addon_id' => 'str',
		]);

		$input['arguments'] = [];
		$args = $this->filter('arguments', 'array');
		foreach ($args AS $arg)
		{
			if (!$arg['argument'])
			{
				continue;
			}
			$input['arguments'][] = $this->filterArray($arg, [
				'argument' => 'str',
				'required' => 'bool',
			]);
		}

		$form->basicEntitySave($advertisingPosition, $input);

		$extraInput = $this->filter([
			'title' => 'str',
			'description' => 'str',
		]);
		$form->validate(function (FormAction $form) use ($extraInput)
		{
			if ($extraInput['title'] === '')
			{
				$form->logError(\XF::phrase('please_enter_valid_title'), 'title');
			}
		});
		$form->apply(function () use ($extraInput, $advertisingPosition)
		{
			$title = $advertisingPosition->getMasterTitlePhrase();
			$title->phrase_text = $extraInput['title'];
			$title->save();

			$description = $advertisingPosition->getMasterDescriptionPhrase();
			$description->phrase_text = $extraInput['description'];
			$description->save();
		});

		return $form;
	}

	public function actionPositionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->position_id)
		{
			$advertisingPosition = $this->assertAdvertisingPositionExists($params->position_id);
		}
		else
		{
			$advertisingPosition = $this->em()->create(AdvertisingPosition::class);
		}

		$this->advertisingPositionSaveProcess($advertisingPosition)->run();

		return $this->redirect($this->buildLink('advertising/positions') . $this->buildLinkHash($advertisingPosition->position_id));
	}

	public function actionPositionDelete(ParameterBag $params)
	{
		$advertisingPosition = $this->assertAdvertisingPositionExists($params->position_id);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$advertisingPosition,
			$this->buildLink('advertising/positions/delete', $advertisingPosition),
			$this->buildLink('advertising/positions/edit', $advertisingPosition),
			$this->buildLink('advertising/positions'),
			$advertisingPosition->title
		);
	}

	public function actionPositionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(AdvertisingPositionFinder::class);
	}

	public function actionGetPositionDescription()
	{
		/** @var DescLoaderPlugin $plugin */
		$plugin = $this->plugin(DescLoaderPlugin::class);
		return $plugin->actionLoadDescription(AdvertisingPosition::class);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return AdvertisingPosition
	 */
	protected function assertAdvertisingPositionExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(AdvertisingPosition::class, $id, $with, $phraseKey);
	}

	/**
	 * @return AdvertisingRepository
	 */
	protected function getAdvertisingRepo()
	{
		return $this->repository(AdvertisingRepository::class);
	}
}
