<?php

namespace XF\Widget;

use XF\Http\Request;
use XF\Repository\SessionActivityRepository;

class MembersOnline extends AbstractWidget
{
	protected $defaultOptions = [
		'limit' => 50,
		'staffOnline' => true,
		'staffQuery' => false,
		'followedOnline' => true,
	];

	public function render()
	{
		if (!\XF::visitor()->canViewMemberList())
		{
			return '';
		}

		/** @var SessionActivityRepository $activityRepo */
		$activityRepo = $this->repository(SessionActivityRepository::class);

		$viewParams = [
			'online' => $activityRepo->getOnlineStatsBlockData(true, $this->options['limit'], $this->options['staffQuery']),
		];
		return $this->renderer('widget_members_online', $viewParams);
	}

	public function verifyOptions(Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint',
			'staffOnline' => 'bool',
			'staffQuery' => 'bool',
			'followedOnline' => 'bool',
		]);
		return true;
	}
}
