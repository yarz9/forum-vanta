<?php

namespace XF\Admin\View\User;

use XF\Mvc\View;

class Export extends View
{
	public function renderXml()
	{
		/** @var \DOMDocument $document */
		$document = $this->params['xml'];

		$this->response->setDownloadFileName('user.xml');

		return $document->saveXML();
	}
}
