<?php

namespace XF\Install\Upgrade;

use XF\Job\SearchRebuild;

class Version2030038 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.3.0 Beta 8';
	}

	public function step1(): void
	{
		$this->app()->jobManager()->cancelUniqueJob('MailQueue');
	}

	public function step2(): void
	{
		$solveMedia = $this->db()->fetchRow("
			SELECT *
			FROM xf_option
			WHERE option_id = 'captcha'
			  AND option_value = 'SolveMedia'
		");

		if (!$solveMedia)
		{
			return;
		}

		$this->executeUpgradeQuery("
			UPDATE xf_option
			SET option_value = 'HCaptcha'
			WHERE option_id = 'captcha'
		");

		$this->executeUpgradeQuery("
			UPDATE xf_option
			SET option_value = '[]'
			WHERE option_id = 'extraCaptchaKeys'
		");
	}

	public function step3(): void
	{
		$this->insertPostUpgradeJob(
			'upgradeConversationSearchRebuild',
			SearchRebuild::class,
			[
				'type' => 'conversation_message',
			]
		);
	}
}
