<?php

namespace XF\Admin\View\Language;

use XF\Mvc\View;

class Export extends View
{
	public function renderXml()
	{
		$this->response->setDownloadFileName($this->params['filename']);

		/** @var \DOMDocument $document */
		$document = $this->params['xml'];
		return $document->saveXml();
	}
}
