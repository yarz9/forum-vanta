<?php

namespace XF\Install\Upgrade;

class Version2021670 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.2.16';
	}

	public function step1()
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
}
