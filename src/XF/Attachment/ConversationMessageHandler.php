<?php

namespace XF\Attachment;

use XF\Entity\Attachment;
use XF\Entity\ConversationMaster;
use XF\Entity\ConversationMessage;
use XF\Mvc\Entity\Entity;
use XF\Repository\AttachmentRepository;

use function intval;

class ConversationMessageHandler extends AbstractHandler
{
	public function canView(Attachment $attachment, Entity $container, &$error = null)
	{
		/** @var ConversationMessage $container */
		if (!$container->canView())
		{
			return false;
		}

		/** @var ConversationMaster $conversation */
		$conversation = $container->Conversation;
		return $conversation->canView($error);
	}

	public function canManageAttachments(array $context, &$error = null)
	{
		$conversation = $this->getConversationFromContext($context);
		return ($conversation && $conversation->canUploadAndManageAttachments());
	}

	public function onAttachmentDelete(Attachment $attachment, ?Entity $container = null)
	{
		if (!$container)
		{
			return;
		}

		/** @var ConversationMessage $container */
		$container->attach_count--;
		$container->save();
	}

	public function getConstraints(array $context)
	{
		/** @var AttachmentRepository $attachRepo */
		$attachRepo = \XF::repository(AttachmentRepository::class);

		$constraints = $attachRepo->getDefaultAttachmentConstraints();

		$conversation = $this->getConversationFromContext($context);
		if ($conversation && $conversation->canUploadVideos())
		{
			$constraints = $attachRepo->applyVideoAttachmentConstraints($constraints);
		}

		return $constraints;
	}

	public function getContainerIdFromContext(array $context)
	{
		return isset($context['message_id']) ? intval($context['message_id']) : null;
	}

	public function getContext(?Entity $entity = null, array $extraContext = [])
	{
		if ($entity instanceof ConversationMessage)
		{
			$extraContext['message_id'] = $entity->message_id;
		}
		else if ($entity instanceof ConversationMaster)
		{
			$extraContext['conversation_id'] = $entity->conversation_id;
		}
		else if (!$entity)
		{
			// need nothing
		}
		else
		{
			throw new \InvalidArgumentException("Entity must be conversation or conversation message");
		}

		return $extraContext;
	}

	protected function getConversationFromContext(array $context)
	{
		$em = \XF::em();

		if (!empty($context['message_id']))
		{
			/** @var ConversationMessage $message */
			$message = $em->find(ConversationMessage::class, intval($context['message_id']), ['Conversation']);
			if (!$message || !$message->canView() || !$message->canEdit())
			{
				return null;
			}

			$conversation = $message->Conversation;
		}
		else if (!empty($context['conversation_id']))
		{
			/** @var ConversationMaster $conversation */
			$conversation = $em->find(ConversationMaster::class, intval($context['conversation_id']));
			if (!$conversation || !$conversation->canView())
			{
				return null;
			}
		}
		else
		{
			$conversation = $em->create(ConversationMaster::class);
		}

		return $conversation;
	}
}
