<?php

namespace XF\ControllerPlugin;

use XF\Entity\AdminNavigation;

class AdminSectionPlugin extends AbstractPlugin
{
	public function actionView(string $navId, $title = null, ?string $viewClass = null, ?string $templateName = null, array $viewParams = [])
	{
		/** @var AdminNavigation $entry */
		$entry = $this->assertRecordExists(AdminNavigation::class, $navId);

		$viewParams += [
			'title' => $title ?: $entry->title,
			'entry' => $entry,
		];
		return $this->view($viewClass ?: 'XF:AdminSection', $templateName ?: 'admin_section', $viewParams);
	}
}
