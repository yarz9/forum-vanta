<?php

namespace XF\Pub\Controller;

use XF\ControllerPlugin\BbCodePreviewPlugin;
use XF\ControllerPlugin\ContentVotePlugin;
use XF\ControllerPlugin\DraftPlugin;
use XF\ControllerPlugin\EditorPlugin;
use XF\ControllerPlugin\FeaturedContentPlugin;
use XF\ControllerPlugin\InlineModPlugin;
use XF\ControllerPlugin\ModeratorLogPlugin;
use XF\ControllerPlugin\NodePlugin;
use XF\ControllerPlugin\PollPlugin;
use XF\ControllerPlugin\PreRegActionPlugin;
use XF\ControllerPlugin\QuotePlugin;
use XF\ControllerPlugin\ThreadPlugin;
use XF\ControllerPlugin\UndeletePlugin;
use XF\Entity\Forum;
use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\Finder\PostFinder;
use XF\Finder\ThreadFinder;
use XF\Finder\UserFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Mvc\Reply\View;
use XF\Mvc\RouteMatch;
use XF\Repository\AttachmentRepository;
use XF\Repository\NodeRepository;
use XF\Repository\PostRepository;
use XF\Repository\ThreadReplyBanRepository;
use XF\Repository\ThreadRepository;
use XF\Repository\ThreadTypeRepository;
use XF\Repository\ThreadWatchRepository;
use XF\Repository\UserAlertRepository;
use XF\Service\Tag\ChangerService;
use XF\Service\Thread\ApproverService;
use XF\Service\Thread\ChangeTypeService;
use XF\Service\Thread\DeleterService;
use XF\Service\Thread\EditorService;
use XF\Service\Thread\MoverService;
use XF\Service\Thread\ReplierService;
use XF\Service\Thread\ReplyBanService;
use XF\ThreadType\AbstractHandler;

use function count, intval, strval;

class ThreadController extends AbstractController
{
	use EmbedResolverTrait;

	public function actionIndex(ParameterBag $params)
	{
		$this->assertNotEmbeddedImageRequest();

		$thread = $this->assertViewableThread($params->thread_id, $this->getThreadViewExtraWith());

		$overrideReply = $thread->TypeHandler->overrideDisplay($thread, $this);
		if ($overrideReply)
		{
			return $overrideReply;
		}

		$threadRepo = $this->getThreadRepo();
		$threadPlugin = $this->plugin(ThreadPlugin::class);

		$filters = $this->getPostListFilterInput($thread);
		$page = $this->filterPage($params->page);
		$perPage = $this->options()->messagesPerPage;

		$this->assertCanonicalUrl($this->buildLink('threads', $thread, ['page' => $page]));

		$effectiveOrder = $threadPlugin->getEffectivePostListOrder(
			$thread,
			$this->filter('order', 'str'),
			$defaultOrder,
			$availableSorts
		);

		$isSimpleDateDisplay = ($effectiveOrder == 'post_date' && !$filters);

		$postRepo = $this->getPostRepo();

		$postList = $postRepo->findPostsForThreadView($thread);
		$postList->order($availableSorts[$effectiveOrder]);
		$this->applyPostListFilters($thread, $postList, $filters);

		$thread->TypeHandler->adjustThreadPostListFinder($thread, $postList, $page, $this->request);

		if ($effectiveOrder == 'post_date' && !$filters)
		{
			// can only do this if sorting by position
			$postList->onPage($page, $perPage);
		}
		else
		{
			$postList->limitByPage($page, $perPage);
		}

		$totalPosts = $filters ? $postList->total() : ($thread->reply_count + 1);

		$this->assertValidPage($page, $perPage, $totalPosts, 'threads', $thread);

		$posts = $postList->fetch();

		if (!$filters && !$posts->count())
		{
			if ($page > 1)
			{
				return $this->redirect($this->buildLink('threads', $thread));
			}
			else
			{
				// should never really happen
				return $this->error(\XF::phrase('something_went_wrong_please_try_again'));
			}
		}

		$isFirstPostPinned = $thread->TypeHandler->isFirstPostPinned($thread);
		$highlightPostIds = $thread->TypeHandler->getHighlightedPostIds($thread, $filters);

		$extraFetchIds = [];

		if ($isFirstPostPinned && !isset($posts[$thread->first_post_id]))
		{
			$extraFetchIds[$thread->first_post_id] = $thread->first_post_id;
		}
		foreach ($highlightPostIds AS $highlightPostId)
		{
			if (!isset($posts[$highlightPostId]))
			{
				$extraFetchIds[$highlightPostId] = $highlightPostId;
			}
		}

		if ($extraFetchIds)
		{
			$extraFinder = $postRepo->findSpecificPostsForThreadView($thread, $extraFetchIds);

			$this->applyPostListFilters($thread, $extraFinder, $filters, $extraFetchIds);
			$thread->TypeHandler->adjustThreadPostListFinder(
				$thread,
				$extraFinder,
				$page,
				$this->request,
				$extraFetchIds
			);

			$fetchPinnedPosts = $extraFinder->fetch();
			$posts = $posts->merge($fetchPinnedPosts);
		}

		$threadPlugin->fetchExtraContentForPostsFullView($posts, $thread);

		$threadViewData = $thread->TypeHandler->setupThreadViewData($thread, $posts, $extraFetchIds);
		if ($isFirstPostPinned)
		{
			$threadViewData->pinFirstPost();
		}
		if ($highlightPostIds)
		{
			$threadViewData->addHighlightedPosts($highlightPostIds);
		}

		/** @var UserAlertRepository $userAlertRepo */
		$userAlertRepo = $this->repository(UserAlertRepository::class);
		$userAlertRepo->markUserAlertsReadForContent(
			'post',
			array_keys($threadViewData->getFullyDisplayedPosts())
		);

		// note that this is the last shown post -- might not be date ordered any longer
		$lastPost = $threadViewData->getLastPost();

		if ($isSimpleDateDisplay && !$this->request->isPrefetch())
		{
			$threadRepo->markThreadReadByVisitor($thread, $lastPost->post_date);
		}

		if ($this->isContentViewCounted())
		{
			$threadRepo->logThreadView($thread);
		}

		$overrideContext = [
			'page' => $page,
			'effectiveOrder' => $effectiveOrder,
			'filters' => $filters,
		];

		$pageNavFilters = $filters;
		if ($effectiveOrder != $defaultOrder)
		{
			$pageNavFilters['order'] = $effectiveOrder;
		}

		$creatableThreadTypes = $this->repository(ThreadTypeRepository::class)->getThreadTypeListData(
			$thread->Forum->getCreatableThreadTypes(),
			ThreadTypeRepository::FILTER_SINGLE_CONVERTIBLE
		);

		$viewParams = [
			'thread' => $thread,
			'forum' => $thread->Forum,
			'posts' => $threadViewData->getMainPosts(),
			'firstPost' => $threadViewData->getFirstPost(),
			'lastPost' => $lastPost,
			'firstUnread' => $threadViewData->getFirstUnread(),
			'isSimpleDateDisplay' => $isSimpleDateDisplay,
			'creatableThreadTypes' => $creatableThreadTypes,

			'isFirstPostPinned' => $isFirstPostPinned,
			'pinnedPost' => $threadViewData->getPinnedFirstPost(),
			'highlightedPosts' => $threadViewData->getHighlightedPosts(),
			'templateOverrides' => $thread->TypeHandler->getThreadViewTemplateOverrides($thread, $overrideContext),

			'availableSorts' => $availableSorts,
			'defaultOrder' => $defaultOrder,
			'effectiveOrder' => $effectiveOrder,
			'filters' => $filters,

			'canInlineMod' => $threadViewData->canUseInlineModeration(),

			'page' => $page,
			'perPage' => $perPage,
			'totalPosts' => $totalPosts,
			'pageNavFilters' => $pageNavFilters,
			'pageNavHash' => $isFirstPostPinned ? '>1:#posts' : '',

			'attachmentData' => $this->getReplyAttachmentData($thread),

			'pendingApproval' => $this->filter('pending_approval', 'bool'),
		];

		[$viewClass, $viewTemplate] = $thread->TypeHandler->getThreadViewAndTemplate($thread);
		$viewParams = $thread->TypeHandler->adjustThreadViewParams($thread, $viewParams, $this->request);

		return $this->view($viewClass, $viewTemplate, $viewParams);
	}

