<?php

namespace XF\Install\Upgrade;

use XF\Db\Schema\Alter;

class Version2021470 extends AbstractUpgrade
{
	public function getVersionName()
	{
		return '2.2.14';
	}

	public function step1()
	{
		$this->alterTable('xf_change_log', function (Alter $table)
		{
			$table->changeColumn('old_value', 'mediumtext');
			$table->changeColumn('new_value', 'mediumtext');
		});
	}

	public function step2()
	{
		$this->alterTable('xf_error_log', function (Alter $table)
		{
			$table->addKey('user_id');
		});
	}

	public function step3()
	{
		$unsubEmail = \XF::options()->unsubscribeEmailAddress ?? null;

		if (!$unsubEmail)
		{
			return;
		}

		$optionValue = [
			'unsubscribe_type' => ['http', 'email'],
			'unsubscribe_email_address' => $unsubEmail,
		];
		$defaultValue = [
			'unsubscribe_type' => ['http'],
			'unsubscribe_email_address' => '',
		];

		$this->executeUpgradeQuery(
			'INSERT IGNORE INTO xf_option
				(option_id, option_value, default_value, edit_format, edit_format_params, data_type, sub_options, validation_class, validation_method, advanced, addon_id)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			[
				'unsubscribeEmail',
				json_encode($optionValue),
				json_encode($defaultValue),
				'template',
				'option_template_unsubscribeEmail',
				'array',
				"unsubscribe_type\nunsubscribe_email_address",
				'',
				'',
				0,
				'XF',
			]
		);
	}
}
