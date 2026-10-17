<?php

namespace XF\Admin\View\Tools;

use XF\Mvc\View;

class RunJob extends View
{
	public function renderJson()
	{
		return [
			'html' => null,
			'jobRunner' => [
				'canCancel' => $this->params['canCancel'],
				'status' => $this->params['status'],
				'jobId' => $this->params['jobId'],
				'redirect' => $this->params['redirect'],
				'onlyIds' => $this->params['onlyIds'],
			],
		];
	}
}
