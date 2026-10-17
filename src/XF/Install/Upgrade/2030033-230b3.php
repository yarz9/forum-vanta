<?php

namespace XF\Install\Upgrade;

use XF\Db\Schema\Create;

class Version2030033 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.3.0 Beta 3';
	}

	public function step1(): void
	{
		$this->executeUpgradeQuery("
			INSERT INTO `xf_tfa_provider`
				(`provider_id`, `provider_class`, `priority`, `active`)
			VALUES
				('passkey', 'XF:Passkey', 3, 1)
		");
	}

	public function step2(): void
	{
		$this->createTable('xf_passkey', function (Create $table)
		{
			$table->addColumn('passkey_id', 'int')->autoIncrement();
			$table->addColumn('credential_id', 'varchar', 128);
			$table->addColumn('credential_public_key', 'text');
			$table->addColumn('user_id', 'int');
			$table->addColumn('name', 'varchar', 100);
			$table->addColumn('aaguid', 'varchar', 32);
			$table->addColumn('create_date', 'int')->setDefault(0);
			$table->addColumn('create_ip_address', 'varbinary', 16)->setDefault('');
			$table->addColumn('last_use_date', 'int')->setDefault(0);
			$table->addColumn('last_use_ip_address', 'varbinary', 16)->setDefault('');
		});
	}
}
