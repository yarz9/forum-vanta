<?php

namespace XF\Install\Upgrade;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Version2030010 extends AbstractUpgrade
{
	public function getVersionName()
	{
		return '2.3.0 Alpha';
	}

	public function step1(): void
	{
		// verify upgrades from XF 1 have the correct xf_job schema

		$sm = $this->db()->getSchemaManager();

		$sm->alterTable('xf_job', function (Alter $table) use ($sm)
		{
			if (!$sm->columnExists('xf_job', 'attempts'))
			{
				$table->addColumn('attempts', 'tinyint', 3)->setDefault(0);
			}
			if (!$sm->columnExists('xf_job', 'priority'))
			{
				$table->addColumn('priority', 'smallint', 5)->setDefault(100);
				$table->addKey(['priority', 'trigger_date'], 'priority_execute_date');
			}
		});
	}

	public function step2()
	{
		$this->createTable('xf_webhook', function (Create $table)
		{
			$table->addColumn('webhook_id', 'int')->autoIncrement();
			$table->addColumn('title', 'varchar', 150);
			$table->addColumn('description', 'varchar', 150)->setDefault('');
			$table->addColumn('url', 'text');
			$table->addColumn('secret', 'text')->nullable();
			$table->addColumn('content_type', 'enum')->values(['json', 'form_params'])->setDefault('json');
			$table->addColumn('ssl_verify', 'tinyint')->setDefault(1);
			$table->addColumn('events', 'blob');
			$table->addColumn('criteria', 'blob');
			$table->addColumn('active', 'tinyint', 3);
			$table->addColumn('creation_date', 'int')->setDefault(0);
		});

		$this->createTable('xf_icon_usage', function (Create $table): void
		{
			$table->addColumn('icon_usage_id', 'int')->autoIncrement();
			$table->addColumn('content_type', 'varbinary', 25);
			$table->addColumn('content_id', 'varbinary', 100);
			$table->addColumn('usage_type', 'enum')->values(['sprite', 'standalone']);
			$table->addColumn('icon_variant', 'enum')->values(['default', 'solid', 'regular', 'light', 'duotone', 'brands'])->setDefault('default');
			$table->addColumn('icon_name', 'varbinary', 50);
			$table->addKey(['content_type', 'content_id']);
			$table->addKey(['icon_name', 'usage_type', 'icon_variant']);
		});

		$this->createTable('xf_featured_content', function (Create $table)
		{
			$table->addColumn('content_type', 'varchar', 25);
			$table->addColumn('content_id', 'int');
			$table->addColumn('content_container_id', 'int')->setDefault(0);
			$table->addColumn('content_user_id', 'int')->setDefault(0);
			$table->addColumn('content_username', 'varchar', 50)->setDefault('');
			$table->addColumn('content_date', 'int')->setDefault(0);
			$table->addColumn('content_visible', 'tinyint');
			$table->addColumn('feature_user_id', 'int');
			$table->addColumn('feature_date', 'int');
			$table->addColumn('auto_featured', 'tinyint')->setDefault(0);
			$table->addColumn('always_visible', 'tinyint')->setDefault(0);
			$table->addColumn('title', 'varchar', 150)->setDefault('');
			$table->addColumn('image_date', 'int')->setDefault(0);
			$table->addColumn('snippet', 'text');
			$table->addPrimaryKey(['content_type', 'content_id']);
			$table->addKey('feature_date');
			$table->addKey(['content_visible', 'feature_date']);
		});

		$this->createTable('xf_content_activity_log', function (Create $table)
		{
			$table->addColumn('log_date', 'date');
			$table->addColumn('content_type', 'varbinary', 25);
			$table->addColumn('content_id', 'int');
			$table->addColumn('content_date', 'int');
			$table->addColumn('content_container_id', 'int')->setDefault(0);
			$table->addColumn('view_count', 'int')->setDefault(0);
			$table->addColumn('reply_count', 'int')->setDefault(0);
			$table->addColumn('reaction_count', 'int')->setDefault(0);
			$table->addColumn('reaction_score', 'int')->unsigned(false)->setDefault(0);
			$table->addColumn('vote_count', 'int')->setDefault(0);
			$table->addColumn('vote_score', 'int')->unsigned(false)->setDefault(0);
			$table->addPrimaryKey(['log_date', 'content_type', 'content_id']);
			$table->addKey(['content_type', 'content_id']);
		});
	}

