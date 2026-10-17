<?php

namespace XF\Admin\View\User;

use XF\Entity\User;
use XF\Mvc\View;
use XF\Util\File;

class EmailList extends View
{
	public function renderRaw()
	{
		$this->response
			->contentType('text/csv', 'utf-8')
			->setDownloadFileName($this->getCsvFileName());

		$fp = fopen(File::getTempFile(), 'r+');
		fputcsv($fp, [
			'username',
			'email',
		]);

		/** @var User $user */
		foreach ($this->params['users'] AS $user)
		{
			fputcsv($fp, [
				$user->username,
				$user->email,
			]);
		}

		rewind($fp);
		$csv = stream_get_contents($fp);
		fclose($fp);

		return $csv;
	}

	protected function getCsvFileName()
	{
		return sprintf("XenForo Users Email %s.csv", gmdate('Y-m-d', \XF::$time));
	}
}
