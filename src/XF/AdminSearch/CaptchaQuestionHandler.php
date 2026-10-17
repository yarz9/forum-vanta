<?php

namespace XF\AdminSearch;

class CaptchaQuestionHandler extends AbstractFieldSearch
{
	protected $searchFields = ['question'];

	public function getDisplayOrder()
	{
		return 45;
	}

	protected function getFinderName()
	{
		return 'XF:CaptchaQuestion';
	}

	protected function getRouteName()
	{
		return 'captcha-questions/edit';
	}

	public function isSearchable()
	{
		return \XF::visitor()->hasAdminPermission('option');
	}
}
