<?php

namespace XF\Admin\View\BbCode;

use XF\Mvc\View;

class Export extends View
{
	public function renderXml()
	{
		/** @var \DOMDocument $document */
		$document = $this->params['xml'];

		$this->response->setDownloadFileName('bb_codes.xml');

		return $document->saveXML();
	}
}
