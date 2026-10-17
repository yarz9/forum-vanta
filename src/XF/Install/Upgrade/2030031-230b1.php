<?php

namespace XF\Install\Upgrade;

use XF\Db\Schema\Alter;
use XF\Job\SearchRebuild;

class Version2030031 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.3.0 Beta 1';
	}

	public function step1(): void
	{
		$this->alterTable('xf_content_activity_log', function (Alter $table)
		{
			$table->changeColumn('view_count', 'int')->unsigned(false);
			$table->changeColumn('reply_count', 'int')->unsigned(false);
			$table->changeColumn('reaction_count', 'int')->unsigned(false);
			$table->changeColumn('vote_count', 'int')->unsigned(false);
		});
	}

	public function step2(): void
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
