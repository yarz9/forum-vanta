<?php

namespace XF\Admin\View\Banning\DiscouragedIps;

use XF\Mvc\View;

class Export extends View
{
	public function renderXml()
	{
		/** @var \DOMDocument $document */
		$document = $this->params['xml'];

		$this->response->setDownloadFileName('discouraged_ips.xml');

		return $document->saveXML();
	}
}