	protected function getThreadViewExtraWith()
	{
		$extraWith = ['User'];
		$userId = \XF::visitor()->user_id;
		if ($userId)
		{
			$extraWith[] = 'Watch|' . $userId;
			$extraWith[] = 'DraftReplies|' . $userId;
			$extraWith[] = 'ReplyBans|' . $userId;
			$extraWith[] = 'ContentVotes|' . $userId; // don't know if this might exist for the thread type, so just grab it
		}

		return $extraWith;
	}

	protected function getPostListFilterInput(Thread $thread): array
	{
		// Currently no globally supported filters
		$filters = [];

		return $thread->TypeHandler->getPostListFilterInput($thread, $this->request, $filters);
	}

	protected function applyPostListFilters(
		Thread $thread,
		PostFinder $postList,
		array $filters,
		?array $extraFetchIds = null
	)
	{
		// Note that if global filters are added, they should be skipped if there are $extraFetchIds.
		// The type handler should opt into that if needed as otherwise it could break thread display.

		$thread->TypeHandler->applyPostListFilters($thread, $postList, $filters, $extraFetchIds);
	}

	public function actionUnread(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id, ['LastPost']);

		if (!\XF::visitor()->user_id)
		{
			return $this->redirect($this->buildLink('threads', $thread));
		}

		$postRepo = $this->getPostRepo();
		$firstUnreadDate = $thread->getVisitorReadDate();

		// this would force us to go to a new post, even if we have no read marking data for this thread
		$forceNew = $this->filter('new', 'bool');

		if (!$forceNew && $firstUnreadDate <= $this->getThreadRepo()->getReadMarkingCutOff())
		{
			// We have no read marking data for this person, so we don't know whether they've read this thread before.
			// More than likely, they haven't so we have to take them to the beginning.
			return $this->redirect($this->buildLink('threads', $thread));
		}

		$findFirstUnread = $postRepo->findNextPostsInThread($thread, $firstUnreadDate);
		$firstUnread = $findFirstUnread->skipIgnored()->fetchOne();

		if (!$firstUnread)
		{
			$firstUnread = $thread->LastPost;
		}

		if (!$firstUnread)
		{
			// sanity check, probably shouldn't happen
			return $this->redirect($this->buildLink('threads', $thread));
		}

		if ($firstUnread->post_id == $thread->first_post_id)
		{
			return $this->redirect($this->buildLink('threads', $thread));
		}

