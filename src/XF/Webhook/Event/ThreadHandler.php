<?php

namespace XF\Webhook\Event;

use XF\Mvc\Entity\Entity;
use XF\Repository\NodeRepository;
use XF\Repository\ThreadTypeRepository;
use XF\Webhook\Criteria\Thread;

class ThreadHandler extends AbstractHandler
{
	public function getCriteriaClass(): string
	{
		return Thread::class;
	}

	public function getCriteriaTemplateName(): ?string
	{
		return 'admin:webhook_criteria_thread';
	}

	public function getCriteriaTemplateParams(Entity $webhook): array
	{
		$options = parent::getCriteriaTemplateParams($webhook);

		$nodeRepo = \XF::repository(NodeRepository::class);
		$forums = $nodeRepo->getNodeOptionsData(false, 'Forum');

		$threadTypeRepo = \XF::repository(ThreadTypeRepository::class);
		$threadTypes = $threadTypeRepo->getThreadTypeListData();

		return array_merge($options, [
			'forums' => $forums,
			'threadTypes' => $threadTypes,
		]);
	}

	public function getEntityWith(): array
	{
		return ['Forum'];
	}
}
