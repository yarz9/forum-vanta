<?php

namespace XF\Attachment;

use XF\Entity\Attachment;
use XF\Entity\ProfilePost;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Repository\AttachmentRepository;

use function intval;

class ProfilePostHandler extends AbstractHandler
{
	public function getContainerWith()
	{
		return ['ProfileUser', 'User'];
	}

	public function canView(Attachment $attachment, Entity $container, &$error = null)
	{
		/** @var ProfilePost $container */
		if (!$container->canView())
		{
			return false;
		}

		return $container->canViewAttachments($error);
	}

	public function canManageAttachments(array $context, &$error = null)
	{
		$user = $this->getUserFromContext($context);
		return ($user && $user->canUploadAndManageAttachmentsOnProfile());
	}

	public function onAttachmentDelete(Attachment $attachment, ?Entity $container = null)
	{
		if (!$container)
		{
			return;
		}

		/** @var ProfilePost $container */
		$container->attach_count--;
		$container->save();

		// TODO: phrase for attachment_deleted
		\XF::app()->logger()->logModeratorAction($this->contentType, $container, 'attachment_deleted', [], false);
	}

	public function getConstraints(array $context)
	{
		/** @var AttachmentRepository $attachRepo */
		$attachRepo = \XF::repository(AttachmentRepository::class);

		$constraints = $attachRepo->getDefaultAttachmentConstraints();

		$user = $this->getUserFromContext($context);
		if ($user && $user->canUploadVideosOnProfile())
		{
			$constraints = $attachRepo->applyVideoAttachmentConstraints($constraints);
		}

		return $constraints;
	}

	public function getContainerIdFromContext(array $context)
	{
		return isset($context['profile_post_id']) ? intval($context['profile_post_id']) : null;
	}

	public function getContext(?Entity $entity = null, array $extraContext = [])
	{
		if ($entity instanceof ProfilePost)
		{
			$extraContext['profile_post_id'] = $entity->profile_post_id;
		}
		else if ($entity instanceof User)
		{
			$extraContext['profile_user_id'] = $entity->user_id;
		}
		else
		{
			throw new \InvalidArgumentException("Entity must be profile post or user");
		}

		return $extraContext;
	}

	protected function getUserFromContext(array $context)
	{
		$em = \XF::em();

		if (!empty($context['profile_post_id']))
		{
			/** @var ProfilePost $profilePost */
			$profilePost = $em->find(ProfilePost::class, intval($context['profile_post_id']), ['ProfileUser']);
			if (!$profilePost || !$profilePost->canView() || !$profilePost->canEdit())
			{
				return null;
			}

			$user = $profilePost->ProfileUser;
		}
		else if (!empty($context['profile_user_id']))
		{
			/** @var User $user */
			$user = $em->find(User::class, intval($context['profile_user_id']));
			if (!$user)
			{
				return null;
			}
		}

		return $user ?? null;
	}
}
