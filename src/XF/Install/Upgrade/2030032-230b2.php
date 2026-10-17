<?php

namespace XF\Install\Upgrade;

use XF\Db\Schema\Alter;

class Version2030032 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.3.0 Beta 2';
	}

	public function step1(): void
	{
		$this->alterTable('xf_user_upgrade_active', function (Alter $table)
		{
			$table->addColumn('is_cancelled', 'tinyint')->setDefault(0);
		});
	}

	public function step2()
	{
		$this->db()->insert('xf_payment_provider', [
			'provider_id' => 'paypalrest',
			'provider_class' => 'XF:PayPalRest',
			'addon_id' => 'XF',
		], true);
	}
}
