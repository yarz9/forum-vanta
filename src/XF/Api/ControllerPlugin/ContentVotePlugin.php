<?php

namespace XF\Api\ControllerPlugin;

use XF\Api\Mvc\Reply\ApiResult;
use XF\Entity\ContentVote;
use XF\Entity\ContentVoteTrait;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\Exception;
use XF\Repository\ContentVoteRepository;

class ContentVotePlugin extends AbstractPlugin
{
	/**
	 * @api-in <req> str $type Type of vote, "up" or "down". Use the current type to undo.
	 *
	 * @api-out true $success
	 * @api-out str $action "insert" or "delete" based on whether the reaction was added or removed.
	 *
	 * @param Entity|ContentVoteTrait $content
	 *
	 * @return ApiResult
	 * @throws Exception
	 */
	public function actionVote(Entity $content)
	{
		$this->assertRequiredApiInput('type');
		$voteType = $this->filter('type', 'str');

		$voteRepo = $this->getVoteRepo();

		if (!$voteRepo->isValidVoteType($voteType))
		{
			throw $this->exception($this->noPermission());
		}

		if (\XF::isApiCheckingPermissions() && !$content->canVoteOnContent($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		if ($voteType == ContentVote::VOTE_DOWN)
		{
			if (!$content->isContentDownvoteSupported())
			{
				throw $this->exception($this->noPermission());
			}
			else if (\XF::isApiCheckingPermissions() && !$content->canDownvoteContent($error))
			{
				throw $this->exception($this->noPermission($error));
			}
		}

		$vote = $voteRepo->vote(
			$content->getEntityContentType(),
			$content->getEntityId(),
			$voteType
		);

		return $this->apiSuccess([
			'action' => $vote ? 'insert' : 'delete',
		]);
	}

	/**
	 * @return ContentVoteRepository
	 */
	protected function getVoteRepo()
	{
		return $this->repository(ContentVoteRepository::class);
	}
}
