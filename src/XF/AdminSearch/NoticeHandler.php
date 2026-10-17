<?php

namespace XF\AdminSearch;

class NoticeHandler extends AbstractFieldSearch
{
	public function getDisplayOrder()
	{
		return 45;
	}

	protected function getFinderName()
	{
		return 'XF:Notice';
	}

	protected function getRouteName()
	{
		return 'notices/edit';
	}

	public function isSearchable()
	{
		return \XF::visitor()->hasAdminPermission('notice');
	}
}
