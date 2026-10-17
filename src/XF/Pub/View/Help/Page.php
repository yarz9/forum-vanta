<?php

namespace XF\Pub\View\Help;

use XF\Mvc\View;

class Page extends View
{
	public function renderHtml()
	{
		$this->params['templateHtml'] = $this->renderTemplate(
			$this->params['templateName'],
			$this->params
		);
	}
}
