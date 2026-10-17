<?php

namespace XF\ForumType;

use XF\Api\Result\EntityResult;
use XF\Entity\Forum;
use XF\Entity\Node;
use XF\Http\Request;
use XF\InputFiltererArray;
use XF\Mvc\Entity\Entity;
use XF\Mvc\FormAction;
use XF\Mvc\Reply\View;

use function is_array;

class DiscussionHandler extends AbstractHandler
{
	public function getDefaultThreadType(Forum $forum): string
	{
		return \XF\ThreadType\AbstractHandler::BASIC_THREAD_TYPE;
	}

	public function getDisplayOrder(): int
	{
		return 1;
	}

	public function getTypeIconClass(): string
	{
		return 'fa-comments';
	}

	public function getExtraAllowedThreadTypes(Forum $forum): array
	{
		return $forum->type_config['allowed_thread_types'];
	}

	/**
	 * Returns a list of thread type IDs that can possibly be (manually) created in this forum
	 *
	 * @param Forum $forum
	 *
	 * @return array
	 */
	public function getPossibleCreatableThreadTypes(Forum $forum): array
	{
		return ['article', 'poll', 'question'];
	}

	public function getDefaultTypeConfig(): array
	{
		return [
			'allowed_thread_types' => [],
			'allow_answer_voting' => true,
			'allow_answer_downvote' => true,
		];
	}

	protected function getTypeConfigColumnDefinitions(): array
	{
		return [
			'allowed_thread_types' => [
				'type' => Entity::LIST_ARRAY,
				'list' => ['type' => 'str', 'unique' => true],
			],
			'allow_answer_voting' => ['type' => Entity::BOOL],
			'allow_answer_downvote' => ['type' => Entity::BOOL],
		];
	}

	public function setupTypeConfigEdit(
		View $reply,
		Node $node,
		Forum $forum,
		array &$typeConfig
	)
	{
		$possibleThreadTypeIds = $this->getPossibleCreatableThreadTypes($forum);

		$possibleThreadTypes = [];
		foreach ($possibleThreadTypeIds AS $threadTypeId)
		{
			$threadType = \XF::app()->threadType($threadTypeId, false);
			if ($threadType)
			{
				$possibleThreadTypes[$threadTypeId] = $threadType->getTypeTitlePlural();
			}
		}

		$reply->setParam('possibleThreadTypes', $possibleThreadTypes);

		return 'forum_type_config_discussion';
	}

	public function setupTypeConfigSave(FormAction $form, Node $node, Forum $forum, Request $request)
	{
		$validator = $this->getTypeConfigValidator($forum);
		$validator->allowed_thread_types = $request->filter('type_config.allowed_thread_types', 'array-str');
		$validator->allow_answer_voting = $request->filter('type_config.allow_answer_voting', 'bool');
		$validator->allow_answer_downvote = $request->filter('type_config.allow_answer_downvote', 'bool');

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

		$allowedThreadTypes = $typeInputFilterer->filter('type_config.allowed_thread_types', '?array-str');
		if (is_array($allowedThreadTypes))
		{
			$validator->allowed_thread_types = array_filter($allowedThreadTypes);
			// removes empty types, so allowed_thread_types[]=<empty> can be passed in to remove all types
		}

		$allowVoting = $typeInputFilterer->filter('type_config.allow_answer_voting', '?bool');
		if ($allowVoting !== null)
		{
			$validator->allow_answer_voting = $allowVoting;
		}

		$allowDownvote = $typeInputFilterer->filter('type_config.allow_answer_downvote', '?bool');
		if ($allowDownvote !== null)
		{
			$validator->allow_answer_downvote = $allowDownvote;
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
		$result->discussion = [
			'allowed_thread_types' => [$this->getDefaultThreadType($forum)] + $forum->type_config['allowed_thread_types'],
			'allow_answer_voting' => $forum->type_config['allow_answer_voting'],
			'allow_answer_downvote' => $forum->type_config['allow_answer_downvote'],
		];
	}
}
