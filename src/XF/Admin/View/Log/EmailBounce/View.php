<?php

namespace XF\Admin\View\Log\EmailBounce;

use XF\Entity\EmailBounceLog;

class View extends \XF\Mvc\View
{
	public function renderRaw()
	{
		/** @var EmailBounceLog $bounce */
		$bounce = $this->params['bounce'];

		$this->response->contentType('text/plain', 'utf-8')
			->header('X-Content-Type-Options', 'nosniff');

		return $bounce->raw_message;
	}
}
