<?php

namespace XF\Install\Upgrade;

use XF\Db\Schema\Alter;

class Version2030670 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.3.6';
	}

	public function step1(): void
	{
		// ensuring this is applied for any upgrades that missed it in 2.3.5
		$this->alterTable('xf_purchase_request', function (Alter $table)
		{
			$table->changeColumn('provider_metadata', 'varbinary', 500);
		});
	}
}
