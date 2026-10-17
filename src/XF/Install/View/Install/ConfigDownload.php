<?php

namespace XF\Install\View\Install;

use XF\Mvc\View;

class ConfigDownload extends View
{
	public function renderRaw()
	{
		$this->response->header('Content-type', 'application/octet-stream', true);
		$this->response->setDownloadFileName('config.php');

		return $this->params['generated'];
	}
}
