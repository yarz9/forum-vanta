<?php

namespace XF\Widget;

use XF\Finder\UserFinder;
use XF\Http\Request;

class NewestMembers extends AbstractWidget
{
	protected $defaultOptions = [
		'limit' => 12,
	];

	public function render()
	{
		if (!\XF::visitor()->canViewMemberList())
		{
			return '';
		}

		$userFinder = $this->finder(UserFinder::class)
			->isValidUser()
			->indexHint('USE', 'register_date')
			->order('register_date', 'DESC')
			->limit($this->options['limit']);

		$viewParams = [
			'users' => $userFinder->fetch(),
		];
		return $this->renderer('widget_newest_members', $viewParams);
	}

	public function verifyOptions(Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint',
		]);
		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		return true;
	}
}
