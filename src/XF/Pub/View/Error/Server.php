<?php

namespace XF\Pub\View\Error;

use XF\Mvc\View;

class Server extends View
{
	public function renderHtml()
	{
		$e = $this->params['exception'] ?? null;
		return $this->renderExceptionHtml($e);
	}

	public function renderJson()
	{
		$e = $this->params['exception'] ?? null;
		$html = $this->renderExceptionHtml($e, $error);

		return [
			'exception' => $error,
			'errorHtml' => $html,
		];
	}

	public function renderXml()
	{
		$e = $this->params['exception'] ?? null;
		return $this->renderExceptionXml($e)->saveXML();
	}
}
