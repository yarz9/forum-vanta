<?php

namespace XF\Pub\Controller;

use XF\ControllerPlugin\BbCodePreviewPlugin;
use XF\ControllerPlugin\DraftPlugin;
use XF\ControllerPlugin\EditorPlugin;
use XF\ControllerPlugin\InlineModPlugin;
use XF\ControllerPlugin\IpPlugin;
use XF\ControllerPlugin\QuotePlugin;
use XF\ControllerPlugin\ReactionPlugin;
use XF\ControllerPlugin\ReportPlugin;
use XF\Draft;
use XF\Entity\ConversationMaster;
use XF\Entity\ConversationMessage;
use XF\Entity\ConversationUser;
use XF\Entity\User;
use XF\Finder\ConversationMessageFinder;
use XF\Finder\ConversationUserFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Repository\AttachmentRepository;
use XF\Repository\ConversationMessageRepository;
use XF\Repository\ConversationRepository;
use XF\Repository\EmbedResolverRepository;
use XF\Repository\UnfurlRepository;
use XF\Repository\UserAlertRepository;
use XF\Service\Conversation\CreatorService;
use XF\Service\Conversation\EditorService;
use XF\Service\Conversation\InviterService;
use XF\Service\Conversation\MessageEditorService;
use XF\Service\Conversation\ReplierService;

use function intval;

