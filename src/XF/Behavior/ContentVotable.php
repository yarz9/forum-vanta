<?php

namespace XF\Behavior;

use XF\Mvc\Entity\Behavior;
use XF\Repository\ContentVoteRepository;

class ContentVotable extends Behavior
{
	protected function getDefaultConfig()
	{
		return [
			'stateField' => null,
		];
	}

	protected function verifyConfig()
	{
		if (!$this->contentType())
		{
			throw new \LogicException("Structure must provide a contentType value");
		}

		if ($this->config['stateField'] === null)
		{
			throw new \LogicException("stateField config must be overridden; if no field is present, use an empty string");
		}
	}

	public function postSave()
	{
		if ($this->config['stateField'])
		{
			$visibilityChange = $this->entity->isStateChanged($this->config['stateField'], 'visible');

			if ($this->entity->isUpdate() && ($visibilityChange == 'enter' || $visibilityChange == 'leave'))
			{
				/** @var ContentVoteRepository $voteRepo */
				$voteRepo = $this->repository(ContentVoteRepository::class);
				$voteRepo->recalculateVoteIsCounted($this->contentType(), $this->id());
			}
		}
	}

	public function postDelete()
	{
		/** @var ContentVoteRepository $voteRepo */
		$voteRepo = $this->repository(ContentVoteRepository::class);
		$voteRepo->fastDeleteVotesForContent($this->contentType(), $this->id());
	}
}
