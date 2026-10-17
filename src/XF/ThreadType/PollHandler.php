<?php

namespace XF\ThreadType;

use XF\Api\Result\EntityResult;
use XF\Entity\Thread;
use XF\Helper\Poll;
use XF\Http\Request;
use XF\Mvc\Entity\Entity;
use XF\Service\Thread\TypeData\PollCreatorService;

use function in_array;

class PollHandler extends AbstractHandler
{
	public function getTypeIconClass(): string
	{
		return 'fa-chart-bar';
	}

	public function getThreadViewAndTemplate(Thread $thread): array
	{
		return ['XF:Thread\ViewTypePoll', 'thread_view_type_poll'];
	}

	public function adjustThreadViewParams(Thread $thread, array $viewParams, Request $request): array
	{
		$viewParams['poll'] = $thread->Poll;

		return $viewParams;
	}

	protected function renderExtraDataEditInternal(
		Thread $thread,
		array $typeData,
		string $context,
		string $subContext,
		array $options = []
	): string
	{
		if (!in_array($context, ['create', 'convert']))
		{
			// poll options have a dedicated edit system
			return '';
		}

		if (isset($options['draftOverride']))
		{
			$pollDraft = $options['draftOverride']['poll'] ?? [];
		}
		else if (isset($options['draft']))
		{
			$pollDraft = $options['draft']['poll'] ?? [];
		}
		else
		{
			$pollDraft = [];
		}

		$params = [
			'handler' => $this,
			'thread' => $thread,
			'typeData' => $typeData,
			'typeDataDefinitions' => $this->getTypeDataColumnDefinitions(),
			'context' => $context,
			'subContext' => $subContext,
			'draft' => $options['draft'] ?? [],
			'pollDraft' => $pollDraft,
		];

		return \XF::app()->templater()->renderTemplate('public:thread_type_fields_poll', $params);
	}

	public function processExtraDataService(
		Thread $thread,
		string $context,
		Request $request,
		array $options = []
	)
	{
		if (!in_array($context, ['create', 'convert']))
		{
			// poll options have a dedicated edit system
			return null;
		}

		/** @var Poll $pollHelper */
		$pollHelper = \XF::helper(Poll::class);

		/** @var PollCreatorService $creator */
		$creator = \XF::service(PollCreatorService::class, $thread);

		$pollHelper->configureCreatorFromInput(
			$creator->getPollCreator(),
			$pollHelper->getPollInput($request)
		);

		return $creator;
	}

	public function processExtraDataForApiService(
		Thread $thread,
		string $context,
		Request $request,
		array $options = []
	)
	{
		// since we're only doing creation, this basically works out the same
		return $this->processExtraDataService($thread, $context, $request, $options);
	}

	public function processExtraDataForPreRegService(
		Thread $thread,
		string $context,
		array $input,
		array $options = []
	)
	{
		if ($context != 'create')
		{
			return null;
		}

		/** @var Poll $pollHelper */
		$pollHelper = \XF::helper(Poll::class);

		/** @var PollCreatorService $creator */
		$creator = \XF::service(PollCreatorService::class, $thread);

		$pollHelper->configureCreatorFromInput(
			$creator->getPollCreator(),
			$input['poll']
		);

		return $creator;
	}

	public function getExtraDataForDraft(Thread $thread, Request $request): array
	{
		/** @var Poll $pollHelper */
		$pollHelper = \XF::helper(Poll::class);

		return ['poll' => $pollHelper->getPollInput($request)];
	}

	public function getExtraDataForPreRegAction(Thread $thread, Request $request): array
	{
		/** @var Poll $pollHelper */
		$pollHelper = \XF::helper(Poll::class);

		return ['poll' => $pollHelper->getPollInput($request)];
	}

	public function addTypeDataToApiResult(
		Thread $thread,
		EntityResult $result,
		int $verbosity = Entity::VERBOSITY_NORMAL,
		array $options = []
	)
	{
		if ($verbosity > Entity::VERBOSITY_NORMAL && $thread->Poll)
		{
			$result->Poll = $thread->Poll->toApiResult();
		}
	}

	public function onThreadLeaveType(Thread $thread, array $typeData, bool $isDelete)
	{
		if ($thread->Poll)
		{
			$thread->Poll->delete();
		}
	}

	public function canConvertThreadToType(bool $isBulk): bool
	{
		if ($isBulk)
		{
			return false;
		}

		return true;
	}
}
