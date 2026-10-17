<?php

namespace XF\ThreadType;

use XF\Api\Result\EntityResult;
use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Entity\ThreadQuestion;
use XF\Finder\PostFinder;
use XF\ForumType\DiscussionHandler;
use XF\Http\Request;
use XF\Mvc\Entity\Entity;
use XF\Repository\ContentVoteRepository;

class QuestionHandler extends AbstractHandler
{
	public function getTypeIconClass(): string
	{
		return 'fa-question-circle';
	}

	public function getAdditionalPostListSortOptions(Thread $thread): array
	{
		if (!$this->isQuestionVotable($thread))
		{
			return [];
		}

		return [
			'vote_score' => [
				['vote_score', 'DESC'],
				['post_date', 'ASC'],
			],
		];
	}

	public function getThreadViewAndTemplate(Thread $thread): array
	{
		return ['XF:Thread\ViewTypeQuestion', 'thread_view_type_question'];
	}

	public function getThreadViewTemplateOverrides(Thread $thread, array $extra = []): array
	{
		return [
			'post_macro' => 'post_question_macros::answer',
		];
	}

	public function adjustThreadViewParams(Thread $thread, array $viewParams, Request $request): array
	{
		/** @var PostFinder $postFinder */
		$postFinder = \XF::finder(PostFinder::class);
		$suggestedSolutions = $postFinder
			->where([
				'thread_id' => $thread->thread_id,
				'message_state' => 'visible',
			])
			->where('post_id', '!=', $thread->first_post_id)
			->where('post_id', '!=', $thread->type_data['solution_post_id'])
			->with('User')
			->order(['vote_score', 'post_date'], 'desc')
			->fetch(3);

		$viewParams['suggestedSolutions'] = $suggestedSolutions;

		return $viewParams;
	}

	public function getSuggestedSolutionMinimumScore(Thread $thread): int
	{
		return 0;
	}

	public function getDefaultTypeData(): array
	{
		return [
			'solution_post_id' => 0,
			'solution_user_id' => 0,
			'allow_question_actions' => 'yes',
		];
	}

	protected function getTypeDataColumnDefinitions(): array
	{
		return [
			'solution_post_id' => ['type' => Entity::UINT],
			'solution_user_id' => ['type' => Entity::UINT],
			'allow_question_actions' => ['type' => Entity::STR, 'allowedValues' => ['yes', 'no', 'paused']],
		];
	}

	protected function renderExtraDataEditInternal(
		Thread $thread,
		array $typeData,
		string $context,
		string $subContext,
		array $options = []
	): string
	{
		$params = [
			'handler' => $this,
			'thread' => $thread,
			'typeData' => $typeData,
			'typeDataDefinitions' => $this->getTypeDataColumnDefinitions(),
			'context' => $context,
			'subContext' => $subContext,
			'draft' => $options['draft'] ?? [],
		];

		return \XF::app()->templater()->renderTemplate('public:thread_type_fields_question', $params);
	}

	public function processExtraDataSimple(
		Thread $thread,
		string $context,
		Request $request,
		&$errors = [],
		array $options = []
	)
	{
		$validator = $this->getTypeDataValidator($thread);

		if ($thread->canEditModeratorFields())
		{
			$validator->allow_question_actions = $request->filter('question.allow_question_actions', 'str');
		}

		return $validator;
	}

	public function processExtraDataForApiSimple(
		Thread $thread,
		string $context,
		Request $request,
		&$errors = [],
		array $options = []
	)
	{
		$validator = $this->getTypeDataValidator($thread);

		if ($thread->canEditModeratorFields() || \XF::isApiBypassingPermissions())
		{
			$allowQuestionActions = $request->filter('question.allow_question_actions', '?str');
			if ($allowQuestionActions !== null)
			{
				$validator->allow_question_actions = $allowQuestionActions;
			}
		}

		return $validator;
	}

