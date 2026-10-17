<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Criteria\PageCriteria;
use XF\Criteria\UserCriteria;
use XF\Entity\Notice;
use XF\Entity\Option;
use XF\Finder\NoticeFinder;
use XF\Mvc\ParameterBag;
use XF\Repository\NoticeRepository;

class NoticeController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('notice');
	}

	public function actionIndex(ParameterBag $params)
	{
		if ($params['notice_id'])
		{
			$notice = $this->assertNoticeExists($params['notice_id']);
			return $this->redirect($this->buildLink('notices/edit', $notice));
		}

		$options = $this->em()->find(Option::class, 'enableNotices');

		$noticeRepo = $this->getNoticeRepo();
		$noticeList = $noticeRepo->findNoticesForList()->fetch();
		$notices = $noticeList->groupBy('notice_type');

		$invalidNotices = $noticeRepo->getInvalidNotices($noticeList);

		$viewParams = [
			'notices' => $notices,
			'invalidNotices' => $invalidNotices,
			'noticeTypes' => $noticeRepo->getNoticeTypes(),
			'options' => [$options],
			'totalNotices' => $noticeRepo->getTotalGroupedNotices($notices),
		];
		return $this->view('XF:Notice\Listing', 'notice_list', $viewParams);
	}

	protected function noticeAddEdit(Notice $notice)
	{
		$userCriteria = $this->app->criteria(UserCriteria::class, $notice->user_criteria);
		$pageCriteria = $this->app->criteria(PageCriteria::class, $notice->page_criteria);

		$viewParams = [
			'notice' => $notice,
			'noticeTypes' => $this->getNoticeRepo()->getNoticeTypes(),
			'userCriteria' => $userCriteria,
			'pageCriteria' => $pageCriteria,
		];
		return $this->view('XF:Notice\Edit', 'notice_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$notice = $this->assertNoticeExists($params['notice_id']);
		return $this->noticeAddEdit($notice);
	}

	public function actionAdd()
	{
		$notice = $this->em()->create(Notice::class);
		return $this->noticeAddEdit($notice);
	}

	protected function noticeSaveProcess(Notice $notice)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'title' => 'str',
			'message' => 'str',
			'dismissible' => 'bool',
			'active' => 'bool',
			'display_order' => 'uint',
			'display_image' => 'str',
			'image_url' => 'str',
			'visibility' => 'str',
			'notice_type' => 'str',
			'display_style' => 'str',
			'css_class' => 'str',
			'display_duration' => 'uint',
			'delay_duration' => 'uint',
			'auto_dismiss' => 'bool',
			'user_criteria' => 'array',
			'page_criteria' => 'array',
		]);

		$form->basicEntitySave($notice, $input);

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->notice_id)
		{
			$notice = $this->assertNoticeExists($params->notice_id);
		}
		else
		{
			$notice = $this->em()->create(Notice::class);
		}

		$this->noticeSaveProcess($notice)->run();

		return $this->redirect($this->buildLink('notices') . $this->buildLinkHash($notice->notice_id));
	}

	public function actionDelete(ParameterBag $params)
	{
		$notice = $this->assertNoticeExists($params->notice_id);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$notice,
			$this->buildLink('notices/delete', $notice),
			$this->buildLink('notices/edit', $notice),
			$this->buildLink('notices'),
			$notice->title
		);
	}

	public function actionReset(ParameterBag $params)
	{
		$notice = $this->assertNoticeExists($params['notice_id']);

		if ($this->isPost())
		{
			$this->getNoticeRepo()->resetNoticeDismissal($notice);
			return $this->redirect($this->buildLink('notices'));
		}
		else
		{
			$viewParams = [
				'notice' => $notice,
			];
			return $this->view('XF:Notice\Reset', 'notice_reset', $viewParams);
		}
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(NoticeFinder::class);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return Notice
	 */
	protected function assertNoticeExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(Notice::class, $id, $with, $phraseKey);
	}

	/**
	 * @return NoticeRepository
	 */
	protected function getNoticeRepo()
	{
		return $this->repository(NoticeRepository::class);
	}
}
