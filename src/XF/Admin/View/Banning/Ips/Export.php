<?php

namespace XF\Admin\View\Banning\Ips;

use XF\Mvc\View;

class Export extends View
{
	public function renderXml()
	{
		/** @var \DOMDocument $document */
		$document = $this->params['xml'];

		$this->response->setDownloadFileName('banned_ips.xml');

		return $document->saveXML();
	}
}
