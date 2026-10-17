<?php

namespace XF\Pub\View\Error;

use XF\Mvc\View;

class RegistrationRequired extends View
{
	public function renderJson()
	{
		$html = $this->renderTemplate($this->templateName, $this->params);

		return [
			'status' => 'error',
			'errors' => [$this->params['error']],
			'errorHtml' => $this->renderer->getHtmlOutputStructure($html),
		];
	}
}
