<?php

namespace XF\Admin\View\Moderator;

use XF\Moderator\AbstractModerator;
use XF\Mvc\View;

class AddChoice extends View
{
	public function renderHtml()
	{
		$this->params['typeChoices'] = [];

		if (!empty($this->params['typeHandlers']))
		{
			foreach ($this->params['typeHandlers'] AS $contentType => $handler)
			{
				$handlerClass = \XF::extendClass($handler);

				if (!class_exists($handlerClass))
				{
					continue;
				}

				/** @var AbstractModerator $contentHandler */
				$contentHandler = new $handlerClass();

				$selectedContentId = ($this->params['typeId'][$contentType] ?? 0);
				$this->params['typeChoices'][$contentType] = $contentHandler->getAddModeratorOption($selectedContentId, $contentType);
			}
		}
	}
}
