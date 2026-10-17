<?php

namespace XF\Api\Controller;

use XF\Entity\ConversationMaster;
use XF\Entity\ConversationUser;
use XF\Entity\User;
use XF\Finder\ConversationUserFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Repository\ConversationRepository;
use XF\Service\Conversation\CreatorService;

use function intval;

/**
 * @api-group Conversations
 */
class ConversationsController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('conversation');
		$this->assertRegisteredUser();
	}

	/**
	 * @api-desc Gets the API user's list of conversations.
	 *
	 * @api-in int $page
	 * @api-in int $starter_id
	 * @api-in int $receiver_id
	 * @api-in bool $starred Only gets starred conversations if specified
	 * @api-in bool $unread Only gets unread conversations if specified
	 *
	 * @api-out Conversation[] $conversations
	 * @api-out pagination $pagination
	 */
	public function actionGet()
	{
		$page = $this->filterPage();
		$perPage = $this->options()->discussionsPerPage;

		$conversationFinder = $this->setupConversationFinder();
		$conversationFinder->limitByPage($page, $perPage);

		/** @var ConversationUser[]|AbstractCollection $conversations */
		$conversations = $conversationFinder->fetch();
		$totalConversations = $conversationFinder->total();

		$this->assertValidApiPage($page, $perPage, $totalConversations);

		$conversationsResults = $conversations->toApiResults();

		$return = [
			'conversations' => $conversationsResults,
			'pagination' => $this->getPaginationData($conversationsResults, $page, $perPage, $totalConversations),
		];
		return $this->apiResult($return);
	}

	/**
	 * @return ConversationUserFinder
	 */
	protected function setupConversationFinder()
	{
		$conversationFinder = $this->getConversationRepo()->findUserConversations(\XF::visitor())
			->with('api')
			->keyedBy('conversation_id');

		$starterId = $this->filter('starter_id', 'uint');
		if ($starterId)
		{
			$conversationFinder->where('Master.user_id', $starterId);
		}

		$receiverId = $this->filter('receiver_id', 'uint');
		if ($receiverId)
		{
			$conversationFinder->exists('Master.Recipients|' . intval($receiverId));
		}

		$starred = $this->filter('starred', 'bool');
		if ($starred)
		{
			$conversationFinder->where('is_starred', 1);
		}

		$unread = $this->filter('unread', 'bool');
		if ($unread)
		{
			$conversationFinder->where('is_unread', 1);
		}

		return $conversationFinder;
	}

	/**
	 * @api-desc Creates a conversation
	 *
	 * @api-in <req> int[] $recipient_ids List of user IDs to send the conversation to
	 * @api-in <req> str $title Conversation title
	 * @api-in <req> str $message Conversation message body
	 * @api-in str $attachment_key API attachment key to upload files. Attachment key content type must be conversation_message with no context.
	 * @api-in bool $conversation_open If false, no replies may be made to this conversation.
	 * @api-in bool $open_invite If true, any member of the conversation may add others
	 *
	 * @api-out true $success
	 * @api-out Conversation $conversation
	 */
	public function actionPost()
	{
		$this->assertRequiredApiInput(['title', 'message', 'recipient_ids']);

		$visitor = \XF::visitor();
		if (\XF::isApiCheckingPermissions() && !$visitor->canStartConversation())
		{
			return $this->noPermission();
		}

		$creator = $this->setupConversationCreate();
		$creator->setAutoSpamCheck(false);

		if (\XF::isApiCheckingPermissions())
		{
			$creator->checkForSpam();
		}

		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		/** @var ConversationMaster $conversation */
		$conversation = $creator->save();
		$this->finalizeConversationCreate($creator);

		$userConv = $conversation->Users[$visitor->user_id];

		return $this->apiSuccess([
			'conversation' => $userConv->toApiResult(Entity::VERBOSITY_VERBOSE),
		]);
	}

	/**
	 * @return CreatorService
	 */
	protected function setupConversationCreate()
	{
		$input = $this->filter([
			'title' => 'str',
			'message' => 'str',
			'attachment_key' => 'str',
			'recipient_ids' => 'array-uint',
			'conversation_open' => '?bool',
			'open_invite' => 'bool',
		]);

		$visitor = \XF::visitor();

		$recipients = $this->em()->findByIds(User::class, $input['recipient_ids']);

		/** @var CreatorService $creator */
		$creator = $this->service(CreatorService::class, $visitor);
		$creator->setOptions([
			'open_invite' => $input['open_invite'],
			'conversation_open' => $input['conversation_open'] ?? true,
		]);

		if (\XF::isApiBypassingPermissions())
		{
			$creator->overrideMaxAllowed(-1);
		}
		$creator->setRecipients($recipients, \XF::isApiCheckingPermissions());
		$creator->setContent($input['title'], $input['message']);

		$conversation = $creator->getConversation();

		if (\XF::isApiBypassingPermissions() || $conversation->canUploadAndManageAttachments())
		{
			$hash = $this->getAttachmentTempHashFromKey($input['attachment_key'], 'conversation_message', []);
			$creator->setAttachmentHash($hash);
		}

		return $creator;
	}

	protected function finalizeConversationCreate(CreatorService $creator)
	{
	}

	/**
	 * @return ConversationRepository
	 */
	protected function getConversationRepo()
	{
		return $this->repository(ConversationRepository::class);
	}
}
