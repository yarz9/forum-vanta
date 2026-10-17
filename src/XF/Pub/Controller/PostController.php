<?php

namespace XF\Pub\Controller;

use XF\ControllerPlugin\BbCodePreviewPlugin;
use XF\ControllerPlugin\BookmarkPlugin;
use XF\ControllerPlugin\ContentVotePlugin;
use XF\ControllerPlugin\EditorPlugin;
use XF\ControllerPlugin\InlineModPlugin;
use XF\ControllerPlugin\IpPlugin;
use XF\ControllerPlugin\NodePlugin;
use XF\ControllerPlugin\QuotePlugin;
use XF\ControllerPlugin\ReactionPlugin;
use XF\ControllerPlugin\ReportPlugin;
use XF\ControllerPlugin\SharePlugin;
use XF\ControllerPlugin\ThreadPlugin;
use XF\ControllerPlugin\UndeletePlugin;
use XF\ControllerPlugin\WarnPlugin;
use XF\Entity\Forum;
use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Finder\PostFinder;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Mvc\RouteMatch;
use XF\Repository\AttachmentRepository;
use XF\Repository\PostRepository;
use XF\Repository\ThreadRepository;
use XF\Service\Post\DeleterService;
use XF\Service\Post\EditorService;
use XF\Service\ThreadQuestion\MarkSolutionService;

class PostController extends AbstractController
{
	use EmbedResolverTrait;

	public function actionIndex(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id);