	public function step3()
	{
		$this->alterTable('xf_thread', function (Alter $table)
		{
			$table->addColumn('index_state', 'enum')->values(['default', 'indexed', 'not_indexed'])
				->setDefault('default')
				->after('type_data');
		});

		$this->alterTable('xf_moderator', function (Alter $table)
		{
			$table->addColumn('notify_report', 'tinyint', 3);
			$table->addColumn('notify_approval', 'tinyint', 3);
		});

		$this->alterTable('xf_image_proxy', function (Alter $table)
		{
			$table->addColumn('file_metadata', 'blob')->nullable()->setDefault(null);
		});

		$this->executeUpgradeQuery("
			INSERT INTO `xf_connected_account_provider`
				(`provider_id`, `provider_class`, `display_order`, `options`)
			VALUES
				('apple', 'XF:Provider\\\\Apple', 80, '')
		");
	}

	public function step4()
	{
		$this->alterTable('xf_thread', function (Alter $table)
		{
			$table->changeColumn('discussion_type', 'varbinary');
		});
	}

	public function step5()
	{
		$this->alterTable('xf_smilie', function (Alter $table)
		{
			$table->addColumn('emoji_shortname', 'varchar', 100)->setDefault('')->after('smilie_text');
		});

		// [y => emoji shortname]
		$emojiMap = [
			0    => ':slight_smile:',     // smile
			-110 => ':rolling_eyes:',     // roll eyes
			-220 => ':flushed:',          // oops!
			-330 => ':blush:',            // giggle
			-440 => ':person_bowing:',    // notworthy
			-550 => ':thumbsup:',         // thumbs up
			-660 => ':zipper_mouth:',     // censored
			-22  => ':grinning:',         // big grin
			-132 => ':stuck_out_tongue:', // stick out tongue
			-242 => ':scream:',           // eek!
			-352 => ':sleeping:',         // sleep
			-462 => ':poop:',             // poop
			-572 => ':thumbsdown:',       // thumbs down
			-682 => ':nauseated_face:',   // sick
			-44  => ':sunglasses:',       // cool
			-154 => ':confused:',         // confused
			-264 => ':slight_frown:',     // frown
			-374 => ':ninja:',            // ninja
			-484 => ':cry:',              // crying
			-594 => ':footprints:',       // barefoot
			-704 => ':smirk:',            // sneaky
			-66  => ':rage:',             // mad
			-176 => ':wink:',             // wink
			-286 => ':rofl:',             // rofl
			-396 => ':laughing:',         // laugh
			-506 => ':heart_eyes:',       // love
			-616 => ':alien:',            // alien
			-726 => ':see_no_evil:',      // x3
			-88  => ':nerd:',             // geek
			-198 => ':coffee:',           // coffee
			-308 => ':dizzy_face:',       // err... what?
			-418 => ':unamused:',         // cautious
			-528 => ':thinking:',         // unsure
			-638 => ':smiling_imp:',      // devil
			-748 => ':kissing:',           // whistling
		];

		$db = $this->db();

		$defaultSprite = 'styles/default/xenforo/smilies/emojione/sprite_sheet_emojione.png';
		$possibleDefaultSmilies = $db->fetchAllKeyed(
			'SELECT *
				FROM xf_smilie
				WHERE image_url = ?
					AND sprite_mode = 1',
			'smilie_id',
			$defaultSprite
		);

		$updates = [];
		foreach ($possibleDefaultSmilies AS $smilieId => $smilie)
		{
			$spriteParams = @json_decode($smilie['sprite_params'], true);
			if (!$spriteParams)
			{
				continue;
			}

			$y = $spriteParams['y'];

			if (!isset($emojiMap[$y]))
			{
				continue;
			}

			$shortname = $emojiMap[$y];
			$updates[$smilieId] = [
				'image_url' => '',
				'emoji_shortname' => $shortname,
				'sprite_mode' => 0,
				'sprite_params' => json_encode([
					'w' => '22',
					'h' => '22',
					'x' => '0',
					'y' => '0',
					'bs' => '',
				]),
			];
		}

		if ($updates)
		{
			$db->beginTransaction();

			foreach ($updates AS $smilieId => $cols)
			{
				$db->update('xf_smilie', $cols, 'smilie_id = ?', $smilieId);
			}

			// force smilie cache rebuild
			$this->executeUpgradeQuery(
				'DELETE FROM xf_data_registry WHERE data_key = ?',
				'smilies'
			);

			$db->commit();
		}
	}

	public function step6()
	{
		$this->alterTable('xf_reaction', function (Alter $table)
		{
			$table->addColumn('emoji_shortname', 'varchar', 100)->setDefault('')->after('text_color');
		});

		$emojiMap = [
			0 => ':thumbsup:',
			-32 => ':heart_eyes:',
			-64 => ':rofl:',
			-96 => ':astonished:',
			-128 => ':slight_frown:',
			-160 => ':rage:',
			-192 => ':thumbsdown:',
		];

		$db = $this->db();

		$defaultSprite = 'styles/default/xenforo/reactions/emojione/sprite_sheet_emojione.png';
		$possibleDefaultReactions = $db->fetchAllKeyed(
			'SELECT *
				FROM xf_reaction
				WHERE image_url = ?
					AND sprite_mode = 1',
			'reaction_id',
			$defaultSprite
		);

		$updates = [];
		foreach ($possibleDefaultReactions AS $reactionId => $reaction)
		{
			$spriteParams = @json_decode($reaction['sprite_params'], true);
			if (!$spriteParams)
			{
				continue;
			}

			$y = $spriteParams['y'];

			if (!isset($emojiMap[$y]))
			{
				continue;
			}

			$shortname = $emojiMap[$y];
			$updates[$reactionId] = [
				'image_url' => '',
				'emoji_shortname' => $shortname,
				'sprite_mode' => 0,
				'sprite_params' => json_encode([
					'w' => '32',
					'h' => '32',
					'x' => '0',
					'y' => '0',
					'bs' => '',
				]),
			];
		}

		if ($updates)
		{
			$db->beginTransaction();

			foreach ($updates AS $reactionId => $cols)
			{
				$db->update('xf_reaction', $cols, 'reaction_id = ?', $reactionId);
			}

			// force reaction cache rebuild
			$this->executeUpgradeQuery(
				'DELETE FROM xf_data_registry WHERE data_key = ?',
				'reactions'
			);

			$db->commit();
		}
	}

	public function step7()
	{
		$db = $this->db();

		$attachmentExtensions = $db->fetchOne('
			SELECT option_value
			FROM xf_option
			WHERE option_id = \'attachmentExtensions\'
		');

		$attachmentExtensions = preg_split('/\s+/', trim($attachmentExtensions), -1, PREG_SPLIT_NO_EMPTY);

		if (!in_array('webp', $attachmentExtensions))
		{
			$newAttachmentExtensions = $attachmentExtensions;
			$newAttachmentExtensions[] = 'webp';
			$newAttachmentExtensions = implode("\n", $newAttachmentExtensions);

			$this->executeUpgradeQuery('
				UPDATE xf_option
				SET option_value = ?
				WHERE option_id = \'attachmentExtensions\'
			', $newAttachmentExtensions);
		}
	}

	public function step8()
	{
		$this->alterTable('xf_thread', function (Alter $table)
		{
			$table->addColumn('featured', 'tinyint')->setDefault(0)->after('vote_count');
		});

		$this->alterTable('xf_forum', function (Alter $table)
		{
			$table->addColumn('auto_feature', 'tinyint')->setDefault(0)->after('count_messages');
		});

		$this->alterTable('xf_language', function (Alter $table)
		{
			$table->addColumn('date_short_format', 'varchar', 30)->after('date_format');
			$table->addColumn('date_short_recent_format', 'varchar', 30)->after('date_short_format');
		});

		$this->executeUpgradeQuery("
			UPDATE xf_language SET
				date_short_format = 'M \\'y',
				date_short_recent_format = 'M j'
		");
	}

	public function step9()
	{
		$this->applyGlobalPermission('forum', 'featureUnfeatureThread', 'forum', 'stickUnstickThread');

		$this->createWidget('forum_overview_featured_content', 'featured_content', [
			'positions' => [
				'forum_list_sidebar' => 10,
				'forum_new_posts_sidebar' => 10,
				'whats_new_sidebar' => 10,
			],
			'options' => [
				'limit' => 3,
				'style' => 'simple',
			],
		]);

		$this->editWidgetPositions('forum_overview_members_online', [
			'whats_new_sidebar' => 20,
		]);

		$this->editWidgetPositions('forum_overview_forum_statistics', [
			'whats_new_sidebar' => 30,
		]);
	}

	public function step10()
	{
		$this->alterTable('xf_job', function (Alter $table)
		{
			$table->addColumn('attempts', 'tinyint', 3)->setDefault(0);
		});

		$this->createTable('xf_failed_job', function (Create $table)
		{
			$table->addColumn('failed_job_id', 'int')->autoIncrement();
			$table->addColumn('execute_class', 'varchar', 100);
			$table->addColumn('execute_data', 'mediumblob');
			$table->addColumn('exception', 'text');
			$table->addColumn('fail_date', 'int');
		});
	}

	public function step11($position, array $stepData)
	{
		$db = $this->db();
		$amount = 1000;

		if (!isset($stepData['max']))
		{
			$stepData['max'] = $db->fetchOne(
				'
				SELECT MAX(mail_queue_id) FROM xf_mail_queue'
			);
		}

		$queuedEmails = $db->fetchAll($db->limit("
			SELECT *
			FROM xf_mail_queue
			WHERE mail_queue_id > ?
			ORDER BY mail_queue_id
		", $amount), $position);

		if (!$queuedEmails)
		{
			return true;
		}

		$inserts = [];
		$next = 0;

		foreach ($queuedEmails AS $email)
		{
			$next = $email['mail_queue_id'];

			$mailData = @unserialize($email['mail_data']);
			if (!$mailData)
			{
				continue;
			}

			$executeData = [
				'email' => $mailData,
			];

			$inserts[] = [
				'execute_class' => 'XF:MailSend',
				'execute_data' => serialize($executeData),
				'trigger_date' => $email['queue_date'],
				'manual_execute' => 0,
			];
		}

		if ($inserts)
		{
			$db->insertBulk('xf_job', $inserts);
		}

		return [
			$next,
			"$next / $stepData[max]",
			$stepData,
		];
	}

	public function step12()
	{
		$this->executeUpgradeQuery('DROP TABLE xf_mail_queue');
	}

	public function step13(int $position, array $stepData)
	{
		return $this->removeCriteriaRules(
			'xf_notice',
			['notice_id'],
			'page_criteria',
			['from_search'],
			$position,
			$stepData
		);
	}

	public function step14(): void
	{
		$this->alterTable('xf_style', function (Alter $table)
		{
			$table->addColumn('enable_variations', 'tinyint', 3)->setDefault(1)->after('last_modified_date');
		});

		// the style cache will be rebuilt later
		$this->db()->update('xf_style', ['enable_variations' => 0], null);

		$this->alterTable('xf_style_property', function (Alter $table)
		{
			$table->addColumn('has_variations', 'tinyint')->setDefault(0)->after('value_parameters');
		});

		$this->alterTable('xf_user', function (Alter $table)
		{
			$table->addColumn('style_variation', 'varchar', 50)->setDefault('')->after('style_id');
		});

		$this->alterTable('xf_admin', function (Alter $table)
		{
			$table->addColumn('admin_style_variation', 'varchar', 50)->setDefault('')->after('permission_cache');
		});
	}

	public function step15(): void
	{
		$tables = [
			'xf_attachment_view',
			'xf_session',
			'xf_session_activity',
			'xf_session_admin',
			'xf_session_install',
			'xf_thread_view',
		];

		$defaultEngine = \XF::config('db')['engine'] ?? 'InnoDb';
		if ($defaultEngine !== 'InnoDb')
		{
			\XF::logError('During upgrade to XenForo 2.3.0 we could not convert some tables to InnoDb: ' . implode(', ', $tables));
			return;
		}

		foreach ($tables AS $tableName)
		{
			$this->alterTable($tableName, function (Alter $table)
			{
				$table->engine('InnoDB');
			});
		}
	}

	public function step16(): void
	{
		$this->alterTable('xf_attachment_data', function (Alter $table)
		{
			$table->addColumn('optimized', 'tinyint')->setDefault(0)->after('upload_date');
		});

		$this->alterTable('xf_user', function (Alter $table)
		{
			$table->addColumn('avatar_optimized', 'tinyint')->setDefault(0)->after('avatar_highdpi');
		});

		$this->alterTable('xf_user_profile', function (Alter $table)
		{
			$table->addColumn('banner_optimized', 'tinyint')->setDefault(0)->after('banner_position_y');
		});
	}

	public function step17()
	{
		$this->createTable('xf_oauth_client', function (Create $table)
		{
			$table->addColumn('client_id', 'varchar', 16);
			$table->addColumn('client_secret', 'varchar', 32);
			$table->addColumn('client_type', 'enum')->values(['confidential', 'public'])->setDefault('confidential');
			$table->addColumn('title', 'varchar', 50);
			$table->addColumn('description', 'text')->nullable();
			$table->addColumn('image_url', 'varchar', 200)->nullable();
			$table->addColumn('homepage_url', 'varchar', 200)->nullable();
			$table->addColumn('redirect_uris', 'blob');
			$table->addColumn('active', 'tinyint', 3)->setDefault(1);
			$table->addColumn('creation_user_id', 'int')->setDefault(0);
			$table->addColumn('creation_date', 'int')->setDefault(0);
		});

		$this->createTable('xf_oauth_code', function (Create $table)
		{
			$table->addColumn('code', 'varchar', 64);
			$table->addColumn('oauth_request_id', 'varchar', 255);
			$table->addColumn('expiry_date', 'int')->setDefault(0);
			$table->addPrimaryKey('code');
		});

		$this->createTable('xf_oauth_token', function (Create $table)
		{
			$table->addColumn('token_id', 'int')->autoIncrement();
			$table->addColumn('token', 'varchar', 64);
			$table->addColumn('client_id', 'varchar', 16);
			$table->addColumn('user_id', 'int');
			$table->addColumn('issue_date', 'int')->setDefault(0);
			$table->addColumn('last_use_date', 'int')->setDefault(0);
			$table->addColumn('expiry_date', 'int');
			$table->addColumn('revoked_date', 'int')->setDefault(0);
			$table->addColumn('scopes', 'blob');
			$table->addUniqueKey('token');
			$table->addKey('expiry_date');
		});
	}

	public function step18()
	{
		$this->createTable('xf_oauth_refresh_token', function (Create $table)
		{
			$table->addColumn('refresh_token_id', 'int')->autoIncrement();
			$table->addColumn('token_id', 'int');
			$table->addColumn('refresh_token', 'text');
			$table->addColumn('client_id', 'text');
			$table->addColumn('issue_date', 'int')->setDefault(0);
			$table->addColumn('expiry_date', 'int');
			$table->addColumn('revoked_date', 'int')->setDefault(0);
		});

		$this->createTable('xf_oauth_request', function (Create $table)
		{
			$table->addColumn('oauth_request_id', 'varchar', 255);
			$table->addColumn('client_id', 'text');
			$table->addColumn('user_id', 'int');
			$table->addColumn('response_type', 'text');
			$table->addColumn('redirect_uri', 'text');
			$table->addColumn('state', 'text')->nullable();
			$table->addColumn('code_challenge', 'text')->nullable();
			$table->addColumn('code_challenge_method', 'text')->nullable();
			$table->addColumn('request_date', 'int')->setDefault(0);
			$table->addColumn('scopes', 'blob');
		});

		$this->executeUpgradeQuery("
			INSERT INTO `xf_connected_account_provider`
				(`provider_id`, `provider_class`, `display_order`, `options`)
			VALUES
				('xenforo', 'XF:Provider\\\\XenForo', 90, '')
		");
	}
}
