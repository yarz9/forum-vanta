<?php

namespace XF\Pub\Controller;

use XF\Entity\ApprovalQueue;
use XF\Job\ApprovalQueueProcess;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Repository\ApprovalQueueRepository;

use function in_array;

class ApprovalQueueController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		if (!\XF::visitor()->is_moderator)
		{
			throw $this->exception($this->noPermission());
		}
	}

	public function actionIndex()
	{
		$approvalQueueRepo = $this->getApprovalQueueRepo();

		$unapprovedFinder = $approvalQueueRepo->findUnapprovedContent();

		$filters = $this->getQueueFilterInput();
		$this->applyQueueFilters($unapprovedFinder, $filters);

		/** @var ApprovalQueue[]|ArrayCollection $unapprovedItems */
		$unapprovedItems = $unapprovedFinder->fetch();

		if ($unapprovedItems->count() != $this->app->unapprovedCounts['total'])
		{
			$approvalQueueRepo->rebuildUnapprovedCounts();
		}

		$approvalQueueRepo->addContentToUnapprovedItems($unapprovedItems);
		$approvalQueueRepo->cleanUpInvalidRecords($unapprovedItems);
		$unapprovedItems = $approvalQueueRepo->filterViewableUnapprovedItems($unapprovedItems);

		$viewParams = [
			'filters' => $filters,
			'unapprovedItems' => $unapprovedItems->slice(0, 50),
		];
		return $this->view('XF:ApprovalQueue\Listing', 'approval_queue', $viewParams);
	}

	public function actionFilters(ParameterBag $params)
	{
		$filters = $this->getQueueFilterInput();

		if ($this->filter('apply', 'bool'))
		{
			return $this->redirect($this->buildLink('approval-queue', null, $filters));
		}

		$contentTypes = $this->getApprovalQueueRepo()->getContentTypesFromCurrentQueue();

		$viewParams = [
			'filters' => $filters,
			'contentTypes' => $contentTypes,
		];
		return $this->view('XF:ApprovalQueue\Filters', 'approval_queue_filters', $viewParams);
	}

	protected function getQueueFilterInput()
	{
		$filters = [];

		$input = $this->filter([
			'content_type' => 'str',
			'order' => 'str',
			'direction' => 'str',
		]);

		if ($input['content_type'])
		{
			$filters['content_type'] = $input['content_type'];
		}

		$sorts = $this->getAvailableQueueSorts();

		if ($input['order'] && isset($sorts[$input['order']]))
		{
			if (!in_array($input['direction'], ['asc', 'desc']))
			{
				$input['direction'] = 'asc';
			}

			if ($input['order'] != 'content_date' || $input['direction'] != 'asc')
			{
				$filters['order'] = $input['order'];
				$filters['direction'] = $input['direction'];
			}
		}

		return $filters;
	}

	protected function getAvailableQueueSorts()
	{
		// maps [name of sort] => field in/relative to ApprovalQueue entity
		return [
			'content_date' => 'content_date',
		];
	}

	protected function applyQueueFilters(Finder $finder, array $filters)
	{
		if (!empty($filters['content_type']))
		{
			$finder->where('content_type', $filters['content_type']);
		}

		$sorts = $this->getAvailableQueueSorts();

		if (!empty($filters['order']) && isset($sorts[$filters['order']]))
		{
			$finder->order($sorts[$filters['order']], $filters['direction']);
		}
		// else the default order has already been applied
	}

	public function actionProcess()
	{
		$this->assertPostOnly();

		$approvalQueueRepo = $this->getApprovalQueueRepo();

		$queue = $this->filter('queue', 'array');
		$queue = $approvalQueueRepo->filterQueue($queue);

		if ($queue)
		{
			$jobManager = $this->app->jobManager();
			$jobManager->enqueueAutoBlocking(ApprovalQueueProcess::class, [
				'asUserId' => \XF::visitor()->user_id,
				'queue' => $queue,
				'rawInput' => $this->request->getInput(),
			]);
			$jobManager->setAutoBlockingMessage(\XF::phrase('processing_approval_queue...'));
		}

		return $this->redirect($this->buildLink('approval-queue'));
	}

	/**
	 * @return ApprovalQueueRepository
	 */
	protected function getApprovalQueueRepo()
	{
		return $this->repository(ApprovalQueueRepository::class);
	}

	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('performing_moderation_duties');
	}
}
