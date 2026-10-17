<?php

namespace XF\Pub\View\Purchase;

use XF\Mvc\Renderer\Json;
use XF\Mvc\View;

/**
 * @property Json $renderer
 */
class TwoCheckoutInitiate extends View
{
	public function renderJson()
	{
		$params = $this->getParams();

		return [
			'providerHtml' => $this->renderer->getHtmlOutputStructure(
				$this->renderTemplate($this->getTemplateName(), $params)
			),
		] + $params;
	}
}