class ConversationController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertRegistrationRequired();
	}

	public function actionIndex(ParameterBag $params)
	{
		if ($params->conversation_id)
		{
			return $this->rerouteController(self::class, 'view', $params);
		}

		$this->assertNotEmbeddedImageRequest();

		$page = $this->filterPage($params->page);
		$perPage = $this->options()->discussionsPerPage;

		$this->assertCanonicalUrl($this->buildLink('direct-messages', null, ['page' => $page]));

		$visitor = \XF::visitor();
		$filters = $this->getConversationFilterInput();

		$conversationRepo = $this->getConversationRepo();

		$conversationFinder = $conversationRepo->findUserConversations($visitor)
			->limitByPage($page, $perPage);

		$this->applyConversationFilters($conversationFinder, $filters);

		$totalConversations = $conversationFinder->total();
		$this->assertValidPage($page, $perPage, $totalConversations, 'direct-messages');

		$userConvs = $conversationFinder->fetch();

		$starterFilter = !empty($filters['starter_id']) ? $this->em()->find(User::class, $filters['starter_id']) : null;
		$receiverFilter = !empty($filters['receiver_id']) ? $this->em()->find(User::class, $filters['receiver_id']) : null;

		$viewParams = [
			'userConvs' => $userConvs,

			'page' => $page,
			'perPage' => $perPage,
			'total' => $totalConversations,

			'starterFilter' => $starterFilter,
			'receiverFilter' => $receiverFilter,

			'filters' => $filters,
		];
		return $this->view('XF:Conversations\Listing', 'conversation_list', $viewParams);
	}

	protected function applyConversationFilters(ConversationUserFinder $finder, array $filters)
	{
		if (!empty($filters['starter_id']))
		{
			$finder->where('Master.user_id', intval($filters['starter_id']));
		}

		if (!empty($filters['receiver_id']))
		{
			$finder->exists('Master.Recipients|' . intval($filters['receiver_id']));
		}

		if (!empty($filters['starred']))
		{
			$finder->where('is_starred', 1);
		}

		if (!empty($filters['unread']))
		{
			$finder->where('is_unread', 1);
		}
	}

	protected function getConversationFilterInput()
	{
		$filters = [];

		$input = $this->filter([
			'starter_id' => 'uint',
			'receiver_id' => 'uint',
			'filter_type' => 'str',
			'starter' => 'str',
			'receiver' => 'str',
			'starred' => 'bool',
			'unread' => 'bool',
		]);

		if ($input['starter_id'])
		{
			$filters['starter_id'] = $input['starter_id'];
		}
		else if ($input['filter_type'] == 'started' && $input['starter'])
		{
			$user = $this->em()->findOne(User::class, ['username' => $input['starter']]);
			if ($user)
			{
				$filters['starter_id'] = $user->user_id;
			}
		}

		if ($input['receiver_id'])
		{
			$filters['receiver_id'] = $input['receiver_id'];
		}
		else if ($input['filter_type'] == 'received' && $input['receiver'])
		{
			$user = $this->em()->findOne(User::class, ['username' => $input['receiver']]);
			if ($user)
			{
				$filters['receiver_id'] = $user->user_id;
			}
		}

		if ($input['starred'])
		{
			$filters['starred'] = 1;
		}

		if ($input['unread'])
		{
			$filters['unread'] = 1;
		}

		return $filters;
	}

	public function actionFilters()
	{
		$filters = $this->getConversationFilterInput();

		return $this->redirect($this->buildLink('direct-messages', null, $filters));
	}

	public function actionPopup()
	{
		$this->assertNotEmbeddedImageRequest();

		$visitor = \XF::visitor();
		$conversationRepo = $this->getConversationRepo();
		$cutOff = \XF::$time - $this->options()->conversationPopupExpiryHours * 3600;

		$conversations = $conversationRepo->getUserConversationsForPopup($visitor, 10, $cutOff, ['Master.LastMessageUser']);

		$totalUnread = $conversationRepo->findUserConversationsForPopupList($visitor, true)->total();
		if ($totalUnread != $visitor->conversations_unread)
		{
			$visitor->conversations_unread = $totalUnread;
			$visitor->saveIfChanged();
		}

		$viewParams = [
			'unreadConversations' => $conversations['unread'],
			'readConversations' => $conversations['read'],
		];
		return $this->view('XF:Conversations\Popup', 'conversations_popup', $viewParams);
	}

	public function actionView(ParameterBag $params)
	{
		$this->assertNotEmbeddedImageRequest();

		$userConv = $this->assertViewableUserConversation(
			$params->conversation_id,
			['Master.DraftReplies|' . \XF::visitor()->user_id]
		);
		$conversation = $userConv->Master;

		$page = $params->page;
		$perPage = $this->options()->messagesPerPage;

		$messageCount = $conversation->reply_count + 1;

		$this->assertValidPage($page, $perPage, $messageCount, 'direct-messages', $conversation);
		$this->assertCanonicalUrl($this->buildLink('direct-messages', $conversation, ['page' => $page]));

		$conversationRepo = $this->getConversationRepo();
		$conversationMessageRepo = $this->getConversationMessageRepo();

		$messageList = $conversationMessageRepo->findMessagesForConversationView($conversation);
		$messages = $messageList->limitByPage($page, $perPage)->fetch();

		/** @var AttachmentRepository $attachmentRepo */
		$attachmentRepo = $this->repository(AttachmentRepository::class);
		$attachmentRepo->addAttachmentsToContent($messages, 'conversation_message');

		/** @var UserAlertRepository $userAlertRepo */
		$userAlertRepo = $this->repository(UserAlertRepository::class);
		$userAlertRepo->markUserAlertsReadForContent('conversation_message', $messages->keys());

		/** @var UnfurlRepository $unfurlRepo */
		$unfurlRepo = $this->repository(UnfurlRepository::class);
		$unfurlRepo->addUnfurlsToContent($messages, false);

		/** @var EmbedResolverRepository $embedRepo */
		$embedRepo = $this->repository(EmbedResolverRepository::class);
		$embedRepo->addEmbedsToContent($messages);

		$lastRead = $userConv->Recipient ? $userConv->Recipient->last_read_date : 0;

		$lastMessage = $messages->last();
		$conversationRepo->markUserConversationRead($userConv, $lastMessage->message_date);

		$viewParams = [
			'userConv' => $userConv,
			'conversation' => $conversation,
			'recipients' => $conversationRepo->findRecipientsForList($conversation)->fetch(),

			'lastRead' => $lastRead,
			'messages' => $messages,
			'lastMessage' => $lastMessage,

			'page' => $page,
			'perPage' => $perPage,

			'attachmentData' => $this->getReplyAttachmentData($conversation),
		];
		return $this->view('XF:Conversation\View', 'conversation_view', $viewParams);
	}

	public function actionUnread(ParameterBag $params)
	{
		$userConv = $this->assertViewableUserConversation($params->conversation_id);
		$conversation = $userConv->Master;
		$recipient = $userConv->Recipient;

		if (!$recipient || !$recipient->last_read_date)
		{
			return $this->redirect($this->buildLink('direct-messages', $userConv));
		}

		$convMessageRepo = $this->getConversationMessageRepo();

		$firstUnread = $convMessageRepo->getFirstUnreadMessageInConversation($userConv);
		if (!$firstUnread || $firstUnread->message_id == $conversation->last_message_id)
		{
			$messagesBefore = $conversation->reply_count;
			$messageId = $conversation->last_message_id;
		}
		else
		{
			$messagesBefore = $convMessageRepo->findEarlierMessages($conversation, $firstUnread)->total();
			$messageId = $firstUnread->message_id;
		}

		$page = floor($messagesBefore / $this->options()->messagesPerPage) + 1;
		return $this->redirect(
			$this->buildLink('direct-messages', $conversation, ['page' => $page]) . '#convMessage-' . $messageId
		);
	}

	public function actionLatest(ParameterBag $params)
	{
		$userConv = $this->assertViewableUserConversation($params->conversation_id);
		$conversation = $userConv->Master;

		$messagesBefore = $conversation->reply_count;
		$messageId = $conversation->last_message_id;

		$page = floor($messagesBefore / $this->options()->messagesPerPage) + 1;
		return $this->redirect(
			$this->buildLink('direct-messages', $conversation, ['page' => $page]) . '#convMessage-' . $messageId
		);
	}

	/**
	 * @return CreatorService
	 */
	protected function setupConversationCreate()
	{
		$recipients = $this->filter('recipients', 'str');
		$title = $this->filter('title', 'str');
		$message = $this->plugin(EditorPlugin::class)->fromInput('message');

		$conversationLocked = $this->filter('conversation_locked', 'bool');
		$options = $this->filter([
			'open_invite' => 'bool',
		]);
		$options['conversation_open'] = !$conversationLocked;

		$visitor = \XF::visitor();

		/** @var CreatorService $creator */
		$creator = $this->service(CreatorService::class, $visitor);
		$creator->setOptions($options);
		$creator->setRecipients($recipients);
		$creator->setContent($title, $message);

		$conversation = $creator->getConversation();

		if ($conversation->canUploadAndManageAttachments())
		{
			$creator->setAttachmentHash($this->filter('attachment_hash', 'str'));
		}

		return $creator;
	}

	protected function finalizeConversationCreate(CreatorService $creator)
	{
		Draft::createFromKey('conversation')->delete();
	}

	public function actionAdd()
	{
		$visitor = \XF::visitor();

		if (!$visitor->canStartConversation())
		{
			return $this->noPermission();
		}

		if ($this->isPost())
		{
			$creator = $this->setupConversationCreate();
			if (!$creator->validate($errors))
			{
				return $this->error($errors);
			}
			$this->assertNotFlooding('conversation', $this->app->options()->floodCheckLengthDiscussion ?: null);
			$conversation = $creator->save();

			$this->finalizeConversationCreate($creator);

			return $this->redirect($this->buildLink('direct-messages', $conversation));
		}
		else
		{
			$to = $this->filter('to', 'str');
			$title = $this->filter('title', 'str');
			$message = $this->filter('message', 'str');

			if ($to !== '' && strpos($to, ',') === false)
			{
				/** @var User $toUser */
				$toUser = $this->em()->findOne(User::class, ['username' => $to]);
				if (!$toUser)
				{
					return $this->notFound(\XF::phrase('requested_user_not_found'));
				}

				if (!$visitor->canStartConversationWith($toUser))
				{
					return $this->noPermission(\XF::phrase('you_may_not_send_direct_message_to_x_because_of_their_privacy_settings', ['name' => $toUser->username]));
				}
			}

			/** @var ConversationMaster $conversation */
			$conversation = $this->em()->create(ConversationMaster::class);

			$draft = Draft::createFromKey('conversation');

			if ($conversation->canUploadAndManageAttachments())
			{
				/** @var AttachmentRepository $attachmentRepo */
				$attachmentRepo = $this->repository(AttachmentRepository::class);
				$attachmentData = $attachmentRepo->getEditorData('conversation_message', null, $draft->attachment_hash);
			}
			else
			{
				$attachmentData = null;
			}

			$viewParams = [
				'to' => $to ?: $draft->recipients,
				'title' => $title ?: $draft->title,
				'message' => $message ?: $draft->message,

				'conversation' => $conversation,
				'maxRecipients' => $conversation->getMaximumAllowedRecipients(),
				'draft' => $draft,

				'attachmentData' => $attachmentData,
			];
			return $this->view('XF:Conversation\Add', 'conversation_add', $viewParams);
		}
	}

	public function actionDraft(ParameterBag $params)
	{
		$this->assertDraftsEnabled();

		$extraData = $this->filter([
			'attachment_hash' => 'str',
		]);

		if ($params->conversation_id)
		{
			$conversation = $this->assertViewableUserConversation($params->conversation_id);

			$draft = $conversation->Master->draft_reply;
		}
		else
		{
			$visitor = \XF::visitor();

			if (!$visitor->canStartConversation())
			{
				return $this->noPermission();
			}

			$extraData = $extraData + $this->filter([
				'recipients' => 'str',
				'title' => 'str',
				'open_invite' => 'bool',
				'conversation_locked' => 'bool',
			]);
			$extraData['conversation_open'] = !$extraData['conversation_locked'];
			unset($extraData['conversation_locked']);

			$draft = Draft::createFromKey('conversation');
		}

		/** @var DraftPlugin $draftPlugin */
		$draftPlugin = $this->plugin(DraftPlugin::class);
		$draftReply = $draftPlugin->actionDraftMessage($draft, $extraData, 'message', $draftAction);

		if ($draftAction == 'save')
		{
			$draftPlugin->refreshTempAttachments($extraData['attachment_hash']);
		}

		return $draftReply;
	}

	/**
	 * @param ConversationMaster $conversation
	 * @param ConversationUser $userConv
	 *
	 * @return ReplierService
	 */
	protected function setupConversationReply(ConversationMaster $conversation, ConversationUser $userConv)
	{
		$visitor = \XF::visitor();
		$message = $this->plugin(EditorPlugin::class)->fromInput('message');

		/** @var ReplierService $replier */
		$replier = $this->service(ReplierService::class, $conversation, $visitor);
		$replier->setMessageContent($message);

		if ($conversation->canUploadAndManageAttachments())
		{
			$replier->setAttachmentHash($this->filter('attachment_hash', 'str'));
		}

		return $replier;
	}

	protected function afterConversationReply(ReplierService $replier)
	{
		$conversation = $replier->getConversation();

		$conversation->draft_reply->delete();
	}

	public function actionReply(ParameterBag $params)
	{
		$userConv = $this->assertViewableUserConversation($params->conversation_id);
		$conversation = $userConv->Master;
		if (!$conversation->canReply())
		{
			return $this->noPermission();
		}

		$defaultMessage = '';
		$forceAttachmentHash = null;

		$quote = $this->filter('quote', 'uint');
		if ($quote)
		{
			/** @var ConversationMessage $message */
			$message = $this->em()->find(ConversationMessage::class, $quote, 'User');
			if ($message->conversation_id == $conversation->conversation_id && $message->canView())
			{
				$defaultMessage = $message->getQuoteWrapper(
					$this->app->stringFormatter()->getBbCodeForQuote($message->message, 'conversation_message')
				);
				$forceAttachmentHash = '';
			}
		}
		else
		{
			$defaultMessage = $conversation->draft_reply->message;
		}

		$viewParams = [
			'conversation' => $conversation,
			'attachmentData' => $this->getReplyAttachmentData($conversation, $forceAttachmentHash),
			'defaultMessage' => $defaultMessage,
		];
		return $this->view('XF:Conversation\Reply', 'conversation_reply', $viewParams);
	}

	public function actionAddReply(ParameterBag $params)
	{
		$this->assertPostOnly();

		$userConv = $this->assertViewableUserConversation($params->conversation_id);
		$conversation = $userConv->Master;
		if (!$conversation->canReply())
		{
			return $this->noPermission();
		}

		$replier = $this->setupConversationReply($conversation, $userConv);
		if (!$replier->validate($errors))
		{
			return $this->error($errors);
		}
		$this->assertNotFlooding('conversation_message');
		$message = $replier->save();

		$this->afterConversationReply($replier);

		if ($this->filter('_xfWithData', 'bool') && $this->request->exists('last_date') && $message->canView())
		{
			$convMessageRepo = $this->getConversationMessageRepo();

			$limit = 3;
			$lastDate = $this->filter('last_date', 'uint');

			/** @var Finder $messageList */
			$messageList = $convMessageRepo->findNewestMessagesInConversation($conversation, $lastDate)->limit($limit + 1);
			$messages = $messageList->fetch();

			// We fetched one more post than needed, if more than $limit posts were returned,
			// we can show the 'there are more posts' notice
			if ($messages->count() > $limit)
			{
				$firstUnshownMessage = $messages->first();

				// Remove the extra post
				$messages = $messages->pop();
			}
			else
			{
				$firstUnshownMessage = null;
			}

			// put the posts into oldest-first order
			$messages = $messages->reverse(true);

			/** @var AttachmentRepository $attachmentRepo */
			$attachmentRepo = $this->repository(AttachmentRepository::class);
			$attachmentRepo->addAttachmentsToContent($messages, 'conversation_message');

			$viewParams = [
				'conversation' => $conversation,
				'messages' => $messages,
				'firstUnshownMessage' => $firstUnshownMessage,
			];
			$view = $this->view('XF:Conversation\NewMessages', 'conversation_reply_new_messages', $viewParams);
			$view->setJsonParam('lastDate', $messages->last()->message_date);
			return $view;
		}
		else
		{
			return $this->redirect($this->buildLink('direct-messages/replies', $message));
		}
	}

	public function actionAddPreview()
	{
		$visitor = \XF::visitor();

		if (!$visitor->canStartConversation())
		{
			return $this->noPermission();
		}

		$creator = $this->setupConversationCreate();
		if (!$creator->validate($errors) && isset($errors['message']))
		{
			return $this->error($errors);
		}

		$message = $creator->getMessage();
		$conversation = $creator->getConversation();
		$attachments = null;

		$tempHash = $this->filter('attachment_hash', 'str');
		if ($tempHash && $conversation->canUploadAndManageAttachments())
		{
			$attachRepo = $this->repository(AttachmentRepository::class);
			$attachments = $attachRepo->findAttachmentsByTempHash($tempHash)->fetch();
		}

		return $this->plugin(BbCodePreviewPlugin::class)->actionPreview(
			$message->message,
			'conversation_message',
			$message->User,
			$attachments
		);
	}

	public function actionReplyPreview(ParameterBag $params)
	{
		$this->assertPostOnly();

		$userConv = $this->assertViewableUserConversation($params->conversation_id);
		$conversation = $userConv->Master;
		if (!$conversation->canReply())
		{
			return $this->noPermission();
		}

		$replier = $this->setupConversationReply($conversation, $userConv);
		if (!$replier->validate($errors))
		{
			return $this->error($errors);
		}

		$message = $replier->getMessage();
		$attachments = null;

		$tempHash = $this->filter('attachment_hash', 'str');
		if ($tempHash && $conversation->canUploadAndManageAttachments())
		{
			$attachRepo = $this->repository(AttachmentRepository::class);
			$attachments = $attachRepo->findAttachmentsByTempHash($tempHash)->fetch();
		}

		return $this->plugin(BbCodePreviewPlugin::class)->actionPreview(
			$message->message,
			'conversation_message',
			$message->User,
			$attachments
		);
	}

	public function actionMultiQuote(ParameterBag $params)
	{
		$this->assertPostOnly();

		/** @var QuotePlugin $quotePlugin */
		$quotePlugin = $this->plugin(QuotePlugin::class);

		$quotes = $this->filter('quotes', 'json-array');
		if (!$quotes)
		{
			return $this->error(\XF::phrase('no_messages_selected'));
		}
		$quotes = $quotePlugin->prepareQuotes($quotes);

		$messageFinder = $this->finder(ConversationMessageFinder::class);

		$messages = $messageFinder
			->with(['Conversation', 'User'])
			->where('message_id', array_keys($quotes))
			->order('message_date', 'DESC')
			->fetch()
			->filterViewable();

		if ($this->request->exists('insert'))
		{
			$insertOrder = $this->filter('insert', 'array');
			return $quotePlugin->actionMultiQuote($messages, $insertOrder, $quotes, 'conversation_message');
		}
		else
		{
			$viewParams = [
				'quotes' => $quotes,
				'messages' => $messages,
			];
			return $this->view('XF:Conversation\MultiQuote', 'conversation_multi_quote', $viewParams);
		}
	}

	/**
	 * @param ConversationMaster $conversation
	 *
	 * @return EditorService
	 */
	protected function setupConversationEdit(ConversationMaster $conversation)
	{
		/** @var EditorService $editor */
		$editor = $this->service(EditorService::class, $conversation);

		$editor->setTitle($this->filter('title', 'str'));
		$editor->setOpenInvite($this->filter('open_invite', 'bool'));
		$editor->setConversationOpen(!$this->filter('conversation_locked', 'bool'));

		return $editor;
	}


	public function actionEdit(ParameterBag $params)
	{
		$userConv = $this->assertViewableUserConversation($params->conversation_id);
		$conversation = $userConv->Master;

		if (!$conversation->canEdit())
		{
			return $this->noPermission();
		}

		if ($this->isPost())
		{
			$editor = $this->setupConversationEdit($conversation);

			if (!$editor->validate($errors))
			{
				return $this->error($errors);
			}

			$editor->save();

			return $this->redirect($this->buildLink('direct-messages', $conversation));
		}
		else
		{
			$viewParams = [
				'userConv' => $userConv,
				'conversation' => $conversation,
			];
			return $this->view('XF:Conversation\Edit', 'conversation_edit', $viewParams);
		}
	}

	public function actionStar(ParameterBag $params)
	{
		$userConv = $this->assertViewableUserConversation($params->conversation_id);

		$wasStarred = $userConv->is_starred;

		$redirect = $this->getDynamicRedirect(null, false);

		if ($this->isPost())
		{
			if (!$wasStarred)
			{
				$userConv->is_starred = true;
				$message = \XF::phrase('direct_message_starred');
			}
			else
			{
				$userConv->is_starred = false;
				$message = \XF::phrase('direct_message_unstarred');
			}

			$userConv->save();

			$reply = $this->redirect($redirect, $message);
			$reply->setJsonParam('switchKey', $userConv->is_starred ? 'unstar' : 'star');
			return $reply;
		}
		else
		{
			$viewParams = [
				'userConv' => $userConv,
				'conversation' => $userConv->Master,
				'redirect' => $redirect,
				'isStarred' => $wasStarred,
			];
			return $this->view('XF:Conversation\Star', 'conversation_star', $viewParams);
		}
	}

	public function actionMarkUnread(ParameterBag $params)
	{
		$userConv = $this->assertViewableUserConversation($params->conversation_id);

		$wasUnread = $userConv->is_unread;

		$redirect = $this->getDynamicRedirect($this->buildLink('direct-messages'));

		if ($this->isPost())
		{
			if (!$wasUnread)
			{
				$this->getConversationRepo()->markUserConversationUnread($userConv);
				$message = \XF::phrase('direct_message_marked_as_unread');
			}
			else
			{
				$this->getConversationRepo()->markUserConversationRead($userConv);
				$message = \XF::phrase('direct_message_marked_as_read');
			}

			$reply = $this->redirect($redirect, $message);
			$reply->setJsonParam('switchKey', $userConv->is_unread ? 'read' : 'unread');
			return $reply;
		}
		else
		{
			$viewParams = [
				'userConv' => $userConv,
				'conversation' => $userConv->Master,
				'redirect' => $redirect,
				'isUnread' => $wasUnread,
			];
			return $this->view('XF:Conversation\MarkUnread', 'conversation_mark_unread', $viewParams);
		}
	}

	public function actionInvite(ParameterBag $params)
	{
		$userConv = $this->assertViewableUserConversation($params->conversation_id);
		$conversation = $userConv->Master;
		if (!$conversation->canInvite())
		{
			return $this->noPermission();
		}

		if ($this->isPost())
		{
			/** @var InviterService $inviter */
			$inviter = $this->service(InviterService::class, $conversation, \XF::visitor());

			$recipients = $this->filter('recipients', 'str');
			$inviter->setRecipients($recipients);
			if (!$inviter->validate($errors))
			{
				return $this->error($errors);
			}

			$inviter->save();

			return $this->redirect($this->buildLink('direct-messages', $conversation));
		}
		else
		{
			$viewParams = [
				'userConv' => $userConv,
				'conversation' => $conversation,
				'remainingRecipients' => $conversation->getRemainingRecipientsCount(),
			];
			return $this->view('XF:Conversation\Invite', 'conversation_invite', $viewParams);
		}
	}

	public function actionLeave(ParameterBag $params)
	{
		$userConv = $this->assertViewableUserConversation($params->conversation_id);
		$conversation = $userConv->Master;

		if ($this->isPost())
		{
			$recipientState = $this->filter('recipient_state', 'str');

			// TODO: turn to service?
			switch ($recipientState)
			{
				case 'deleted':
				case 'deleted_ignored':
					break;

				default:
					$recipientState = 'deleted';
			}

			$recipient = $userConv->Recipient;
			if ($recipient)
			{
				$recipient->recipient_state = $recipientState;
				$recipient->save();
			}

			$this->plugin(InlineModPlugin::class)->clearIdFromCookie('conversation', $conversation->conversation_id);

			return $this->redirect($this->buildLink('direct-messages'));
		}
		else
		{
			$viewParams = [
				'conversation' => $conversation,
			];
			return $this->view('XF:Conversation\Leave', 'conversation_leave', $viewParams);
		}
	}

	public function actionReplies(ParameterBag $params)
	{
		return $this->rerouteController(self::class, 'messages', $params);
	}

	public function actionMessages(ParameterBag $params)
	{
		$message = $this->assertViewableMessage($params->message_id);
		$conversation = $message->Conversation;

		$conversationMessageRepo = $this->getConversationMessageRepo();

		$redirectParams = [];
		$earlierMessages = $conversationMessageRepo->findEarlierMessages($conversation, $message)->total();

		$page = floor($earlierMessages / $this->options()->messagesPerPage) + 1;
		if ($page > 1)
		{
			$redirectParams['page'] = $page;
		}

		return $this->redirectPermanently(
			$this->buildLink('direct-messages', $conversation, $redirectParams) . '#convMessage-' . $message->message_id
		);
	}

	public function actionRepliesReact(ParameterBag $params)
	{
		return $this->rerouteController(self::class, 'messages/react', $params);
	}

	public function actionMessagesReact(ParameterBag $params)
	{
		$message = $this->assertViewableMessage($params->message_id);

		/** @var ReactionPlugin $reactionPlugin */
		$reactionPlugin = $this->plugin(ReactionPlugin::class);
		return $reactionPlugin->actionReactSimple($message, 'direct-messages/replies');
	}

	public function actionRepliesReactions(ParameterBag $params)
	{
		return $this->rerouteController(self::class, 'messages/reactions', $params);
	}

	public function actionMessagesReactions(ParameterBag $params)
	{
		$message = $this->assertViewableMessage($params->message_id);

		$breadcrumbs = [];
		$breadcrumbs[] = [
			'value' => $message->Conversation->title,
			'href' => $this->buildLink('direct-messages', $message->Conversation),
		];

		$title = \XF::phrase('members_who_reacted_to_message_by_x', ['user' => $message->User ? $message->User->username : $message->username]);

		/** @var ReactionPlugin $reactionPlugin */
		$reactionPlugin = $this->plugin(ReactionPlugin::class);
		return $reactionPlugin->actionReactions(
			$message,
			'direct-messages/replies/reactions',
			$title,
			$breadcrumbs
		);
	}

	public function actionRepliesQuote(ParameterBag $params)
	{
		return $this->rerouteController(self::class, 'messages/quote', $params);
	}

	public function actionMessagesQuote(ParameterBag $params)
	{
		$message = $this->assertViewableMessage($params->message_id);
		if (!$message->Conversation->canReply())
		{
			return $this->noPermission();
		}

		return $this->plugin(QuotePlugin::class)->actionQuote($message, 'conversation_message');
	}

	/**
	 * @param ConversationMessage $message
	 *
	 * @return MessageEditorService
	 */
	protected function setupMessageEdit(ConversationMessage $conversationMessage)
	{
		$message = $this->plugin(EditorPlugin::class)->fromInput('message');

		/** @var MessageEditorService $editor */
		$editor = $this->service(MessageEditorService::class, $conversationMessage);
		$editor->setMessageContent($message);

		$conversation = $conversationMessage->Conversation;

		if ($conversation->canUploadAndManageAttachments())
		{
			$editor->setAttachmentHash($this->filter('attachment_hash', 'str'));
		}

		return $editor;
	}

	public function actionRepliesEdit(ParameterBag $params)
	{
		return $this->rerouteController(self::class, 'messages/edit', $params);
	}

	public function actionMessagesEdit(ParameterBag $params)
	{
		$message = $this->assertViewableMessage($params->message_id);
		if (!$message->canEdit($error))
		{
			return $this->noPermission($error);
		}
		$conversation = $message->Conversation;

		if ($this->isPost())
		{
			$editor = $this->setupMessageEdit($message);
			if (!$editor->validate($errors))
			{
				return $this->error($errors);
			}
			$editor->save();

			if ($this->filter('_xfWithData', 'bool') && $this->filter('_xfInlineEdit', 'bool'))
			{
				/** @var AttachmentRepository $attachmentRepo */
				$attachmentRepo = $this->repository(AttachmentRepository::class);
				$attachmentRepo->addAttachmentsToContent([
					$message->message_id => $message,
				], 'conversation_message');

				$viewParams = [
					'conversation' => $conversation,
					'message' => $message,
				];
				$reply = $this->view('XF:Conversation\Message\EditNewMessage', 'conversation_message_edit_new_message', $viewParams);
				$reply->setJsonParam('message', \XF::phrase('your_changes_have_been_saved'));
				return $reply;
			}
			else
			{
				return $this->redirect($this->buildLink('direct-messages/replies', $message));
			}
		}
		else
		{
			if ($conversation->canUploadAndManageAttachments())
			{
				/** @var AttachmentRepository $attachmentRepo */
				$attachmentRepo = $this->repository(AttachmentRepository::class);
				$attachmentData = $attachmentRepo->getEditorData('conversation_message', $message);
			}
			else
			{
				$attachmentData = null;
			}

			$viewParams = [
				'conversation' => $conversation,
				'message' => $message,

				'attachmentData' => $attachmentData,
				'quickEdit' => $this->filter('_xfWithData', 'bool'),
			];
			return $this->view('XF:Conversation\Message\Edit', 'conversation_message_edit', $viewParams);
		}
	}

	public function actionRepliesPreview(ParameterBag $params)
	{
		return $this->rerouteController(self::class, 'messages/preview', $params);
	}

	public function actionMessagesPreview(ParameterBag $params)
	{
		$this->assertPostOnly();

		$message = $this->assertViewableMessage($params->message_id);
		if (!$message->canEdit($error))
		{
			return $this->noPermission($error);
		}

		$editor = $this->setupMessageEdit($message);
		if (!$editor->validate($errors))
		{
			return $this->error($errors);
		}

		$conversation = $message->Conversation;

		$attachments = [];
		$tempHash = $this->filter('attachment_hash', 'str');

		if ($conversation->canUploadAndManageAttachments())
		{
			/** @var AttachmentRepository $attachmentRepo */
			$attachmentRepo = $this->repository(AttachmentRepository::class);
			$attachmentData = $attachmentRepo->getEditorData('conversation_message', $message, $tempHash);
			$attachments = $attachmentData['attachments'];
		}

		return $this->plugin(BbCodePreviewPlugin::class)->actionPreview(
			$message->message,
			'post',
			$message->User,
			$attachments,
			true
		);
	}

	public function actionRepliesIp(ParameterBag $params)
	{
		return $this->rerouteController(self::class, 'messages/ip', $params);
	}

	public function actionMessagesIp(ParameterBag $params)
	{
		$message = $this->assertViewableMessage($params->message_id);

		/** @var IpPlugin $ipPlugin */
		$ipPlugin = $this->plugin(IpPlugin::class);
		return $ipPlugin->actionIp($message);
	}

	public function actionRepliesReport(ParameterBag $params)
	{
		return $this->rerouteController(self::class, 'messages/report', $params);
	}

	public function actionMessagesReport(ParameterBag $params)
	{
		$message = $this->assertViewableMessage($params->message_id);
		if (!$message->canReport($error))
		{
			return $this->noPermission($error);
		}

		/** @var ReportPlugin $reportPlugin */
		$reportPlugin = $this->plugin(ReportPlugin::class);
		return $reportPlugin->actionReport(
			'conversation_message',
			$message,
			$this->buildLink('direct-messages/replies/report', $message),
			$this->buildLink('direct-messages/replies', $message),
			[
				'extraViewParams' => [
					'conversation' => $message->Conversation,
				],
			]
		);
	}

	/**
	 * @param $conversationId
	 * @param array $extraWith
	 *
	 * @return ConversationUser
	 *
	 * @throws Exception
	 */
	protected function assertViewableUserConversation($conversationId, array $extraWith = [])
	{
		$visitor = \XF::visitor();

		/** @var ConversationUserFinder $finder */
		$finder = $this->finder(ConversationUserFinder::class);
		$finder->forUser($visitor, false);
		$finder->where('conversation_id', $conversationId);
		$finder->with($extraWith);

		/** @var ConversationUser $conversation */
		$conversation = $finder->fetchOne();
		if (!$conversation || !$conversation->Master)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_direct_message_not_found')));
		}

		$this->setContentKey('conversation-' . $conversation->conversation_id);

		return $conversation;
	}

	/**
	 * @param $messageId
	 * @param array $extraWith
	 *
	 * @return ConversationMessage
	 *
	 * @throws Exception
	 */
	protected function assertViewableMessage($messageId, array $extraWith = [])
	{
		$extraWith[] = 'Conversation';

		$visitor = \XF::visitor();
		if ($visitor->user_id)
		{
			$extraWith[] = 'Conversation.Recipients|' . $visitor->user_id;
			$extraWith[] = 'Conversation.Users|' . $visitor->user_id;
		}

		$extraWith = array_unique($extraWith);

		/** @var ConversationMessage $message */
		$message = $this->em()->find(ConversationMessage::class, $messageId, $extraWith);
		if (!$message)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_message_not_found')));
		}
		if (!$message->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		$this->setContentKey('conversation_message-' . $message->conversation_id);

		return $message;
	}

	protected function getReplyAttachmentData(ConversationMaster $conversation, $forceAttachmentHash = null)
	{
		if ($conversation->canUploadAndManageAttachments())
		{
			if ($forceAttachmentHash !== null)
			{
				$attachmentHash = $forceAttachmentHash;
			}
			else
			{
				$attachmentHash = $conversation->draft_reply->attachment_hash;
			}

			/** @var AttachmentRepository $attachmentRepo */
			$attachmentRepo = $this->repository(AttachmentRepository::class);
			return $attachmentRepo->getEditorData('conversation_message', $conversation, $attachmentHash);
		}
		else
		{
			return null;
		}
	}

	protected function canUpdateSessionActivity($action, ParameterBag $params, AbstractReply &$reply, &$viewState)
	{
		if (strtolower($action) == 'addreply')
		{
			$viewState = 'valid';
			return true;
		}
		return parent::canUpdateSessionActivity($action, $params, $reply, $viewState);
	}

	/**
	 * @return ConversationRepository
	 */
	protected function getConversationRepo()
	{
		return $this->repository(ConversationRepository::class);
	}

	/**
	 * @return ConversationMessageRepository
	 */
	protected function getConversationMessageRepo()
	{
		return $this->repository(ConversationMessageRepository::class);
	}

	public function assertNotSecurityLocked($action)
	{
		switch (strtolower($action))
		{
			// mostly just so it doesn't error
			case 'popup':
				break;

			default:
				parent::assertNotSecurityLocked($action);
		}
	}

	public function assertPolicyAcceptance($action)
	{
		switch (strtolower($action))
		{
			// mostly just so it doesn't error
			case 'popup':
				break;

			default:
				parent::assertPolicyAcceptance($action);
		}
	}

	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('viewing_direct_messages');
	}
}