	public function getLdStructuredData(Thread $thread, Post $firstDisplayedPost, int $page, array $extraData = [])
	{
		$router = \XF::app()->router('public');
		$pageLink = $router->buildLink('canonical:threads', $thread, [
			'page' => $page,
		]);
		$threadLink = $thread->getContentUrl(true);
		$userLink = $thread->User ? $thread->User->getContentUrl(true) : null;

		$acceptedAnswer = null;
		if ($thread->type_data['solution_post_id'])
		{
			/** @var Post|null $solution */
			$solution = $extraData['highlightedPosts'][$thread->type_data['solution_post_id']]
				?? null;
			$acceptedAnswer = $solution
				? $this->getLdAnswerOutput($solution)
				: null;
		}

		$suggestedAnswer = null;
		/** @var Post[] $suggestedSolutions */
		$suggestedSolutions = $extraData['suggestedSolutions'] ?? [];
		if ($suggestedSolutions)
		{
			$suggestedAnswer = [];
			foreach ($suggestedSolutions AS $suggestedSolution)
			{
				$suggestedAnswer[] = $this->getLdAnswerOutput($suggestedSolution);
			}
		}

		$mainEntity = [
			'@type' => $this->getMicrodataType($thread),
			'@id' => $threadLink,
			'name' => $thread->title,
			'datePublished' => gmdate('c', $thread->post_date),
			'dateModified' =>  $thread->FirstPost->last_edit_date
				? gmdate('c', $thread->FirstPost->last_edit_date)
				: null,
			'keywords' => $thread->tags
				? implode(', ', array_column($thread->tags, 'tag'))
				: null,
			'url' => $threadLink,
			'image' => $this->getLdImage(
				$thread,
				$firstDisplayedPost,
				$extraData
			),
			'text' => $this->getLdSnippet($thread->FirstPost->message, 0)
				?: $thread->title,
			'answerCount' => $thread->reply_count,
			'upvoteCount' => $thread->first_post_reaction_score,
			'author' => [
				'@type' => 'Person',
				'@id' => $userLink,
				'name' => $thread->User->username ?? $thread->username,
				'url' => $userLink,
			],
			'acceptedAnswer' => $acceptedAnswer,
			'suggestedAnswer' => $suggestedAnswer,
		];

		return [
			'@context' => 'https://schema.org',
			'@type' => 'QAPage',
			'url' => $pageLink,
			'mainEntity' => $mainEntity,
			'publisher' => $this->getLdPublisher($this->getLdMetadataLogo()),
		];
	}

	protected function getLdAnswerOutput(Post $post)
	{
		$publicRouter = \XF::app()->router('public');
		$postLink = $publicRouter->buildLink('canonical:posts', $post);
		$userLink = $post->User
			? $publicRouter->buildLink('canonical:members', $post->User)
			: null;

		return [
			'@type' => 'Answer',
			'datePublished' => gmdate('c', $post->post_date),
			'dateModified' => $post->last_edit_date
				? gmdate('c', $post->last_edit_date)
				: null,
			'url' => $postLink,
			'text' => $this->getLdSnippet($post->message, 0)
				?: $post->getContentTitle(),
			'upvoteCount' => $post->vote_score,
			'author' => [
				'@type' => 'Person',
				'@id' => $userLink,
				'name' => $post->User->username ?? $post->username,
				'url' => $userLink,
			],
		];
	}

	public function getMicrodataType(Thread $thread): string
	{
		return 'Question';
	}

	public function getReplyMicrodataType(Thread $thread): string
	{
		return 'Answer';
	}

	public function isFirstPostPinned(Thread $thread): bool
	{
		return true;
	}

	public function getHighlightedPostIds(Thread $thread, array $filters = []): array
	{
		if ($thread->type_data['allow_question_actions'] == 'no')
		{
			return [];
		}

		if ($thread->type_data['solution_post_id'])
		{
			return [$thread->type_data['solution_post_id']];
		}
		else
		{
			return [];
		}
	}

	public function adjustThreadPostListFinder(
		Thread $thread,
		PostFinder $postFinder,
		int $page,
		Request $request,
		?array $extraFetchIds = null
	)
	{
		$visitor = \XF::visitor();

		if ($visitor->user_id)
		{
			$postFinder->with('ContentVotes|' . $visitor->user_id);
		}
	}

	public function isPostVotingSupported(Thread $thread, Post $post): bool
	{
		return $this->isQuestionVotable($thread);
	}

	public function isPostDownvoteSupported(Thread $thread, Post $post): bool
	{
		$forum = $this->getForumIfType($thread, \XF\ForumType\QuestionHandler::class);
		if ($forum)
		{
			return $forum->type_config['allow_downvote'];
		}

		$forum = $this->getForumIfType($thread, DiscussionHandler::class);
		if ($forum)
		{
			return $forum->type_config['allow_answer_downvote'];
		}

		return false;
	}

	public function canVoteOnPost(Thread $thread, Post $post, &$error = null): bool
	{
		if ($thread->type_data['allow_question_actions'] !== 'yes')
		{
			return false;
		}

		return \XF::visitor()->hasNodePermission($thread->node_id, 'contentVote');
	}

	public function addTypeDataToApiResult(
		Thread $thread,
		EntityResult $result,
		int $verbosity = Entity::VERBOSITY_NORMAL,
		array $options = []
	)
	{
		$typeData = $thread->type_data;

		$result->question = [
			'solution_post_id' => $typeData['solution_post_id'],
			'solution_user_id' => $typeData['solution_user_id'],
			'allow_question_actions' => $typeData['allow_question_actions'],
		];
	}

