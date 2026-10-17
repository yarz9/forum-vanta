<?php

namespace XF\Repository;

use XF\Entity\Template;
use XF\Finder\TemplateHistoryFinder;
use XF\Mvc\Entity\Repository;

class TemplateHistoryRepository extends Repository
{
	public function getHistoryForMerge(Template $template, ?Template $parentTemplate = null)
	{
		if (!$parentTemplate)
		{
			$parentTemplate = $template->ParentTemplate;
		}
		if (!$parentTemplate)
		{
			throw new \InvalidArgumentException("This template does not have a parent version, cannot be used for merge");
		}

		$templateHistoryFinder = $this->finder(TemplateHistoryFinder::class);
		return $templateHistoryFinder
			->where('title', $template->title)
			->where('type', $template->type)
			->where('style_id', $parentTemplate->style_id)
			->where('edit_date', '<=', $template->last_edit_date)
			->order('edit_date', 'DESC')
			->fetchOne();
	}
}
