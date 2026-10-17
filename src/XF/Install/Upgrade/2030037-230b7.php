<?php

namespace XF\Install\Upgrade;

use XF\Db\Schema\Alter;
use XF\Repository\OptionRepository;
use XF\Util\File;

class Version2030037 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.3.0 Beta 7';
	}

	public function step1(): void
	{
		$this->alterTable('xf_oauth_client', function (Alter $table)
		{
			$table->addColumn('allowed_scopes', 'mediumblob')->after('redirect_uris');
		});
	}

	public function step2(): void
	{
		$this->alterTable('xf_api_scope', function (Alter $table)
		{
			$table->addColumn('usable_with_oauth_clients', 'tinyint', 3)->setDefault(1)->after('api_scope_id');
		});
	}

	public function step3(): void
	{
		$db = $this->db();

		$scopesToUpdate = [
			'auth', 'auth:login_token',
		];
		$quotedScopes = $db->quote($scopesToUpdate);

		$db->update('xf_api_scope', ['usable_with_oauth_clients' => 0], "api_scope_id IN ($quotedScopes)");
	}

	public function step4(): void
	{
		$dkimOptions = \XF::options()->emailDkim ?? null;

		if (!$dkimOptions)
		{
			return;
		}

		if ($dkimOptions['enabled'])
		{
			$keyFile = null;
			$privateKeyFilePath = $dkimOptions['privateKey'];

			try
			{
				$path = 'internal-data://keys/' . $privateKeyFilePath;
				$keyFile = \XF::fs()->read($path);
			}
			catch (\Exception $e)
			{
			}

			if ($keyFile)
			{
				$registry = $this->app()->registry();
				$registry->set('emailDkimKey', $keyFile);

				File::deleteFromAbstractedPath($path);
				unset($dkimOptions['privateKey']);

				$optionRepo = $this->app->repository(OptionRepository::class);
				$optionRepo->updateOption('emailDkim', $dkimOptions);

			}
		}
	}
}
