<?php

namespace XF\Cron;

class FileCheck
{
	public static function checkFiles()
	{
		$app = \XF::app();

		$fileCheck = $app->em()->create(\XF\Entity\FileCheck::class);
		$fileCheck->save();

		$app->jobManager()->enqueueUnique('fileCheck', \XF\Job\FileCheck::class, [
			'check_id' => $fileCheck->check_id,
			'automated' => true,
		], false);
	}
}
