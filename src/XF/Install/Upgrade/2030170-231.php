<?php

namespace XF\Install\Upgrade;

class Version2030170 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.3.1';
	}

	public function step1(): void
	{
		$this->applyGlobalPermission('forum', 'manageIndexing', 'forum', 'manageAnyThread');
	}

	public function step2(): void
	{
		$this->executeUpgradeQuery(
			'ALTER TABLE xf_featured_content
				DROP PRIMARY KEY,
				ADD featured_content_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST,
				ADD UNIQUE content_type_content_id (content_type, content_id)'
		);
	}
}