	public function canMarkPostAsSolution(Thread $thread, Post $post, &$error = null): bool
	{
		$visitor = \XF::visitor();
		$nodeId = $thread->node_id;

		if (!$visitor->user_id)
		{
			return false;
		}

		if ($thread->type_data['allow_question_actions'] !== 'yes')
		{
			return false;
		}

		if ($visitor->hasNodePermission($nodeId, 'markSolutionAnyThread'))
		{
			return true;
		}

		return ($thread->user_id == $visitor->user_id && $visitor->hasNodePermission($nodeId, 'markSolution'));
	}

	public function isPostSolution(Thread $thread, Post $post, &$error = null): bool
	{
		if ($thread->type_data['allow_question_actions'] == 'no')
		{
			return false;
		}

		return $thread->type_data['solution_post_id'] == $post->post_id;
	}

	public function isQuestionVotable(Thread $thread): bool
	{
		if ($thread->type_data['allow_question_actions'] == 'no')
		{
			return false;
		}

		$forum = $this->getForumIfType($thread, \XF\ForumType\QuestionHandler::class)
			?? $this->getForumIfType($thread, DiscussionHandler::class);

		if ($forum)
		{
			return $forum->type_config['allow_answer_voting'];
		}

		return false;
	}

	protected function getForumIfQuestionType(Thread $thread)
	{
		return $this->getForumIfType($thread, \XF\ForumType\QuestionHandler::class);
	}

	public function onThreadSave(Thread $thread, bool $isTypeEnter)
	{
		$typeData = $thread->type_data;
		$solutionPostId = $typeData['solution_post_id'];

		if ($isTypeEnter)
		{
			$solutionChanged = true;
		}
		else
		{
			$oldTypeData = $thread->getTypeData(false);
			$solutionChanged = ($solutionPostId != $oldTypeData['solution_post_id']);
		}

		if ($solutionChanged)
		{
			/** @var ThreadQuestion $question */
			$question = $thread->getRelationOrDefault('Question', false);

			/** @var Post|null $solution */
			$solution = $solutionPostId ? \XF::em()->find(Post::class, $solutionPostId) : null;
			if ($solution && $solution->thread_id == $thread->thread_id)
			{
				$question->solution_post_id = $solution->post_id;
				$question->solution_user_id = $solution->user_id;
			}
			else
			{
				$question->solution_post_id = 0;
				$question->solution_user_id = 0;
			}

			$question->save(true, false);
		}
	}

	public function onThreadMadeVisible(Thread $thread)
	{
		$question = $thread->Question;
		if ($question)
		{
			$question->threadMadeVisible();
		}
	}

	public function onThreadHidden(Thread $thread, bool $isDelete)
	{
		if (!$isDelete)
		{
			// if the thread is being hard deleted, this will be removed there
			$question = $thread->Question;
			if ($question)
			{
				$question->threadHidden();
			}
		}
	}

	public function onThreadLeaveType(Thread $thread, array $typeData, bool $isDelete)
	{
		if (!$isDelete)
		{
			$db = \XF::db();
			$postIds = $thread->post_ids;

			// these will be cleaned up on delete already so don't need to do that
			\XF::repository(ContentVoteRepository::class)->fastDeleteVotesForContent('post', $postIds);

			if ($postIds)
			{
				// possible to have no post IDs when doing things like thread merging
				$db->update('xf_post', [
					'vote_score' => 0,
					'vote_count' => 0,
				], 'post_id IN (' . $db->quote($postIds) . ')');
			}
		}

		$question = $thread->Question;
		if ($question)
		{
			$question->delete(false, false);
		}
	}

	public function onThreadRebuildCounters(Thread $thread)
	{
		$typeData = $thread->type_data;
		if (!$typeData['solution_post_id'])
		{
			return;
		}

		$post = \XF::em()->find(Post::class, $typeData['solution_post_id']);
		if ($post->thread_id != $thread->thread_id || $post->message_state != 'visible')
		{
			unset($typeData['solution_post_id'], $typeData['solution_user_id']);
			$thread->type_data = $typeData;
		}
	}

	public function onVisiblePostRemoved(Thread $thread, Post $post)
	{
		$typeData = $thread->type_data;
		if ($typeData['solution_post_id'] && $typeData['solution_post_id'] == $post->post_id)
		{
			unset($typeData['solution_post_id'], $typeData['solution_user_id']);
			$thread->type_data = $typeData;
		}
	}
}
