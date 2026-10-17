<?php

namespace XF\ForumType;

use XF\Api\Result\EntityResult;
use XF\Entity\Forum;
use XF\Entity\Node;
use XF\Entity\Thread;
use XF\Finder\ThreadFinder;
use XF\Http\Request;
use XF\InputFiltererArray;
use XF\Mvc\Entity\Entity;
use XF\Mvc\FormAction;
use XF\Mvc\Reply\View;
use XF\Repository\AttachmentRepository;

class ArticleHandler extends AbstractHandler
{
	public function getDefaultThreadType(Forum $forum): string
	{
		return 'article';
	}

	public function getDisplayOrder(): int
	{
		return 10;
	}

	public function getTypeIconClass(): string
	{
		return 'fa-file-alt';
	}

	public function getTypeActionPhrase(string $context)
	{
		return \XF::phrase('forum_type_action.article');
	}

	public function getDefaultTypeConfig(): array
	{
		return [
			'display_style' => 'full',
			'expanded_snippet' => 500,
			'expanded_per_page' => 0,
		];
	}

	protected function getTypeConfigColumnDefinitions(): array
	{
		return [
			'display_style' => ['type' => Entity::STR, 'allowedValues' => ['full', 'preview', 'expanded']],
			'expanded_snippet' => ['type' => Entity::UINT],
			'expanded_per_page' => ['type' => Entity::UINT],
		];
	}

	public function setupTypeConfigEdit(
		View $reply,
		Node $node,
		Forum $forum,
		array &$typeConfig
	)
	{
		return 'forum_type_config_article';
	}

	public function setupTypeConfigSave(FormAction $form, Node $node, Forum $forum, Request $request)
	{
		$validator = $this->getTypeConfigValidator($forum);

		$validator->bulkSet([
			'display_style' => $request->filter('type_config.display_style', 'str'),
			'expanded_snippet' => $request->filter('type_config.expanded_snippet', 'uint'),
			'expanded_per_page' => $request->filter('type_config.expanded_per_page', 'uint'),
		]);

		return $validator;
	}

	public function setupTypeConfigApiSave(
		FormAction $form,
		Node $node,
		Forum $forum,
		InputFiltererArray $typeInputFilterer
	)
	{
		$validator = $this->getTypeConfigValidator($forum);

		$displayStyle = $typeInputFilterer->filter('type_config.display_style', '?str');
		if ($displayStyle !== null)
		{
			$validator->display_style = $displayStyle;
		}

		$expandedSnippet = $typeInputFilterer->filter('type_config.expanded_snippet', '?uint');
		if ($expandedSnippet !== null)
		{
			$validator->expanded_snippet = $expandedSnippet;
		}

		$expandedPerPage = $typeInputFilterer->filter('type_config.expanded_per_page', '?uint');
		if ($expandedPerPage !== null)
		{
			$validator->expanded_per_page = $expandedPerPage;
		}

		return $validator;
	}

	public function addTypeConfigToApiResult(
		Forum $forum,
		EntityResult $result,
		int $verbosity = Entity::VERBOSITY_NORMAL,
		array $options = []
	)
	{
		$result->article = [
			'display_style' => $forum->type_config['display_style'],
			'expanded_snippet' => $forum->type_config['expanded_snippet'],
			'expanded_per_page' => $forum->type_config['expanded_per_page'],
		];
	}

	public function getForumViewAndTemplate(Forum $forum): array
	{
		return ['XF:Forum\ViewTypeArticle', 'forum_view_type_article'];
	}

	public function getThreadsPerPage(Forum $forum): int
	{
		$result = 0;

		if ($forum->type_config['display_style'] != 'full')
		{
			$result = $forum->type_config['expanded_per_page'];
		}

		return $result ?: parent::getThreadsPerPage($forum);
	}

	public function adjustForumThreadListFinder(
		Forum $forum,
		ThreadFinder $threadFinder,
		int $page,
		Request $request
	)
	{
		if ($forum->type_config['display_style'] != 'full')
		{
			$threadFinder
				->with('FirstPost.full')
				->where('discussion_type', '<>', 'redirect');
		}
	}

	public function fetchExtraContentForThreadsFullView(Forum $forum, $threads, array $options = [])
	{
		if ($forum->type_config['display_style'] != 'full')
		{
			$firstPosts = [];
			foreach ($threads AS $thread)
			{
				/** @var Thread $thread */
				if ($thread->FirstPost)
				{
					$firstPosts[] = $thread->FirstPost;
				}
			}

			/** @var AttachmentRepository $attachmentRepo */
			$attachmentRepo = \XF::repository(AttachmentRepository::class);
			$attachmentRepo->addAttachmentsToContent($firstPosts, 'post');
		}
	}
}
