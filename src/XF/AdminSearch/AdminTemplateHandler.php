<?php

namespace XF\AdminSearch;

use XF\Repository\StyleRepository;

class AdminTemplateHandler extends PublicTemplateHandler
{
	protected function getSearchTemplateType()
	{
		return 'admin';
	}

	public function isSearchable()
	{
		/** @var StyleRepository $styleRepo */
		$styleRepo = $this->app->repository(StyleRepository::class);
		if (!$styleRepo->getMasterStyle()->canEdit())
		{
			return false;
		}

		return parent::isSearchable();
	}
}
