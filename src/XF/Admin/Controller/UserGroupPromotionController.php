<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Criteria\UserCriteria;
use XF\Entity\UserGroupPromotion;
use XF\Finder\UserFinder;
use XF\Finder\UserGroupPromotionFinder;
use XF\Finder\UserGroupPromotionLogFinder;
use XF\Mvc\ParameterBag;
use XF\Repository\UserGroupPromotionRepository;
use XF\Repository\UserGroupRepository;

class UserGroupPromotionController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('userGroup');
	}

	public function actionIndex()
	{
		$viewParams = [
			'userGroupPromotions' => $this->getUserGroupPromotionRepo()->findUserGroupPromotionsForList()->fetch(),
		];
		return $this->view('XF:UserGroupPromotion\Listing', 'user_group_promotion_list', $viewParams);
	}

	public function actionManage()
	{
		$viewParams = [
			'userGroupPromotions' => $this->getUserGroupPromotionRepo()->getUserGroupPromotionTitlePairs(),
		];
		return $this->view('XF:UserGroupPromotion\Manage', 'user_group_promotion_manage', $viewParams);
	}

	public function actionHistory()
	{
		$input = $this->filter([
			'user_id' => 'uint',
			'username' => 'str',
			'promotion_id' => 'uint',
		]);

		$user = null;
		$promotion = null;

		$userFinder = $this->finder(UserFinder::class);
		if ($input['user_id'])
		{
			$userFinder->where('user_id', $input['user_id']);
		}
		if ($input['username'])
		{
			$userFinder->where('username', $input['username']);
		}
		if ($input['user_id'] || $input['username'])
		{
			$user = $userFinder->fetchOne();
			if (!$user)
			{
				return $this->error(\XF::phraseDeferred('requested_user_not_found'));
			}
		}

		if ($input['promotion_id'])
		{
			$promotion = $this->assertUserGroupPromotionExists($input['promotion_id']);
		}

		$linkParams = [];
		if ($promotion)
		{
			$linkParams['promotion_id'] = $promotion->promotion_id;
		}

		if ($user)
		{
			$linkParams['user_id'] = $user->user_id;
		}

		if ($this->isPost())
		{
			// redirect to a get request approach
			return $this->redirect($this->buildLink('user-group-promotions/history', null, $linkParams));
		}

		$page = $this->filterPage();
		$perPage = 20;

		$userGroupPromotionRepo = $this->getUserGroupPromotionRepo();

		$userGroupPromotionLogs = $userGroupPromotionRepo->findUserGroupPromotionLogsForList();
		if ($user)
		{
			$userGroupPromotionLogs->where('user_id', $user->user_id);
		}
		if ($promotion)
		{
			$userGroupPromotionLogs->where('promotion_id', $promotion->promotion_id);
		}
		$userGroupPromotionLogs->limitByPage($page, $perPage);

		$viewParams = [
			'entries' => $userGroupPromotionLogs->fetch(),
			'totalEntries' => $userGroupPromotionLogs->total(),

			'page' => $page,
			'perPage' => $perPage,
			'linkParams' => $linkParams,
		];
		return $this->view('XF:UserGroupPromotion\History', 'user_group_promotion_history', $viewParams);
	}

	public function actionManual()
	{
		$input = $this->filter([
			'promotion_id' => 'uint',
			'username' => 'str',
			'action' => 'str',
		]);

		$user = $this->finder(UserFinder::class)->where('username', $input['username'])->fetchOne();
		if (!$user)
		{
			return $this->error(\XF::phraseDeferred('requested_user_not_found'));
		}

		$promotion = $this->assertUserGroupPromotionExists($input['promotion_id']);

		if ($input['action'] == 'promote')
		{
			$promotion->promote($user, 'manual');
		}
		else
		{
			$promotion->demote($user, true);
		}
		return $this->redirect($this->buildLink('user-group-promotions/manage'));
	}

	public function actionDemote()
	{
		$input = $this->filter([
			'promotion_id' => 'uint',
			'user_id' => 'uint',
		]);

		$user = $this->finder(UserFinder::class)->where('user_id', $input['user_id'])->fetchOne();
		if (!$user)
		{
			return $this->error(\XF::phraseDeferred('requested_user_not_found'));
		}

		$promotion = $this->assertUserGroupPromotionExists($input['promotion_id']);

		$promotionLog = $this->finder(UserGroupPromotionLogFinder::class)
			->where('promotion_id', $promotion->promotion_id)
			->where('user_id', $user->user_id)->fetchOne();

		$isDemotion = (!$promotionLog || $promotionLog->promotion_state != 'disabled');
		$redirect = $this->getDynamicRedirect();

		if ($this->isPost())
		{
			if ($isDemotion)
			{
				// user has been given this promotion, so demote them and don't allow reapplication
				$promotion->demote($user, true);
			}
			else
			{
				// removing a disabled limit: "demote" but allow reapplication
				$promotion->demote($user, false);
			}

			return $this->redirect($redirect);
		}
		else
		{
			$viewParams = [
				'promotion' => $promotion,
				'user' => $user,
				'promotionLog' => $promotionLog,
				'isDemotion' => $isDemotion,
				'redirect' => $redirect,
			];
			return $this->view('XF:UserGroupPromotion', 'user_group_promotion_demote', $viewParams);
		}
	}

	protected function userGroupPromotionAddEdit(UserGroupPromotion $userGroupPromotion)
	{
		$userCriteria = $this->app->criteria(UserCriteria::class, $userGroupPromotion->user_criteria);

		$viewParams = [
			'userGroupPromotion' => $userGroupPromotion,
			'userCriteria' => $userCriteria,
			'userGroups' => $this->repository(UserGroupRepository::class)->getUserGroupTitlePairs(),
		];
		return $this->view('XF:UserGroupPromotion\Edit', 'user_group_promotion_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$userGroupPromotion = $this->assertUserGroupPromotionExists($params->promotion_id);
		return $this->userGroupPromotionAddEdit($userGroupPromotion);
	}

	public function actionAdd()
	{
		$userGroupPromotion = $this->em()->create(UserGroupPromotion::class);
		return $this->userGroupPromotionAddEdit($userGroupPromotion);
	}

	protected function userGroupPromotionSaveProcess(UserGroupPromotion $userGroupPromotion)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'title' => 'str',
			'active' => 'bool',
			'extra_user_group_ids' => 'array-uint',
			'user_criteria' => 'array',
		]);

		$form->basicEntitySave($userGroupPromotion, $input);

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->promotion_id)
		{
			$userGroupPromotion = $this->assertUserGroupPromotionExists($params->promotion_id);
		}
		else
		{
			$userGroupPromotion = $this->em()->create(UserGroupPromotion::class);
		}

		$this->userGroupPromotionSaveProcess($userGroupPromotion)->run();

		return $this->redirect($this->buildLink('user-group-promotions') . $this->buildLinkHash($userGroupPromotion->promotion_id));
	}

	public function actionDelete(ParameterBag $params)
	{
		$userGroupPromotion = $this->assertUserGroupPromotionExists($params->promotion_id);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$userGroupPromotion,
			$this->buildLink('user-group-promotions/delete', $userGroupPromotion),
			$this->buildLink('user-group-promotions/edit', $userGroupPromotion),
			$this->buildLink('user-group-promotions'),
			$userGroupPromotion->title
		);
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(UserGroupPromotionFinder::class);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return UserGroupPromotion
	 */
	protected function assertUserGroupPromotionExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(UserGroupPromotion::class, $id, $with, $phraseKey);
	}

	/**
	 * @return UserGroupPromotionRepository
	 */
	protected function getUserGroupPromotionRepo()
	{
		return $this->repository(UserGroupPromotionRepository::class);
	}
}
