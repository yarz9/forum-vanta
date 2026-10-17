<?php

namespace XF\Install\Upgrade;

use XF\Db\Schema\Alter;

class Version2030070 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.3.0';
	}

	public function step1(): void
	{
		$this->alterTable('xf_moderator', function (Alter $table)
		{
			$table->changeColumn('notify_report')->setDefault(0);
			$table->changeColumn('notify_approval')->setDefault(0);
		});
	}
}