		return $this->redirect($this->plugin(ThreadPlugin::class)->getPostLink($firstUnread));
	}

	public function actionLatest(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id, ['LastPost']);
		$post = $thread->LastPost;

		if ($post)
		{
			return $this->redirect($this->plugin(ThreadPlugin::class)->getPostLink($post));
		}
		else
		{
			return $this->redirect($this->buildLink('threads', $thread));
		}
	}

	public function actionPost(ParameterBag $params)
	{
		$postId = max(0, intval($params->post_id));
		if (!$postId)
		{
			return $this->notFound();
		}

		$visitor = \XF::visitor();
		$with = [
			'Thread',
			'Thread.Forum',
			'Thread.Forum.Node',
			'Thread.Forum.Node.Permissions|' . $visitor->permission_combination_id,
		];

		/** @var Post $post */
		$post = $this->em()->find(Post::class, $postId, $with);
		if (!$post)
		{
			$thread = $this->em()->find(Thread::class, $params->thread_id);
			if ($thread)
			{
				if (!$thread->canView($error))
				{
					return $this->noPermission($error);
				}
				else
				{
					return $this->redirect($this->buildLink('threads', $thread));
				}
			}
			else
			{
				return $this->notFound(\XF::phrase('requested_thread_not_found'));
			}
		}
		if (!$post->canView($error))
		{
			return $this->noPermission($error);
		}

		return $this->redirectPermanently($this->plugin(ThreadPlugin::class)->getPostLink($post));
	}

	public function actionNewPosts(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id, ['FirstPost']);

		$after = $this->filter('after', 'uint');

		if (!$this->request->isXhr())
		{
			if (!$after)
			{
				return $this->redirect($this->buildLink('threads', $thread));
			}

			$findFirstUnread = $this->getPostRepo()->findNextPostsInThread($thread, $after);
			$firstPost = $findFirstUnread->skipIgnored()->fetchOne();

			if (!$firstPost)
			{
				$firstPost = $thread->LastPost;
			}

			return $this->redirect($this->plugin(ThreadPlugin::class)->getPostLink($firstPost));
		}

		return $this->getNewPostsSinceDateReply($thread, $after);
	}

	public function actionPreview(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id, ['FirstPost']);
		$firstPost = $thread->FirstPost;

		$viewParams = [
			'thread' => $thread,
			'firstPost' => $firstPost,
		];
		return $this->view('XF:Thread\Preview', 'thread_preview', $viewParams);
	}

	public function actionDraft(ParameterBag $params)
	{
		$this->assertDraftsEnabled();

		$thread = $this->assertViewableThread($params->thread_id);

		/** @var DraftPlugin $draftPlugin */
		$draftPlugin = $this->plugin(DraftPlugin::class);

		$extraData = $this->filter([
			'attachment_hash' => 'str',
		]);

		$draftAction = $draftPlugin->updateMessageDraft($thread->draft_reply, $extraData);

		if ($draftAction == 'save')
		{
			$draftPlugin->refreshTempAttachments($extraData['attachment_hash']);
		}

		$lastDate = $this->filter('last_date', 'uint');
		$lastKnownDate = $this->filter('last_known_date', 'uint');
		$lastKnownDate = max($lastDate, $lastKnownDate);

		// the check for last date here is to make sure that we have a date we can log in a link
		if ($lastDate && $this->filter('load_extra', 'bool'))
		{
			/** @var Finder $postList */
			$postList = $this->getPostRepo()->findNewestPostsInThread($thread, $lastKnownDate)->skipIgnored();
			$hasNewPost = ($postList->total() > 0);
		}
		else
		{
			$hasNewPost = false;
		}

		$viewParams = [
			'thread' => $thread,
			'hasNewPost' => $hasNewPost,
			'lastDate' => $lastDate,
			'lastKnownDate' => $lastKnownDate,
		];
		$view = $this->view('XF:Thread\Draft', 'thread_save_draft', $viewParams);
		$view->setJsonParam('hasNew', $hasNewPost);
		$view->setJsonParam('lastDate', $lastDate);
		$draftPlugin->addDraftJsonParams($view, $draftAction);

		return $view;
	}

	/**
	 * @param Thread $thread
	 *
	 * @return ReplierService
	 */
	protected function setupThreadReply(Thread $thread)
	{
		$message = $this->plugin(EditorPlugin::class)->fromInput('message');

		/** @var ReplierService $replier */
		$replier = $this->service(ReplierService::class, $thread);

		$replier->setMessage($message);

		if ($thread->Forum->canUploadAndManageAttachments())
		{
			$replier->setAttachmentHash($this->filter('attachment_hash', 'str'));
		}

		if ($thread->canReplyPreReg())
		{
			// only returns true when pre-reg replying is the only option
			$replier->setIsPreRegAction(true);
		}

		return $replier;
	}

	protected function finalizeThreadReply(ReplierService $replier)
	{
		$replier->sendNotifications();

		$thread = $replier->getThread();
		$post = $replier->getPost();
		$visitor = \XF::visitor();

		$setOptions = $this->filter('_xfSet', 'array-bool');
		if ($thread->canWatch())
		{
			if (isset($setOptions['watch_thread']))
			{
				$watch = $this->filter('watch_thread', 'bool');
				if ($watch)
				{
					/** @var ThreadWatchRepository $threadWatchRepo */
					$threadWatchRepo = $this->repository(ThreadWatchRepository::class);

					$state = $this->filter('watch_thread_email', 'bool') ? 'watch_email' : 'watch_no_email';
					$threadWatchRepo->setWatchState($thread, $visitor, $state);
				}
			}
			else
			{
				// use user preferences
				$this->repository(ThreadWatchRepository::class)->autoWatchThread($thread, $visitor, false);
			}
		}

		if ($thread->canLockUnlock() && isset($setOptions['discussion_open']))
		{
			$thread->discussion_open = $this->filter('discussion_open', 'bool');
		}
		if ($thread->canStickUnstick() && isset($setOptions['sticky']))
		{
			$thread->sticky = $this->filter('sticky', 'bool');
		}

		$thread->saveIfChanged($null, false);

		if ($visitor->user_id)
		{
			$readDate = $thread->getVisitorReadDate();
			if ($readDate && $readDate >= $thread->getPreviousValue('last_post_date'))
			{
				$post = $replier->getPost();
				$this->getThreadRepo()->markThreadReadByVisitor($thread, $post->post_date);
			}

			$thread->draft_reply->delete();

			if ($post->message_state == 'moderated')
			{
				$this->session()->setHasContentPendingApproval();
			}
		}
	}

	public function actionReply(ParameterBag $params)
	{
		$visitor = \XF::visitor();

		$thread = $this->assertViewableThread($params->thread_id, ['Watch|' . $visitor->user_id]);
		if (!$thread->canReply($error) && !$thread->canReplyPreReg())
		{
			return $this->noPermission($error);
		}

		$this->assertCaptchaCookieConsent();

		$defaultMessage = '';
		$forceAttachmentHash = null;

		$quote = $this->filter('quote', 'uint');
		if ($quote)
		{
			/** @var Post $post */
			$post = $this->em()->find(Post::class, $quote, 'User');
			if ($post && $post->thread_id == $thread->thread_id && $post->canView())
			{
				$defaultMessage = $post->getQuoteWrapper(
					$this->app->stringFormatter()->getBbCodeForQuote($post->message, 'post')
				);
				$forceAttachmentHash = '';
			}
		}
		else if ($this->request->exists('requires_captcha'))
		{
			$defaultMessage = $this->plugin(EditorPlugin::class)->fromInput('message');
			$forceAttachmentHash = $this->filter('attachment_hash', 'str');
		}
		else
		{
			$defaultMessage = $thread->draft_reply->message;
		}

		$viewParams = [
			'thread' => $thread,
			'forum' => $thread->Forum,
			'attachmentData' => $this->getReplyAttachmentData($thread, $forceAttachmentHash),
			'defaultMessage' => $defaultMessage,
		];
		return $this->view('XF:Thread\Reply', 'thread_reply', $viewParams);
	}

	public function actionAddReply(ParameterBag $params)
	{
		$this->assertPostOnly();

		$visitor = \XF::visitor();
		$thread = $this->assertViewableThread($params->thread_id, ['Watch|' . $visitor->user_id]);

		$isPreRegReply = $thread->canReplyPreReg();

		if (!$thread->canReply($error) && !$isPreRegReply)
		{
			return $this->noPermission($error);
		}

		if (!$isPreRegReply)
		{
			if ($this->filter('no_captcha', 'bool')) // JS is disabled so user hasn't seen Captcha.
			{
				$this->request->set('requires_captcha', true);
				return $this->rerouteController(self::class, 'reply', $params);
			}
			else if (!$this->captchaIsValid())
			{
				return $this->error(\XF::phrase('did_not_complete_the_captcha_verification_properly'));
			}
		}

		$replier = $this->setupThreadReply($thread);

		if (!$isPreRegReply)
		{
			// for pre-reg, this will be done later
			$replier->checkForSpam();
		}

		if (!$replier->validate($errors))
		{
			return $this->error($errors);
		}

		if ($isPreRegReply)
		{
			/** @var PreRegActionPlugin $preRegPlugin */
			$preRegPlugin = $this->plugin(PreRegActionPlugin::class);
			return $preRegPlugin->actionPreRegAction(
				'XF:Thread\Reply',
				$thread,
				$this->getPreRegReplyActionData($replier)
			);
		}

		$this->assertNotFlooding('post');

		$post = $replier->save();

		$this->finalizeThreadReply($replier);

		if ($this->filter('_xfWithData', 'bool') && $this->request->exists('last_date') && $post->canView())
		{
			$lastDate = $this->filter('last_date', 'uint');
			if ($this->filter('load_extra', 'bool') && $lastDate)
			{
				return $this->getNewPostsSinceDateReply($thread, $lastDate);
			}
			else
			{
				return $this->getSingleNewPostReply($thread, $post);
			}
		}
		else
		{
			$this->getThreadRepo()->markThreadReadByVisitor($thread);
			$confirmation = \XF::phrase('your_message_has_been_posted');

			if ($post->canView())
			{
				return $this->redirect($this->buildLink('posts', $post), $confirmation);
			}
			else
			{
				return $this->redirect($this->buildLink('threads', $thread, ['pending_approval' => 1]), $confirmation);
			}
		}
	}

	protected function getPreRegReplyActionData(ReplierService $replier)
	{
		$post = $replier->getPost();

		// note: attachments aren't supported

		return [
			'message' => $post->message,
		];
	}

	/**
	 * Returns a new posts reply for a specified maximum number of posts since that date.
	 *
	 * This only makes sense in the context of a date-ordered, filterless thread view.
	 *
	 * @param Thread $thread
	 * @param int $lastDate Date of the last seen post (will only get ones after this)
	 * @param int $limit Maximum number of posts to show
	 *
	 * @return View
	 */
	protected function getNewPostsSinceDateReply(
		Thread $thread,
		int $lastDate,
		int $limit = 3
	): AbstractReply
	{
		$postRepo = $this->getPostRepo();

		/** @var Finder $postList */
		$postList = $postRepo->findNewestPostsInThread($thread, $lastDate)->skipIgnored()->with('full');
		$posts = $postList->fetch($limit + 1);

		// We fetched one more post than needed, if more than $limit posts were returned,
		// we can show the 'there are more posts' notice
		if ($posts->count() > $limit)
		{
			/** @var Post|null $firstUnshownPost */
			$firstUnshownPost = $postRepo->findNextPostsInThread($thread, $lastDate)->skipIgnored()->fetchOne();

			// Remove the extra post
			$posts = $posts->pop();
		}
		else
		{
			$firstUnshownPost = null;
		}

		// put the posts into oldest-first order
		$posts = $posts->reverse(true);

		$visitor = \XF::visitor();
		$threadRead = $thread->Read[$visitor->user_id];

		if ($visitor->user_id)
		{
			if (!$firstUnshownPost || ($threadRead && $firstUnshownPost->post_date <= $threadRead->thread_read_date))
			{
				$this->getThreadRepo()->markThreadReadByVisitor($thread);
			}
		}

		return $this->getNewPostsReplyInternal($thread, $posts, $firstUnshownPost);
	}

	/**
	 * Helper to make it easy to return a new post reply that only contains the specified post.
	 *
	 * Though this is appropriate in other orders/filtered views, note that it will still set the
	 * "lastDate" to the post's date.
	 *
	 * @param Thread $thread
	 * @param Post $post
	 *
	 * @return View
	 */
	protected function getSingleNewPostReply(
		Thread $thread,
		Post $post
	): AbstractReply
	{
		return $this->getNewPostsReplyInternal(
			$thread,
			$this->em()->getBasicCollection([$post->post_id => $post])
		);
	}

	protected function getNewPostsReplyInternal(
		Thread $thread,
		AbstractCollection $posts,
		?Post $firstUnshownPost = null
	)
	{
		$threadPlugin = $this->plugin(ThreadPlugin::class);
		$threadPlugin->fetchExtraContentForPostsFullView($posts, $thread);

		/** @var UserAlertRepository $userAlertRepo */
		$userAlertRepo = $this->repository(UserAlertRepository::class);
		$userAlertRepo->markUserAlertsReadForContent('post', $posts->keys());

		$last = $posts->last();
		$lastDate = $last ? $last->post_date : null;

		$viewParams = [
			'thread' => $thread,
			'posts' => $posts,
			'firstUnshownPost' => $firstUnshownPost,
			'templateOverrides' => $thread->TypeHandler->getThreadViewTemplateOverrides($thread),
		];
		$view = $this->view('XF:Thread\NewPosts', 'thread_new_posts', $viewParams);
		$view->setJsonParam('lastDate', $lastDate);
		return $view;
	}

	public function actionReplyPreview(ParameterBag $params)
	{
		$this->assertPostOnly();

		$thread = $this->assertViewableThread($params->thread_id);
		if (!$thread->canReply($error) && !$thread->canReplyPreReg())
		{
			return $this->noPermission($error);
		}

		$replier = $this->setupThreadReply($thread);
		if (!$replier->validate($errors))
		{
			return $this->error($errors);
		}

		$post = $replier->getPost();
		$attachments = [];

		$tempHash = $this->filter('attachment_hash', 'str');
		if ($tempHash && $thread->Forum->canUploadAndManageAttachments())
		{
			$attachRepo = $this->repository(AttachmentRepository::class);
			$attachments = $attachRepo->findAttachmentsByTempHash($tempHash)->fetch();
		}

		return $this->plugin(BbCodePreviewPlugin::class)->actionPreview(
			$post->message,
			'post',
			$post->User,
			$attachments,
			$thread->canViewAttachments()
		);
	}

	public function actionMultiQuote()
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
		if (!$quotes)
		{
			return $this->error(\XF::phrase('no_messages_selected'));
		}

		$postFinder = $this->finder(PostFinder::class);

		$posts = $postFinder
			->with(['User', 'Thread', 'Thread.Forum'])
			->where('post_id', array_keys($quotes))
			->order('post_date', 'DESC')
			->fetch()
			->filterViewable();

		if ($this->request->exists('insert'))
		{
			$insertOrder = $this->filter('insert', 'array');
			return $quotePlugin->actionMultiQuote($posts, $insertOrder, $quotes, 'post');
		}
		else
		{
			$viewParams = [
				'quotes' => $quotes,
				'posts' => $posts,
			];
			return $this->view('XF:Thread\MultiQuote', 'thread_multi_quote', $viewParams);
		}
	}

	/**
	 * @param Thread $thread
	 *
	 * @return EditorService
	 */
	protected function setupThreadEdit(Thread $thread)
	{
		$editor = $this->getEditorService($thread);

		if ($thread->isPrefixEditable())
		{
			$prefixId = $this->filter('prefix_id', 'uint');
			if ($prefixId != $thread->prefix_id && !$thread->Forum->isPrefixUsable($prefixId))
			{
				$prefixId = 0; // not usable, just blank it out
			}
			$editor->setPrefix($prefixId);
		}

		$editor->setTitle($this->filter('title', 'str'));

		$canLockUnlock = $thread->canLockUnlock();
		if ($canLockUnlock)
		{
			$editor->setDiscussionOpen($this->filter('discussion_open', 'bool'));
		}

		$canStickUnstick = $thread->canStickUnstick($error);
		if ($canStickUnstick)
		{
			$editor->setSticky($this->filter('sticky', 'bool'));
		}

		if ($thread->canManageSearchEngineIndexing($error))
		{
			$editor->setIndexState($this->filter('index_state', 'str'));
		}

		$customFields = $this->filter('custom_fields', 'array');
		$editor->setCustomFields($customFields);

		$editor->setDiscussionTypeData($this->request);

		return $editor;
	}

	public function actionEdit(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);
		if (!$thread->canEdit($error))
		{
			return $this->noPermission($error);
		}
		$forum = $thread->Forum;

		$noInlineMod = $this->filter('_xfNoInlineMod', 'bool');
		$forumName = $this->filter('_xfForumName', 'bool');

		if ($this->isPost())
		{
			$editor = $this->setupThreadEdit($thread);

			if (!$editor->validate($errors))
			{
				return $this->error($errors);
			}

			$editor->save();

			if ($this->filter('_xfWithData', 'bool') && $this->filter('_xfInlineEdit', 'bool'))
			{
				$viewParams = [
					'thread' => $thread,
					'forum' => $forum,

					'noInlineMod' => $noInlineMod,
					'forumName' => $forumName,

					'templateOverrides' => $forumName ? [] : $forum->TypeHandler->getForumViewTemplateOverrides($forum),
				];
				$reply = $this->view('XF:Thread\EditInline', 'thread_edit_new_thread', $viewParams);
				$reply->setJsonParam('message', \XF::phrase('your_changes_have_been_saved'));
				return $reply;
			}
			return $this->redirect($this->buildLink('threads', $thread));
		}

		$prefix = $thread->Prefix;
		$prefixes = $forum->getUsablePrefixes($prefix);

		$viewParams = [
			'thread' => $thread,
			'forum' => $forum,
			'prefixes' => $prefixes,

			'noInlineMod' => $noInlineMod,
			'forumName' => $forumName,
		];

		return $this->view('XF:Thread\Edit', 'thread_edit', $viewParams);
	}

	public function actionQuickClose(ParameterBag $params)
	{
		$this->assertPostOnly();

		$thread = $this->assertViewableThread($params->thread_id);
		if (!$thread->canLockUnlock($error))
		{
			return $this->noPermission($error);
		}

		$editor = $this->getEditorService($thread);

		if ($thread->discussion_open)
		{
			$editor->setDiscussionOpen(false);
			$text = \XF::phrase('unlock_thread');
		}
		else
		{
			$editor->setDiscussionOpen(true);
			$text = \XF::phrase('lock_thread');
		}

		if (!$editor->validate($errors))
		{
			return $this->error($errors);
		}

		$editor->save();

		$reply = $this->redirect($this->getDynamicRedirect());
		$reply->setJsonParams([
			'text' => $text,
			'discussion_open' => $thread->discussion_open,
		]);
		return $reply;
	}

	public function actionQuickStick(ParameterBag $params)
	{
		$this->assertPostOnly();

		$thread = $this->assertViewableThread($params->thread_id);
		if (!$thread->canStickUnstick($error))
		{
			return $this->noPermission($error);
		}

		$editor = $this->getEditorService($thread);

		if ($thread->sticky)
		{
			$editor->setSticky(false);
			$text = \XF::phrase('stick_thread');
		}
		else
		{
			$editor->setSticky(true);
			$text = \XF::phrase('unstick_thread');
		}

		if (!$editor->validate($errors))
		{
			return $this->error($errors);
		}

		$editor->save();

		$reply = $this->redirect($this->getDynamicRedirect());
		$reply->setJsonParams([
			'text' => $text,
			'sticky' => $thread->sticky,
		]);
		return $reply;
	}

	public function actionFeature(ParameterBag $params): AbstractReply
	{
		$thread = $this->assertViewableThread($params->thread_id, ['Feature']);
		$breadcrumbs = $thread->getBreadcrumbs();
		$confirmUrl = $this->buildLink('threads/feature', $thread);
		$deleteUrl = $this->buildLink('threads/unfeature', $thread);

		/** @var FeaturedContentPlugin $featurePlugin */
		$featurePlugin = $this->plugin(FeaturedContentPlugin::class);
		return $featurePlugin->actionFeature(
			$thread,
			$breadcrumbs,
			$confirmUrl,
			$deleteUrl
		);
	}

	public function actionUnfeature(ParameterBag $params): AbstractReply
	{
		$thread = $this->assertViewableThread($params->thread_id, ['Feature']);
		$breadcrumbs = $thread->getBreadcrumbs();
		$confirmUrl = $this->buildLink('threads/unfeature', $thread);

		/** @var FeaturedContentPlugin $featurePlugin */
		$featurePlugin = $this->plugin(FeaturedContentPlugin::class);
		return $featurePlugin->actionUnfeature(
			$thread,
			$breadcrumbs,
			$confirmUrl
		);
	}

	public function actionVote(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);

		/** @var ContentVotePlugin $votePlugin */
		$votePlugin = $this->plugin(ContentVotePlugin::class);

		return $votePlugin->actionVote(
			$thread,
			$this->buildLink('threads', $thread),
			$this->buildLink('threads/vote', $thread)
		);
	}

	public function actionPollCreate(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);

		$breadcrumbs = $thread->getBreadcrumbs();

		/** @var PollPlugin $pollPlugin */
		$pollPlugin = $this->plugin(PollPlugin::class);
		return $pollPlugin->actionCreate('thread', $thread, $breadcrumbs);
	}

	public function actionPollEdit(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);
		$poll = $thread->Poll;

		$breadcrumbs = $thread->getBreadcrumbs();

		/** @var PollPlugin $pollPlugin */
		$pollPlugin = $this->plugin(PollPlugin::class);
		return $pollPlugin->actionEdit($poll, $breadcrumbs);
	}

	public function actionPollDelete(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);
		$poll = $thread->Poll;

		$breadcrumbs = $thread->getBreadcrumbs();

		/** @var PollPlugin $pollPlugin */
		$pollPlugin = $this->plugin(PollPlugin::class);
		return $pollPlugin->actionDelete($poll, $breadcrumbs);
	}

	public function actionPollVote(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);
		$poll = $thread->Poll;

		$breadcrumbs = $thread->getBreadcrumbs();

		/** @var PollPlugin $pollPlugin */
		$pollPlugin = $this->plugin(PollPlugin::class);
		return $pollPlugin->actionVote($poll, $breadcrumbs);
	}

	public function actionPollResults(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);
		$poll = $thread->Poll;

		$breadcrumbs = $thread->getBreadcrumbs();

		/** @var PollPlugin $pollPlugin */
		$pollPlugin = $this->plugin(PollPlugin::class);
		return $pollPlugin->actionResults($poll, $breadcrumbs);
	}

	public function actionDelete(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);
		if (!$thread->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$type = $this->filter('hard_delete', 'bool') ? 'hard' : 'soft';
			$reason = $this->filter('reason', 'str');

			if (!$thread->canDelete($type, $error))
			{
				return $this->noPermission($error);
			}

			/** @var DeleterService $deleter */
			$deleter = $this->service(DeleterService::class, $thread);

			if ($this->filter('starter_alert', 'bool'))
			{
				$deleter->setSendAlert(true, $this->filter('starter_alert_reason', 'str'));
			}

			$deleter->delete($type, $reason);

			$this->plugin(InlineModPlugin::class)->clearIdFromCookie('thread', $thread->thread_id);

			return $this->redirect($this->buildLink('forums', $thread->Forum));
		}
		else
		{
			$viewParams = [
				'thread' => $thread,
				'forum' => $thread->Forum,
			];
			return $this->view('XF:Thread\Delete', 'thread_delete', $viewParams);
		}
	}

	public function actionUndelete(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);

		/** @var UndeletePlugin $plugin */
		$plugin = $this->plugin(UndeletePlugin::class);
		return $plugin->actionUndelete(
			$thread,
			$this->buildLink('threads/undelete', $thread),
			$this->buildLink('threads', $thread),
			$thread->title,
			'discussion_state'
		);
	}

	public function actionApprove(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);
		if (!$thread->canApproveUnapprove($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			/** @var ApproverService $approver */
			$approver = \XF::service(ApproverService::class, $thread);
			$approver->approve();

			return $this->redirect($this->buildLink('threads', $thread));
		}
		else
		{
			$viewParams = [
				'thread' => $thread,
				'forum' => $thread->Forum,
			];
			return $this->view('XF:Thread\Approve', 'thread_approve', $viewParams);
		}
	}

	/**
	 * @param Thread $thread
	 * @param Forum $forum
	 *
	 * @return MoverService
	 */
	protected function setupThreadMove(Thread $thread, Forum $forum)
	{
		$options = $this->filter([
			'notify_watchers' => 'bool',
			'starter_alert' => 'bool',
			'starter_alert_reason' => 'str',
			'prefix_id' => 'uint',
		]);

		$redirectType = $this->filter('redirect_type', 'str');
		if ($redirectType == 'permanent')
		{
			$options['redirect'] = true;
			$options['redirect_length'] = 0;
		}
		else if ($redirectType == 'temporary')
		{
			$options['redirect'] = true;
			$options['redirect_length'] = $this->filter('redirect_length', 'timeoffset');
		}
		else
		{
			$options['redirect'] = false;
			$options['redirect_length'] = 0;
		}

		/** @var MoverService $mover */
		$mover = $this->service(MoverService::class, $thread);

		if ($options['starter_alert'])
		{
			$mover->setSendAlert(true, $options['starter_alert_reason']);
		}

		if ($options['notify_watchers'])
		{
			$mover->setNotifyWatchers();
		}

		if ($options['redirect'])
		{
			$mover->setRedirect(true, $options['redirect_length']);
		}

		if ($options['prefix_id'] !== null)
		{
			$mover->setPrefix($options['prefix_id']);
		}

		$mover->addExtraSetup(function ($thread, $forum)
		{
			$thread->title = $this->filter('title', 'str');
		});

		return $mover;
	}

	public function actionMove(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);
		if (!$thread->canMove($error))
		{
			return $this->noPermission($error);
		}
		$forum = $thread->Forum;

		if ($this->isPost())
		{
			$targetNodeId = $this->filter('target_node_id', 'uint');

			/** @var Forum $targetForum */
			$targetForum = $this->app()->em()->find(Forum::class, $targetNodeId);
			if (!$targetForum || !$targetForum->canView())
			{
				return $this->error(\XF::phrase('requested_forum_not_found'));
			}

			$this->setupThreadMove($thread, $targetForum)->move($targetForum);

			return $this->redirect($this->buildLink('threads', $thread));
		}
		else
		{
			/** @var NodeRepository $nodeRepo */
			$nodeRepo = $this->app()->repository(NodeRepository::class);
			$nodes = $nodeRepo->getFullNodeList()->filterViewable();

			$viewParams = [
				'thread' => $thread,
				'forum' => $forum,
				'prefixes' => $forum->getUsablePrefixes($thread->Prefix),
				'nodeTree' => $nodeRepo->createNodeTree($nodes),
			];
			return $this->view('XF:Thread\Move', 'thread_move', $viewParams);
		}
	}

	public function actionMoveWarnings(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);
		if (!$thread->canMove($error))
		{
			return $this->noPermission($error);
		}

		$targetNodeId = $this->filter('content', 'uint');

		if ($thread->node_id == $targetNodeId)
		{
			// if we're not moving, then don't trigger any warnings
			return $this->getMoveWarningsView([], $targetNodeId);
		}

		/** @var Forum $targetForum */
		$targetForum = $this->app()->em()->find(Forum::class, $targetNodeId);
		if (!$targetForum || !$targetForum->canView())
		{
			return $this->error(\XF::phrase('requested_forum_not_found'));
		}

		$warnings = [];

		if (
			$thread->discussion_type !== AbstractHandler::BASIC_THREAD_TYPE
			&& !$targetForum->TypeHandler->isThreadTypeAllowed($thread->discussion_type, $targetForum))
		{
			$warnings[] = \XF::phrase('thread_move_type_change_warning');
		}

		return $this->getMoveWarningsView($warnings, $targetNodeId);
	}

	protected function getMoveWarningsView(array $errors, $targetNodeId)
	{
		$view = $this->view();
		$view->setJsonParams([
			'inputValid' => !count($errors),
			'inputErrors' => $errors,
			'validatedValue' => strval($targetNodeId),
		]);
		return $view;
	}

	public function actionChangeType(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);
		if (!$thread->canChangeType($error))
		{
			return $this->noPermission($error);
		}

		$forum = $thread->Forum;

		$newThreadTypeId = $this->filter('new_thread_type_id', 'str');
		$newThreadType = $newThreadTypeId ? $this->app()->threadType($newThreadTypeId, false) : null;

		$currentThreadType = $thread->TypeHandler;

		if ($newThreadType && $newThreadType->getTypeId() == $currentThreadType->getTypeId())
		{
			return $this->error(\XF::phrase('thread_is_already_that_type'));
		}

		$viewParams = [
			'thread' => $thread,
			'forum' => $forum,

			'currentThreadTypeId' => $currentThreadType->getTypeId(),
			'currentThreadTypeTitle' => $currentThreadType->getTypeTitle(),
		];

		if ($newThreadType && $this->isPost() && $this->filter('confirm', 'bool'))
		{
			/** @var ChangeTypeService $typeChanger */
			$typeChanger = $this->service(ChangeTypeService::class, $thread);
			$typeChanger->setDiscussionTypeAndData($newThreadType->getTypeId(), $this->request());

			if (!$typeChanger->validate($errors))
			{
				return $this->error($errors);
			}

			$typeChanger->save();

			return $this->redirect($this->buildLink('threads', $thread));
		}
		else if ($newThreadType)
		{
			$viewParams += [
				'isTypeChange' => true,
				'newThreadTypeId' => $newThreadTypeId,
				'newThreadTypeTitle' => $newThreadType->getTypeTitle(),
				'newThreadType' => $newThreadType,
			];
			return $this->view('XF:Thread\ChangeType', 'thread_change_type', $viewParams);
		}
		else
		{
			$creatableThreadTypes = $this->repository(ThreadTypeRepository::class)->getThreadTypeListData(
				$forum->getCreatableThreadTypes(),
				ThreadTypeRepository::FILTER_SINGLE_CONVERTIBLE
			);

			if (count($creatableThreadTypes) <= 1)
			{
				return $this->error(\XF::phrase('no_other_thread_types_available_cannot_change'));
			}

			$viewParams['creatableThreadTypes'] = $creatableThreadTypes;

			return $this->view('XF:Thread\ChangeType', 'thread_change_type', $viewParams);
		}
	}

	public function actionTags(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);
		if (!$thread->canEditTags($error))
		{
			return $this->noPermission($error);
		}

		/** @var ChangerService $tagger */
		$tagger = $this->service(ChangerService::class, 'thread', $thread);

		if ($this->isPost())
		{
			$tagger->setEditableTags($this->filter('tags', 'str'));
			if ($tagger->hasErrors())
			{
				return $this->error($tagger->getErrors());
			}

			$tagger->save();

			if ($this->filter('_xfInlineEdit', 'bool'))
			{
				$viewParams = [
					'thread' => $thread,
				];
				$reply = $this->view('XF:Thread\TagsInline', 'thread_tags_list', $viewParams);
				$reply->setJsonParam('message', \XF::phrase('your_changes_have_been_saved'));
				return $reply;
			}
			else
			{
				return $this->redirect($this->buildLink('threads', $thread));
			}
		}
		else
		{
			$grouped = $tagger->getExistingTagsByEditability();

			$viewParams = [
				'thread'         => $thread,
				'forum'          => $thread->Forum,
				'editableTags'   => $grouped['editable'],
				'uneditableTags' => $grouped['uneditable'],
			];

			return $this->view('XF:Thread\Tags', 'thread_tags', $viewParams);
		}
	}

	public function actionWatch(ParameterBag $params)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return $this->noPermission();
		}

		$thread = $this->assertViewableThread($params->thread_id);
		if (!$thread->canWatch($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			if ($this->filter('stop', 'bool'))
			{
				$newState = 'delete';
			}
			else if ($this->filter('email_subscribe', 'bool'))
			{
				$newState = 'watch_email';
			}
			else
			{
				$newState = 'watch_no_email';
			}

			/** @var ThreadWatchRepository $watchRepo */
			$watchRepo = $this->repository(ThreadWatchRepository::class);
			$watchRepo->setWatchState($thread, $visitor, $newState);

			$redirect = $this->redirect($this->buildLink('threads', $thread));
			$redirect->setJsonParam('switchKey', $newState == 'delete' ? 'watch' : 'unwatch');
			return $redirect;
		}
		else
		{
			$viewParams = [
				'thread' => $thread,
				'isWatched' => !empty($thread->Watch[$visitor->user_id]),
				'forum' => $thread->Forum,
			];
			return $this->view('XF:Thread\Watch', 'thread_watch', $viewParams);
		}
	}

	/**
	 * @param Thread $thread
	 *
	 * @return ReplyBanService|null
	 */
	protected function setupThreadReplyBan(Thread $thread)
	{
		$input = $this->filter([
			'username' => 'str',
			'ban_length' => 'str',
			'ban_length_value' => 'uint',
			'ban_length_unit' => 'str',

			'send_alert' => 'bool',
			'reason' => 'str',
		]);

		if ($input['username'] === '')
		{
			return null;
		}

		/** @var User $user */
		$user = $this->finder(UserFinder::class)->where('username', $input['username'])->fetchOne();
		if (!$user)
		{
			throw $this->exception(
				$this->notFound(\XF::phrase('requested_user_x_not_found', ['name' => $input['username']]))
			);
		}

		/** @var ReplyBanService $replyBanService */
		$replyBanService = $this->service(ReplyBanService::class, $thread, $user);

		if ($input['ban_length'] == 'temporary')
		{
			$replyBanService->setExpiryDate($input['ban_length_unit'], $input['ban_length_value']);
		}
		else
		{
			$replyBanService->setExpiryDate(0);
		}

		$replyBanService->setSendAlert($input['send_alert']);
		$replyBanService->setReason($input['reason']);

		return $replyBanService;
	}

	public function actionReplyBans(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);
		if (!$thread->canReplyBan($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$delete = $this->filter('delete', 'array-bool');
			$delete = array_filter($delete);

			$replyBanService = $this->setupThreadReplyBan($thread);
			if ($replyBanService)
			{
				if (!$replyBanService->validate($errors))
				{
					return $this->error($errors);
				}

				$replyBanService->save();

				// don't try to delete the record we just added
				unset($delete[$replyBanService->getUser()->user_id]);
			}

			if ($delete)
			{
				$replyBans = $thread->ReplyBans;
				foreach (array_keys($delete) AS $userId)
				{
					if (isset($replyBans[$userId]))
					{
						$replyBans[$userId]->delete();
					}
				}
			}

			return $this->redirect($this->getDynamicRedirect($this->buildLink('threads', $thread), false));
		}
		else
		{
			/** @var ThreadReplyBanRepository $replyBanRepo */
			$replyBanRepo = $this->repository(ThreadReplyBanRepository::class);
			$replyBanFinder = $replyBanRepo->findReplyBansForThread($thread)->order('ban_date');

			$viewParams = [
				'thread' => $thread,
				'forum' => $thread->Forum,
				'bans' => $replyBanFinder->fetch(),
			];
			return $this->view('XF:Thread\ReplyBans', 'thread_reply_bans', $viewParams);
		}
	}

	public function actionModeratorActions(ParameterBag $params)
	{
		$thread = $this->assertViewableThread($params->thread_id);
		if (!$thread->canViewModeratorLogs($error))
		{
			return $this->noPermission($error);
		}

		$breadcrumbs = $thread->getBreadcrumbs();
		$prefix = $this->app()->templater()->func('prefix', ['thread', $thread, 'escaped']);
		$title = $prefix . $thread->title;

		$this->request()->set('page', $params->page);

		/** @var ModeratorLogPlugin $modLogPlugin */
		$modLogPlugin = $this->plugin(ModeratorLogPlugin::class);
		return $modLogPlugin->actionModeratorActions(
			$thread,
			['threads/moderator-actions', $thread],
			$title,
			$breadcrumbs
		);
	}

	/**
	 * @param $threadId
	 * @param array $extraWith
	 *
	 * @return Thread
	 *
	 * @throws Exception
	 */
	protected function assertViewableThread($threadId, array $extraWith = [])
	{
		$visitor = \XF::visitor();

		$extraWith[] = 'Forum';
		$extraWith[] = 'Forum.Node';
		$extraWith[] = 'Forum.Node.Permissions|' . $visitor->permission_combination_id;
		if ($visitor->user_id)
		{
			$extraWith[] = 'Read|' . $visitor->user_id;
			$extraWith[] = 'Forum.Read|' . $visitor->user_id;
		}

		if ($visitor->canTriggerPreRegAction())
		{
			$extraWith[] = 'Forum.Node.Permissions|' . \XF::options()->preRegAction['permissionCombinationId'];
		}

		/** @var Thread $thread */
		$thread = $this->em()->find(Thread::class, $threadId, $extraWith);
		if (!$thread)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_thread_not_found')));
		}

		if (!$thread->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		$this->plugin(NodePlugin::class)->applyNodeContext($thread->Forum->Node);
		$this->setContentKey('thread-' . $thread->thread_id);

		return $thread;
	}

	protected function getReplyAttachmentData(Thread $thread, $forceAttachmentHash = null)
	{
		/** @var Forum $forum */
		$forum = $thread->Forum;

		if ($forum && $forum->canUploadAndManageAttachments())
		{
			if ($forceAttachmentHash !== null)
			{
				$attachmentHash = $forceAttachmentHash;
			}
			else
			{
				$attachmentHash = $thread->draft_reply->attachment_hash;
			}

			/** @var AttachmentRepository $attachmentRepo */
			$attachmentRepo = $this->repository(AttachmentRepository::class);
			return $attachmentRepo->getEditorData('post', $thread, $attachmentHash);
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

	public static function getActivityDetails(array $activities)
	{
		return self::getActivityDetailsForContent(
			$activities,
			\XF::phrase('viewing_thread'),
			'thread_id',
			function (array $ids)
			{
				$threads = \XF::em()->findByIds(
					ThreadFinder::class,
					$ids,
					['Forum', 'Forum.Node', 'Forum.Node.Permissions|' . \XF::visitor()->permission_combination_id]
				);

				$router = \XF::app()->router('public');
				$data = [];

				foreach ($threads->filterViewable() AS $id => $thread)
				{
					$data[$id] = [
						'title' => $thread->title,
						'url' => $router->buildLink('threads', $thread),
					];
				}

				return $data;
			}
		);
	}

	public static function getResolvableActions(): array
	{
		return ['index', 'post'];
	}

	public static function resolveToEmbeddableContent(ParameterBag $params, RouteMatch $routeMatch): ?Entity
	{
		$content = null;

		if ($params->post_id && $params->thread_id)
		{
			$content = \XF::em()->find(Post::class, $params->post_id);
		}
		else if ($routeMatch->getAnchor() && preg_match('/^#post-(\d+)$/', $routeMatch->getAnchor(), $match))
		{
			$content = \XF::em()->find(Post::class, $match[1]);
		}
		else if ($params->thread_id)
		{
			$content = \XF::em()->find(Thread::class, $params->thread_id);
		}

		if (!$content || !$content->canView())
		{
			$content = null;
		}

		return $content;
	}

	/**
	 * @return ThreadRepository
	 */
	protected function getThreadRepo()
	{
		return $this->repository(ThreadRepository::class);
	}

	/**
	 * @return PostRepository
	 */
	protected function getPostRepo()
	{
		return $this->repository(PostRepository::class);
	}

	/**
	 * @param Thread $thread
	 *
	 * @return EditorService $editor
	 */
	protected function getEditorService(Thread $thread)
	{
		return $this->service(EditorService::class, $thread);
	}
}
