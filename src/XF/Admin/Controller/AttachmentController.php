<?php

namespace XF\Admin\Controller;

use XF\Entity\OptionGroup;
use XF\Filterer\Attachment;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Repository\AttachmentRepository;

use function count;

class AttachmentController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('attachment');
	}

	public function actionIndex(ParameterBag $params)
	{
		if ($params->attachment_id)
		{
			return $this->rerouteController(self::class, 'view', $params);
		}

		if ($this->request->exists('delete_attachments'))
		{
			return $this->rerouteController(self::class, 'delete');
		}

		$attachmentRepo = $this->getAttachmentRepo();

		$page = $this->filterPage();
		$perPage = 20;

		$filterer = $this->setupAttachmentFilterer();
		$finder = $filterer->apply()->limitByPage($page, $perPage);

		$linkParams = $filterer->getLinkParams();

		if ($this->isPost())
		{
			return $this->redirect($this->buildLink('attachments', null, $linkParams), '');
		}

		$total = $finder->total();
		$this->assertValidPage($page, $perPage, $total, 'attachments');

		$viewParams = [
			'attachments' => $finder->fetch(),
			'handlers' => $attachmentRepo->getAttachmentHandlers(),

			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,

			'linkParams' => $linkParams,
			'filterDisplay' => $filterer->getDisplayValues(),
		];
		return $this->view('XF:Attachment\Listing', 'attachment_list', $viewParams);
	}

	public function actionFilter(): AbstractReply
	{
		$attachmentRepo = $this->getAttachmentRepo();
		$filterer = $this->setupAttachmentFilterer();

		$viewParams = [
			'handlers' => $attachmentRepo->getAttachmentHandlers(),
			'conditions' => $filterer->getFiltersForForm(),
			'datePresets' => \XF::language()->getDatePresets(),
		];
		return $this->view('XF:Attachment\Filter', 'attachment_filter', $viewParams);
	}

	protected function setupAttachmentFilterer(): Attachment
	{
		/** @var Attachment $filterer */
		$filterer = $this->app->filterer(Attachment::class);
		$filterer->addFilters($this->request, $this->filter('_skipFilter', 'str'));

		return $filterer;
	}

	public function actionDelete(ParameterBag $params)
	{
		$linkFilters = $this->filter([
			'content_type' => 'str',
			'username' => 'str',
			'start' => 'datetime',
			'end' => 'datetime',
		]);
		$linkFilters = array_filter($linkFilters); // filter empty values

		$attachmentIds = $this->filter('attachment_ids', 'array-uint');
		if ($attachmentId = $this->filter('attachment_id', 'uint', $params->attachment_id))
		{
			$attachmentIds[] = $attachmentId;
		}

		if (!$attachmentIds)
		{
			return $this->redirect($this->buildLink('attachments', null, $linkFilters));
		}

		if ($this->isPost() && !$this->request->exists('delete_attachments'))
		{
			foreach ($attachmentIds AS $attachmentId)
			{
				/** @var \XF\Entity\Attachment $attachment */
				$attachment = $this->em()->find(\XF\Entity\Attachment::class, $attachmentId);
				$attachment->delete(false);
			}

			return $this->redirect($this->buildLink('attachments', null, $linkFilters));
		}
		else
		{
			$viewParams = [
				'attachmentIds' => $attachmentIds,
				'linkFilters' => $linkFilters,
			];
			if (count($attachmentIds) == 1)
			{
				/** @var \XF\Entity\Attachment $attachment */
				$attachment = $this->em()->find(\XF\Entity\Attachment::class, reset($attachmentIds));
				if (!$attachment || !$attachment->Data || !$attachment->Data->isDataAvailable())
				{
					throw $this->exception($this->notFound());
				}
				$viewParams['attachment'] = $attachment;
			}
			return $this->view('XF:Attachment\Delete', 'attachment_delete', $viewParams);
		}
	}

	public function actionView(ParameterBag $params)
	{
		/** @var \XF\Entity\Attachment $attachment */
		$attachment = $this->em()->find(\XF\Entity\Attachment::class, $params->attachment_id);
		if (!$attachment)
		{
			throw $this->exception($this->notFound());
		}

		if (!$attachment->Data || !$attachment->Data->isDataAvailable())
		{
			return $this->error(\XF::phrase('attachment_cannot_be_shown_at_this_time'));
		}

		$this->setResponseType('raw');

		$eTag = $this->request->getServer('HTTP_IF_NONE_MATCH');
		$return304 = ($eTag && $eTag == '"' . $attachment['attach_date'] . '"');

		$viewParams = [
			'attachment' => $attachment,
			'return304' => $return304,
		];
		return $this->view('XF:Attachment\View', '', $viewParams);
	}

	public function actionOptions()
	{
		$group = $this->em()->find(OptionGroup::class, 'attachments');
		if ($group)
		{
			return $this->redirectPermanently($this->buildLink('options/groups', $group));
		}
		else
		{
			return $this->redirect($this->buildLink('options'));
		}
	}

	/**
	 * @return AttachmentRepository
	 */
	protected function getAttachmentRepo()
	{
		return $this->repository(AttachmentRepository::class);
	}
}
