<?php

namespace XF\Service\Thread\TypeData;

use XF\App;
use XF\Entity\Thread;
use XF\Service\AbstractService;
use XF\Service\Poll\CreatorService;
use XF\Service\ValidateAndSavableTrait;

class PollCreatorService extends AbstractService implements SaverInterface
{
	use ValidateAndSavableTrait;

	/**
	 * @var Thread
	 */
	protected $thread;

	/**
	 * @var CreatorService
	 */
	protected $pollCreator;

	public function __construct(App $app, Thread $thread)
	{
		parent::__construct($app);
		$this->thread = $thread;
		$this->pollCreator = $this->service(CreatorService::class, 'thread', $thread);
	}

	/**
	 * @return Thread
	 */
	public function getThread()
	{
		return $this->thread;
	}

	/**
	 * @return CreatorService
	 */
	public function getPollCreator()
	{
		return $this->pollCreator;
	}

	protected function _validate()
	{
		if ($this->pollCreator->validate($errors))
		{
			return [];
		}
		else
		{
			return $errors;
		}
	}

	protected function _save()
	{
		return $this->pollCreator->save();
	}
}
