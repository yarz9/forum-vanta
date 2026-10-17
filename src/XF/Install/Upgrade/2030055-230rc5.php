<?php

namespace XF\Install\Upgrade;

use XF\Db\Schema\Alter;
use XF\Repository\OptionRepository;

class Version2030055 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.3.0 Release Candidate 5';
	}

	public function step1(): void
	{
		$this->alterTable('xf_attachment_data', function (Alter $table)
		{
			$table->addColumn('file_key', 'varchar', 32)->after('file_hash');
		});
	}

	public function step2(): void
	{
		$this->executeUpgradeQuery("
			UPDATE xf_attachment_data
			SET file_key = file_hash
		");
	}

	public function step3(): void
	{
		/** @var array<string, mixed> $emailTransport */
		$emailTransport = $this->app->options()->emailTransport;
		if (!isset($emailTransport['smtpEncrypt']))
		{
			return;
		}

		$emailTransport['smtpSsl'] = $emailTransport['smtpEncrypt'] === 'ssl';
		unset($emailTransport['smtpEncrypt']);

		$optionRepo = $this->app->repository(OptionRepository::class);
		$optionRepo->updateOption('emailTransport', $emailTransport);
	}
}