		return $this->redirectPermanently($this->plugin(ThreadPlugin::class)->getPostLink($post));
	}

	public function actionShow(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id);

		$thread = $post->Thread;
		$typeHandler = $thread->TypeHandler;

		$viewParams = [
			'post' => $post,
			'thread' => $thread,
			'forum' => $thread->Forum,
			'canInlineMod' => $post->canUseInlineModeration(),

			'isPinnedFirstPost' => $post->isFirstPost() && $typeHandler->isFirstPostPinned($thread),
			'templateOverrides' => $typeHandler->getThreadViewTemplateOverrides($thread),
		];
		return $this->view('XF:Post\Show', 'post', $viewParams);
	}

	/**
	 * @param Post $post
	 *
	 * @return EditorService
	 */
	protected function setupPostEdit(Post $post)
	{
		$message = $this->plugin(EditorPlugin::class)->fromInput('message');

		/** @var EditorService $editor */
		$editor = $this->service(EditorService::class, $post);
		if ($post->canEditSilently())
		{
			$silentEdit = $this->filter('silent', 'bool');
			if ($silentEdit)
			{
				$editor->logEdit(false);
				if ($this->filter('clear_edit', 'bool'))
				{
					$post->last_edit_date = 0;
				}
			}
		}
		$editor->setMessage($message);

		$forum = $post->Thread->Forum;
		if ($forum->canUploadAndManageAttachments())
		{
			$editor->setAttachmentHash($this->filter('attachment_hash', 'str'));
		}

		if ($this->filter('author_alert', 'bool') && $post->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		return $editor;
	}

	/**
	 * @param Thread $thread
	 * @param array $threadChanges Returns a list of whether certain important thread fields are changed
	 *
	 * @return \XF\Service\Thread\EditorService
	 */
	protected function setupFirstPostThreadEdit(Thread $thread, &$threadChanges)
	{
		/** @var \XF\Service\Thread\EditorService $threadEditor */
		$threadEditor = $this->service(\XF\Service\Thread\EditorService::class, $thread);

		if ($thread->isPrefixEditable())
		{
			$prefixId = $this->filter('prefix_id', 'uint');
			if ($prefixId != $thread->prefix_id && !$thread->Forum->isPrefixUsable($prefixId))
			{
				$prefixId = 0; // not usable, just blank it out
			}
			$threadEditor->setPrefix($prefixId);
		}

		$threadEditor->setTitle($this->filter('title', 'str'));

		$customFields = $this->filter('custom_fields', 'array');
		$threadEditor->setCustomFields($customFields);

		$threadEditor->setDiscussionTypeData($this->request);

		$threadChanges = [
			'title' => $thread->isChanged(['title', 'prefix_id']),
			'customFields' => $thread->isChanged('custom_fields'),
		];

		return $threadEditor;
	}

	protected function finalizePostEdit(EditorService $editor, ?\XF\Service\Thread\EditorService $threadEditor = null)
	{

	}

	public function actionEdit(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id, ['Thread.Prefix']);
		if (!$post->canEdit($error))
		{
			return $this->noPermission($error);
		}

		$thread = $post->Thread;

		if ($this->isPost())
		{
			$editor = $this->setupPostEdit($post);
			$editor->checkForSpam();

			if ($post->isFirstPost() && $thread->canEdit())
			{
				$threadEditor = $this->setupFirstPostThreadEdit($thread, $threadChanges);
				$editor->setThreadEditor($threadEditor);
			}
			else
			{
				$threadEditor = null;
				$threadChanges = [];
			}

			if (!$editor->validate($errors))
			{
				return $this->error($errors);
			}

			$editor->save();

			$this->finalizePostEdit($editor, $threadEditor);

			if ($this->filter('_xfWithData', 'bool') && $this->filter('_xfInlineEdit', 'bool'))
			{
				$threadPlugin = $this->plugin(ThreadPlugin::class);
				$threadPlugin->fetchExtraContentForPostsFullView([$post->post_id => $post], $thread);

				$typeHandler = $thread->TypeHandler;

				$viewParams = [
					'post' => $post,
					'thread' => $thread,
					'isPinnedFirstPost' => $post->isFirstPost() && $typeHandler->isFirstPostPinned($thread),
					'templateOverrides' => $typeHandler->getThreadViewTemplateOverrides($thread),
				];

				$reply = $this->view('XF:Post\EditNewPost', 'post_edit_new_post', $viewParams);
				$reply->setJsonParams([
					'message' => \XF::phrase('your_changes_have_been_saved'),
					'threadChanges' => $threadChanges,
				]);
				return $reply;
			}
			else
			{
				return $this->redirect($this->buildLink('posts', $post));
			}
		}
		else
		{
			/** @var Forum $forum */
			$forum = $post->Thread->Forum;
			if ($forum->canUploadAndManageAttachments())
			{
				/** @var AttachmentRepository $attachmentRepo */
				$attachmentRepo = $this->repository(AttachmentRepository::class);
				$attachmentData = $attachmentRepo->getEditorData('post', $post);
			}
			else
			{
				$attachmentData = null;
			}

			$prefix = $thread->Prefix;
			$prefixes = $forum->getUsablePrefixes($prefix);

			$viewParams = [
				'post' => $post,
				'thread' => $thread,
				'forum' => $forum,
				'prefixes' => $prefixes,
				'attachmentData' => $attachmentData,
				'quickEdit' => $this->filter('_xfWithData', 'bool'),
			];
			return $this->view('XF:Post\Edit', 'post_edit', $viewParams);
		}
	}

	public function actionPreview(ParameterBag $params)
	{
		$this->assertPostOnly();

		$post = $this->assertViewablePost($params->post_id);
		if (!$post->canEdit($error))
		{
			return $this->noPermission($error);
		}

		$thread = $post->Thread;

		$editor = $this->setupPostEdit($post);

		if (!$editor->validate($errors))
		{
			return $this->error($errors);
		}

		$attachments = [];
		$tempHash = $this->filter('attachment_hash', 'str');

		if ($thread->Forum->canUploadAndManageAttachments())
		{
			/** @var AttachmentRepository $attachmentRepo */
			$attachmentRepo = $this->repository(AttachmentRepository::class);
			$attachmentData = $attachmentRepo->getEditorData('post', $post, $tempHash);
			$attachments = $attachmentData['attachments'];
		}

		return $this->plugin(BbCodePreviewPlugin::class)->actionPreview(
			$post->message,
			'post',
			$post->User,
			$attachments,
			$thread->canViewAttachments()
		);
	}

	public function actionDelete(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id);
		if (!$post->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$type = $this->filter('hard_delete', 'bool') ? 'hard' : 'soft';
			$reason = $this->filter('reason', 'str');

			if (!$post->canDelete($type, $error))
			{
				return $this->noPermission($error);
			}

			/** @var Thread $thread */
			$thread = $post->Thread;

			/** @var DeleterService $deleter */
			$deleter = $this->service(DeleterService::class, $post);

			if ($this->filter('author_alert', 'bool') && $post->canSendModeratorActionAlert())
			{
				$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
			}

			$deleter->delete($type, $reason);

			$this->plugin(InlineModPlugin::class)->clearIdFromCookie('post', $post->post_id);

			if ($deleter->wasThreadDeleted())
			{
				$this->plugin(InlineModPlugin::class)->clearIdFromCookie('thread', $post->thread_id);

				return $this->redirect(
					$thread && $thread->Forum
						? $this->buildLink('forums', $thread->Forum)
						: $this->buildLink('index')
				);
			}
			else
			{
				return $this->redirect(
					$this->getDynamicRedirect($this->buildLink('threads', $thread), false)
				);
			}
		}
		else
		{
			$viewParams = [
				'post' => $post,
				'thread' => $post->Thread,
				'forum' => $post->Thread->Forum,
			];
			return $this->view('XF:Post\Delete', 'post_delete', $viewParams);
		}
	}

	public function actionUndelete(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id);

		/** @var UndeletePlugin $plugin */
		$plugin = $this->plugin(UndeletePlugin::class);
		return $plugin->actionUndelete(
			$post,
			$this->buildLink('posts/undelete', $post),
			$post->getContentUrl(),
			$post->getContentTitle('undelete'),
			'message_state'
		);
	}

	public function actionIp(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id);
		$breadcrumbs = $post->Thread->getBreadcrumbs();

		/** @var IpPlugin $ipPlugin */
		$ipPlugin = $this->plugin(IpPlugin::class);
		return $ipPlugin->actionIp($post, $breadcrumbs);
	}

	public function actionReport(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id);
		if (!$post->canReport($error))
		{
			return $this->noPermission($error);
		}

		/** @var ReportPlugin $reportPlugin */
		$reportPlugin = $this->plugin(ReportPlugin::class);
		return $reportPlugin->actionReport(
			'post',
			$post,
			$this->buildLink('posts/report', $post),
			$this->buildLink('posts', $post)
		);
	}

	public function actionQuote(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id);
		if (!$post->Thread->canReply($error) && !$post->Thread->canReplyPreReg())
		{
			return $this->noPermission($error);
		}

		return $this->plugin(QuotePlugin::class)->actionQuote($post, 'post');
	}

	public function actionHistory(ParameterBag $params)
	{
		return $this->rerouteController(EditHistoryController::class, 'index', [
			'content_type' => 'post',
			'content_id' => $params->post_id,
		]);
	}

	public function actionBookmark(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id);

		/** @var BookmarkPlugin $bookmarkPlugin */
		$bookmarkPlugin = $this->plugin(BookmarkPlugin::class);

		return $bookmarkPlugin->actionBookmark(
			$post,
			$this->buildLink('posts/bookmark', $post)
		);
	}

	public function actionReact(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id);

		/** @var ReactionPlugin $reactionPlugin */
		$reactionPlugin = $this->plugin(ReactionPlugin::class);
		return $reactionPlugin->actionReactSimple($post, 'posts');
	}

	public function actionReactions(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id);

		$breadcrumbs = $post->Thread->getBreadcrumbs();
		$title = \XF::phrase('members_who_reacted_to_message_x', ['position' => ($post->position + 1)]);

		/** @var ReactionPlugin $reactionPlugin */
		$reactionPlugin = $this->plugin(ReactionPlugin::class);
		return $reactionPlugin->actionReactions(
			$post,
			'posts/reactions',
			$title,
			$breadcrumbs
		);
	}

	public function actionVote(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id);

		/** @var ContentVotePlugin $votePlugin */
		$votePlugin = $this->plugin(ContentVotePlugin::class);

		return $votePlugin->actionVote(
			$post,
			$this->buildLink('posts', $post),
			$this->buildLink('posts/vote', $post)
		);
	}

	public function actionMarkSolution(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id);

		if (!$post->canMarkAsQuestionSolution($error))
		{
			return $this->noPermission($error);
		}

		$thread = $post->Thread;
		$existingSolution = $thread->Question->Solution ?? null;

		if (!$existingSolution)
		{
			$type = 'add';
		}
		else if ($post->post_id == $existingSolution->post_id)
		{
			$type = 'remove';
		}
		else
		{
			$type = 'replace';
		}

		// for replacement cases, we want to force an explicit confirmation, even if receiving a post request
		// (which might come from JS)

		if (
			$this->isPost()
			&& ($type != 'replace' || $this->filter('confirm', 'bool'))
		)
		{
			/** @var MarkSolutionService $markSolution */
			$markSolution = $this->service(MarkSolutionService::class, $thread);

			if ($type == 'remove')
			{
				$markSolution->unmarkSolution();
				$switchKey = 'removed';
			}
			else
			{
				$markSolution->markSolution($post);
				$switchKey = $existingSolution ? "replaced:{$existingSolution->post_id}" : 'marked';
			}

			$reply = $this->redirect($this->buildLink('posts', $post));
			$reply->setJsonParam('switchKey', $switchKey);

			return $reply;
		}
		else
		{
			$viewParams = [
				'post' => $post,
				'thread' => $thread,
				'existingSolution' => $existingSolution,
				'type' => $type,
			];
			return $this->view('XF:Post\MarkSolution', 'post_mark_solution', $viewParams);
		}
	}

	public function actionWarn(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id);

		if (!$post->canWarn($error))
		{
			return $this->noPermission($error);
		}

		$breadcrumbs = $post->Thread->getBreadcrumbs();

		/** @var WarnPlugin $warnPlugin */
		$warnPlugin = $this->plugin(WarnPlugin::class);
		return $warnPlugin->actionWarn(
			'post',
			$post,
			$this->buildLink('posts/warn', $post),
			$breadcrumbs
		);
	}

	public function actionShare(ParameterBag $params)
	{
		$post = $this->assertViewablePost($params->post_id);
		$thread = $post->Thread;

		/** @var SharePlugin $sharePlugin */
		$sharePlugin = $this->plugin(SharePlugin::class);
		return $sharePlugin->actionTooltipWithEmbed(
			$post->isFirstPost()
				? $this->buildLink('canonical:threads', $thread)
				: $this->buildLink('canonical:threads/post', $thread, ['post_id' => $post->post_id]),
			$post->isFirstPost()
				? \XF::phrase('thread_x', ['title' => $thread->title])
				: \XF::phrase('post_in_thread_x', ['title' => $thread->title]),
			$post->isFirstPost()
				? \XF::phrase('share_this_thread')
				: \XF::phrase('share_this_post'),
			null,
			$post->isFirstPost()
				? $thread->getEmbedCodeHtml()
				: $post->getEmbedCodeHtml()
		);
	}

	/**
	 * @param $postId
	 * @param array $extraWith
	 *
	 * @return Post
	 *
	 * @throws Exception
	 */
	protected function assertViewablePost($postId, array $extraWith = [])
	{
		$visitor = \XF::visitor();
		$extraWith[] = 'Thread';
		$extraWith[] = 'Thread.Forum';
		$extraWith[] = 'Thread.Forum.Node';
		$extraWith[] = 'Thread.Forum.Node.Permissions|' . $visitor->permission_combination_id;

		/** @var Post $post */
		$post = $this->em()->find(Post::class, $postId, $extraWith);
		if (!$post)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_post_not_found')));
		}
		if (!$post->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		$this->plugin(NodePlugin::class)->applyNodeContext($post->Thread->Forum->Node);

		return $post;
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

	public static function getActivityDetails(array $activities)
	{
		return self::getActivityDetailsForContent(
			$activities,
			\XF::phrase('viewing_thread'),
			'post_id',
			function (array $ids)
			{
				$posts = \XF::em()->findByIds(
					PostFinder::class,
					$ids,
					['Thread', 'Thread.Forum', 'Thread.Forum.Node', 'Thread.Forum.Node.Permissions|' . \XF::visitor()->permission_combination_id]
				);

				$router = \XF::app()->router('public');
				$data = [];

				foreach ($posts->filterViewable() AS $id => $post)
				{
					$data[$id] = [
						'title' => $post->Thread->title,
						'url' => $router->buildLink('threads', $post->Thread),
					];
				}

				return $data;
			}
		);
	}

	public static function getResolvableActions(): array
	{
		return ['index', 'show'];
	}

	public static function resolveToEmbeddableContent(ParameterBag $params, RouteMatch $routeMatch): ?Entity
	{
		$content = null;

		if ($params->post_id)
		{
			/** @var Post $content */
			$content = \XF::em()->find(Post::class, $params->post_id);
		}

		if (!$content || !$content->canView())
		{
			$content = null;
		}

		return $content;
	}
}
