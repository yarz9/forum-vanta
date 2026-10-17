<?php

namespace XF\Admin\View\Smilie;

use XF\Mvc\View;

class Export extends View
{
	public function renderXml()
	{
		/** @var \DOMDocument $document */
		$document = $this->params['xml'];

		$this->response->setDownloadFileName('smilies.xml');

		return $document->saveXml();
	}
}
