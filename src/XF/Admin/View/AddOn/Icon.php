<?php

namespace XF\Admin\View\AddOn;

use XF\Mvc\View;

class Icon extends View
{
	public function renderRaw()
	{
		$this->response->setAttachmentFileParams($this->params['icon']);
		return $this->response->responseFile($this->params['icon']);
	}
}
