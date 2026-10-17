<?php

namespace XF\Api\Controller;

use XF\Api\ControllerPlugin\ConversationPlugin;
use XF\Entity\ConversationMaster;
use XF\Entity\ConversationMessage;
use XF\Entity\ConversationUser;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Service\Conversation\ReplierService;

/**
 * @api-group Conversations
 */
class ConversationMessagesController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('conversation');
	}

	/**
	 * @api-desc Replies to a conversation
	 *
	 * @api-in <req> int $conversation_id
	 * @api-in <req> str $message
	 * @api-in str $attachment_key API attachment key to upload files. Attachment key content type must be conversation_message with context[conversation_id] set to this conversation ID.
	 *
	 * @api-out true $success
	 * @api-out ConversationMessage $message The newly inserted message
	 */
	public function actionPost(ParameterBag $params)
	{
		$this->assertRequiredApiInput(['conversation_id', 'message']);

		$conversationId = $this->filter('conversation_id', 'uint');

		/** @var ConversationUser $userConv */
		$userConv = $this->assertViewableUserConversation($conversationId);
		$conversation = $userConv->Master;

		if (\XF::isApiCheckingPermissions() && !$conversation->canReply())
		{
			return $this->noPermission();
		}

		$replier = $this->setupConversationReply($conversation);
		$replier->setAutoSpamCheck(false);

		if (\XF::isApiCheckingPermissions())
		{
			$replier->checkForSpam();
		}

		if (!$replier->validate($errors))
		{
			return $this->error($errors);
		}

		/** @var ConversationMessage $message */
		$message = $replier->save();
		$this->finalizeConversationReply($replier);

		return $this->apiSuccess([
			'message' => $message->toApiResult(Entity::VERBOSITY_VERBOSE),
		]);
	}

	/**
	 * @param ConversationMaster $conversation
	 *
	 * @return ReplierService
	 */
	protected function setupConversationReply(ConversationMaster $conversation)
	{
		/** @var ReplierService $replier */
		$replier = $this->service(ReplierService::class, $conversation, \XF::visitor());

		$message = $this->filter('message', 'str');
		$replier->setMessageContent($message);

		if (\XF::isApiBypassingPermissions() || $conversation->canUploadAndManageAttachments())
		{
			$attachmentKey = $this->filter('attachment_key', 'str');
			$hash = $this->getAttachmentTempHashFromKey(
				$attachmentKey,
				'conversation_message',
				['conversation_id' => $conversation->conversation_id]
			);
			$replier->setAttachmentHash($hash);
		}

		return $replier;
	}

	protected function finalizeConversationReply(ReplierService $replier)
	{
	}

	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return ConversationUser
	 *
	 * @throws Exception
	 */
	protected function assertViewableUserConversation($id, $with = 'api')
	{
		/** @var ConversationPlugin $conversationPlugin */
		$conversationPlugin = $this->plugin(ConversationPlugin::class);
		return $conversationPlugin->assertViewableUserConversation($id, $with);
	}
}
