<?php

namespace XF\PreRegAction\Thread;

use XF\Entity\Post;
use XF\Entity\PreRegAction;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\PreRegAction\AbstractHandler;
use XF\Repository\ThreadWatchRepository;
use XF\Repository\UserAlertRepository;
use XF\Service\Thread\ReplierService;

class Reply extends AbstractHandler
{
	public function getContainerContentType(): string
	{
		return 'thread';
	}

	public function getDefaultActionData(): array
	{
		return [
			'message' => '',
		];
	}

	protected function canCompleteAction(PreRegAction $action, Entity $containerContent, User $newUser): bool
	{
		/** @var Thread $containerContent */
		return $containerContent->canReply()
			&& !$this->isFlooding('post', $newUser);
	}

	protected function executeAction(PreRegAction $action, Entity $containerContent, User $newUser)
	{
		/** @var Thread $containerContent */
		$replier = $this->setupThreadReply($action, $containerContent);
		$replier->checkForSpam();

		if (!$replier->validate())
		{
			return null;
		}

		$post = $replier->save();

		\XF::repository(ThreadWatchRepository::class)->autoWatchThread($containerContent, $newUser, false);

		$replier->sendNotifications();

		return $post;
	}

	protected function setupThreadReply(
		PreRegAction $action,
		Thread $thread
	): ReplierService
	{
		/** @var ReplierService $replier */
		$replier = \XF::app()->service(ReplierService::class, $thread);
		$replier->setMessage($action->action_data['message']);
		$replier->logIp($action->ip_address);

		return $replier;
	}

	protected function sendSuccessAlert(
		PreRegAction $action,
		Entity $containerContent,
		User $newUser,
		Entity $executeContent
	)
	{
		if (!($executeContent instanceof Post))
		{
			return;
		}

		/** @var Post $post */
		$post = $executeContent;

		/** @var UserAlertRepository $alertRepo */
		$alertRepo = \XF::repository(UserAlertRepository::class);

		$alertRepo->alertFromUser(
			$newUser,
			null,
			'post',
			$post->post_id,
			'pre_reg',
			['welcome' => $action->isForNewUser()],
			['autoRead' => false]
		);
	}

	protected function getStructuredContentData(PreRegAction $preRegAction, Entity $containerContent): array
	{
		/** @var Thread $containerContent */
		return [
			'title' => \XF::phrase('post_in_thread_x', ['title' => $containerContent->title]),
			'title_link' => $containerContent->getContentUrl(),
			'bb_code' => $preRegAction->action_data['message'],
		];
	}
}
