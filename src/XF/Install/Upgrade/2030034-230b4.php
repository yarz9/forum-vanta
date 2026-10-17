<?php

namespace XF\Install\Upgrade;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Version2030034 extends AbstractUpgrade
{
	public function getVersionName()
	{
		return '2.3.0 Beta 4';
	}

	public function step1()
	{
		$this->schemaManager()->alterTable('xf_job', function (Alter $table)
		{
			$table->addColumn('priority', 'smallint', 5)->setDefault(100);
			$table->addKey(['priority', 'trigger_date'], 'priority_execute_date');
		});
	}

	public function step2()
	{
		$this->createTable('xf_trending_result', function (Create $table)
		{
			$table->addColumn('trending_result_id', 'int')->autoIncrement();
			$table->addColumn('order', 'enum')->values(['hot', 'top']);
			$table->addColumn('duration', 'int');
			$table->addColumn('content_type', 'varchar', 25)->setDefault('');
			$table->addColumn('content_container_id', 'int')->setDefault(0);
			$table->addColumn('result_date', 'int');
			$table->addColumn('content_data', 'blob');
			$table->addKey(['order', 'duration', 'content_type', 'content_container_id', 'result_date']);
		});
	}

	public function step3()
	{
		$this->createWidget('forum_overview_trending_content', 'trending_content', [
			'positions' => [
				'forum_list_sidebar' => 20,
				'forum_new_posts_sidebar' => 20,
				'whats_new_sidebar' => 20,
			],
			'options' => [
				'limit' => 5,
				'style' => 'simple',
			],
		]);
	}
}
