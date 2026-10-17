<?php

namespace XF\Install;

use XF\App;
use XF\Db\AbstractAdapter;
use XF\Entity\Admin;
use XF\Entity\Moderator;
use XF\Entity\User;
use XF\Entity\UserAuth;
use XF\Finder\PermissionFinder;
use XF\Install\Data\MySql;
use XF\Job\AddOnData;
use XF\Job\Atomic;
use XF\Job\CoreCacheRebuild;
use XF\Job\IconUsage;
use XF\Job\PermissionRebuild;
use XF\Job\PhraseRebuild;
use XF\Job\StylePropertyRebuild;
use XF\Job\TemplateRebuild;
use XF\Job\UpgradeCheck;
use XF\Repository\OptionRepository;
use XF\Repository\UserRepository;
use XF\Service\UpdatePermissionsService;
use XF\Util\File;
use XF\Util\Php;
use XF\Util\Random;

use function count, defined, in_array, is_array, is_string;

class Helper
{
	/**
	 * @var App
	 */
	protected $app;

	/**
	 * @var list<string> PHP functions required for XenForo functionality
	 */
	protected $requiredFunctions = [
		'fpassthru',
		'ini_set',
	];

	/**
	 * @var list<string> PHP functions recommended for XenForo functionality
	 */
	protected $recommendedFunctions = [
		'escapeshellarg',
		'exec',
		'exif_read_data',
		'fsockopen',
		'popen',
		'proc_open',
		'set_time_limit',
	];

	public function __construct(App $app)
	{
		$this->app = $app;
	}

	/**
	 * @return array<string, string|\Stringable>
	 */
	public function getRequirementErrors(?AbstractAdapter $db = null)
	{
		$errors = [];

		$phpVersion = phpversion();
		if (version_compare($phpVersion, '7.2.0', '<'))
		{
			$errors['phpVersion'] = \XF::phrase('php_version_x_does_not_meet_requirements', ['version' => $phpVersion]);
		}

		if (!function_exists('mysqli_connect'))
		{
			$errors['mysqlPhp'] = \XF::phrase('required_php_extension_x_not_found', ['extension' => 'MySQLi']);
		}

		if (!function_exists('gd_info'))
		{
			$errors['gd'] = \XF::phrase('required_php_extension_x_not_found', ['extension' => 'GD']);
		}
		else if (!function_exists('imagecreatefromjpeg'))
		{
			$errors['gdJpeg'] = \XF::phrase('gd_jpeg_support_missing');
		}

		if (!function_exists('iconv'))
		{
			$errors['iconv'] = \XF::phrase('required_php_extension_x_not_found', ['extension' => 'Iconv']);
		}

		if (!function_exists('ctype_alnum'))
		{
			$errors['ctype'] = \XF::phrase('required_php_extension_x_not_found', ['extension' => 'Ctype']);
		}

		if (!function_exists('fpassthru'))
		{
			$errors['fpassthru'] = \XF::phrase('required_php_function_x_not_found', ['function' => 'fpassthru']);
		}

		if (!function_exists('preg_replace'))
		{
			$errors['pcre'] = \XF::phrase('required_php_extension_x_not_found', ['extension' => 'PCRE']);
		}
		else
		{
			try
			{
				preg_match('/./su', 'x');
			}
			catch (\Exception $e)
			{
				$errors['pcre'] = \XF::phrase('pcre_unicode_support_missing');
			}

			try
			{
				preg_match('/\p{C}/u', 'x');
			}
			catch (\Exception $e)
			{
				$errors['pcre'] = \XF::phrase('pcre_unicode_property_support_missing');
			}
		}

		if (!function_exists('spl_autoload_register'))
		{
			$errors['spl'] = \XF::phrase('required_php_extension_x_not_found', ['extension' => 'SPL']);
		}

		if (!function_exists('json_encode'))
		{
			$errors['json'] = \XF::phrase('required_php_extension_x_not_found', ['extension' => 'JSON']);
		}

		if (!extension_loaded('curl'))
		{
			$errors['curl'] = \XF::phrase('required_php_extension_x_not_found', ['extension' => 'cURL']);
		}

		if (!extension_loaded('mbstring'))
		{
			$errors['mbstring'] = \XF::phrase('required_php_extension_x_not_found', ['extension' => 'mbstring']);
		}

		if (!class_exists('DOMDocument') || !class_exists('SimpleXMLElement'))
		{
			$errors['xml'] = \XF::phrase('required_php_xml_extensions_not_found');
		}

		if ($db)
		{
			$mySqlVersion = Php::getCompatibleMySqlVersion(
				$db->getServerVersion(),
				$db->getConnection()->server_info
			);
			if ($mySqlVersion && version_compare(strtolower($mySqlVersion), '5.7', '<'))
			{
				$errors['mysqlVersion'] = \XF::phrase('mysql_version_x_does_not_meet_requirements', ['version' => $mySqlVersion]);
			}
		}

		$dataDir = File::canonicalizePath($this->app->config('externalDataPath'));
		if (!is_dir($dataDir) || !is_writable($dataDir))
		{
			$errors['dataDir'] = \XF::phrase('directory_x_must_be_writable', ['directory' => $dataDir]);
		}
		else
		{
			foreach (scandir($dataDir) AS $file)
			{
				if ($file[0] == '.')
				{
					continue;
				}

				$fullPath = "$dataDir/$file";
				if (is_dir($fullPath) && !is_writable($fullPath))
				{
					$errors['dataDir'] = \XF::phrase('all_directories_under_x_must_be_writable', ['directory' => $dataDir]);
				}
			}
		}

		$internalDataDir = File::canonicalizePath($this->app->config('internalDataPath'));
		if (!is_dir($internalDataDir) || !is_writable($internalDataDir))
		{
			$errors['internalDataDir'] = \XF::phrase('directory_x_must_be_writable', ['directory' => $internalDataDir]);
		}
		else
		{
			foreach (scandir($internalDataDir) AS $file)
			{
				if ($file[0] == '.')
				{
					continue;
				}

				$fullPath = "$internalDataDir/$file";
				if (is_dir($fullPath) && !is_writable($fullPath))
				{
					$errors['internalDataDir'] = \XF::phrase('all_directories_under_x_must_be_writable', ['directory' => $internalDataDir]);
				}
			}
		}

		$disabled = $this->getDisabledFunctions();
		if ($disabled === false)
		{
			$disabled = [];
			$errors['ini_get'] = \XF::phrase('php_functions_disabled_impossible_check');
		}

		foreach ($this->requiredFunctions AS $fn)
		{
			if (in_array($fn, $disabled) || !function_exists($fn))
			{
				$errors[$fn] = \XF::phrase('php_function_x_disabled_fundamental', ['function' => $fn]);
			}
		}

		return $errors;
	}

	/**
	 * @return array<string, string|\Stringable>
	 */
	public function getRequirementWarnings(?AbstractAdapter $db = null)
	{
		$warnings = [];

		$disabled = $this->getDisabledFunctions();
		if ($disabled === false)
		{
			$disabled = [];
			$warnings['ini_get'] = \XF::phrase('php_functions_disabled_impossible_check');
		}

		foreach ($this->recommendedFunctions AS $fn)
		{
			if (in_array($fn, $disabled) || !function_exists($fn))
			{
				$warnings[$fn] = \XF::phrase('php_function_x_disabled_warning', ['function' => $fn]);
			}
		}

		if (!in_array('https', stream_get_wrappers()))
		{
			if (!function_exists('curl_version') || !defined('CURL_VERSION_SSL'))
			{
				$warnings['https'] = \XF::phrase('php_no_ssl_support');
			}
			else
			{
				$curl = curl_version();
				if (!($curl['features'] & CURL_VERSION_SSL))
				{
					$warnings['https'] = \XF::phrase('php_no_ssl_support');
				}
			}
		}

		if (Random::getLastUsedSource() == 'internal')
		{
			$warnings['random'] = \XF::phrase('php_weak_random_values');
		}

		$phpVersion = phpversion();
		if (version_compare($phpVersion, '7.2.0', '<'))
		{
			$warnings['phpVersion'] = \XF::phrase('php_version_x_outdated_upgrade', ['version' => $phpVersion]);
		}

		if (PHP_INT_SIZE === 4)
		{
			$warnings['phpMaxInt'] = \XF::phrase('php_max_int_size_warning');
		}

		return $warnings;
	}

	/**
	 * @return list<string>|false
	 */
	protected function getDisabledFunctions()
	{
		try
		{
			$disabled = ini_get('disable_functions');
		}
		catch (\Exception $e)
		{
			return false;
		}

		return array_map(function ($fn)
		{
			return trim(strtolower($fn));
		}, explode(',', $disabled));
	}

	/**
	 * @return list<string>
	 */
	public function deleteApplicationTables()
	{
		$db = $this->app->db();

		$removed = [];
		foreach ($db->fetchAllColumn('SHOW TABLES') AS $table)
		{
			if ($this->isApplicationTable($table))
			{
				$removed[] = $table;
				$db->query('DROP TABLE ' . $table);
			}
		}

		return $removed;
	}

	/**
	 * @return bool
	 */
	public function hasApplicationTables(?AbstractAdapter $db = null)
	{
		$db = $db ?: $this->app->db();

		foreach ($db->fetchAllColumn('SHOW TABLES') AS $table)
		{
			if ($this->isApplicationTable($table))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string
	 *
	 * @return bool
	 */
	public function isApplicationTable($table)
	{
		return (
			substr($table, 0, 3) == 'xf_'
			|| substr($table, 0, 11) == 'xengallery_'
		);
	}

	/**
	 * @param int $maxExecution
	 * @param int $startOffset
	 * @param int|false $endOffset
	 *
	 * @return list<string>
	 */
	public function createApplicationTables($maxExecution = 0, $startOffset = 0, &$endOffset = false)
	{
		$mySql = new MySql();
		$tables = $mySql->getTables();
		$sm = $this->app->db()->getSchemaManager();

		$s = microtime(true);
		$i = -1;
		$endOffset = false;

		foreach ($tables AS $tableName => $definition)
		{
			$i++;
			if ($i < $startOffset)
			{
				continue;
			}

			$sm->createTable($tableName, $definition);

			if ($maxExecution && microtime(true) - $s > $maxExecution)
			{
				// start at the next one
				$endOffset = $i + 1;
				break;
			}
		}

		return array_keys($tables);
	}

	/**
	 * @return int
	 */
	public function insertDefaultData()
	{
		$mySql = new MySql();
		$data = $mySql->getData();
		$db = $this->app->db();

		foreach ($data AS $dataQuery)
		{
			$db->query($dataQuery);
		}

		return count($data);
	}

	public function createDirectories()
	{
		$internalDataDir = File::canonicalizePath($this->app->config('internalDataPath'));

		$dirs = [
			$internalDataDir . '/temp',
			$internalDataDir . '/code_cache',
		];
		foreach ($dirs AS $dir)
		{
			File::createDirectory($dir, true);
		}
	}

	/**
	 * @param array{username: string, email: string} $baseData
	 * @param string $password
	 *
	 * @return User
	 */
	public function createInitialUser(array $baseData, $password)
	{
		$this->app->db()->beginTransaction();

		/** @var UserRepository $userRepo */
		$userRepo = $this->app->repository(UserRepository::class);
		$user = $userRepo->setupBaseUser();
		$user->setOption('admin_edit', true);
		$user->bulkSet($baseData);
		$user->user_state = 'valid';
		$user->is_staff = true;

		/** @var UserAuth $auth */
		$auth = $user->getRelationOrDefault('Auth');
		$auth->setPassword($password);

		$user->save();

		/** @var Admin $admin */
		$admin = $user->getRelationOrDefault('Admin', false);
		$admin->is_super_admin = true;
		$admin->extra_user_group_ids = [User::GROUP_ADMIN];
		$admin->permission_cache = $this->app->db()->fetchAllColumn("
			SELECT admin_permission_id
			FROM xf_admin_permission
		");
		$admin->save();

		$moderator = $this->app->em()->create(Moderator::class);
		$moderator->user_id = $user->user_id;
		$moderator->is_super_moderator = true;
		$moderator->extra_user_group_ids = [User::GROUP_MOD];
		$moderator->save();

		$permissions = $this->app->finder(PermissionFinder::class)
			->where('Interface.is_moderator', 1)
			->where('permission_type', 'flag') // all that's supported
			->fetch();

		$permissionValues = [];
		foreach ($permissions AS $permission)
		{
			$permissionValues[$permission->permission_group_id][$permission->permission_id] = 'allow';
		}

		/** @var UpdatePermissionsService $permissionUpdater */
		$permissionUpdater = $this->app->service(UpdatePermissionsService::class);
		$permissionUpdater->setUser($user)->setGlobal();
		$permissionUpdater->updatePermissions($permissionValues);

		$this->app->db()->commit();

		return $user;
	}

	public function hasInitialUser(): bool
	{
		return (bool) $this->app->em()->find(User::class, 1);
	}

	public function completeInstallation()
	{
		$this->writeInstallLock();
		$this->insertUpgradeLog(\XF::$versionId, null, 'install');
		$this->updateVersion();

		// ensure we do an initial upgrade check and set up for future checks
		$this->enqueueUpgradeCheck();

		// we trigger some user changes here -- simplest to just clear this out
		$this->app->db()->emptyTable('xf_change_log');
	}

	public function writeInstallLock()
	{
		File::writeInstallLock();
	}

	/**
	 * @param int $versionId
	 * @param int|null $lastStep
	 * @param 'install'|'upgrade' $type
	 */
	public function insertUpgradeLog($versionId, $lastStep = null, $type = 'upgrade')
	{
		$this->app->db()->insert('xf_upgrade_log', [
			'version_id' => $versionId,
			'last_step' => $lastStep,
			'completion_date' => $lastStep === null ? time() : 0,
			'log_type' => $type,
		], false, 'last_step = VALUES(last_step), completion_date = VALUES(completion_date)');
	}

	public function updateVersion()
	{
		/** @var OptionRepository $optionRepo */
		$optionRepo = $this->app->repository(OptionRepository::class);
		$optionRepo->updateOption('currentVersionId', \XF::$versionId);

		$this->app->db()->update(
			'xf_addon',
			['version_id' => \XF::$versionId, 'version_string' => \XF::$version],
			"addon_id = 'XF'"
		);

		$this->app->addOnDataManager()->rebuildActiveAddOnCache();
	}

	public function enqueueUpgradeCheck()
	{
		$this->app->jobManager()->enqueueUnique('xfUpgradeCheck', UpgradeCheck::class, [], false);
	}

	/**
	 * @return bool
	 */
	public function isInstalled()
	{
		return (
			File::installLockExists()
			&& ($this->app->config('exists') || $this->app->config('legacyExists'))
		);
	}

	/**
	 * @param array<string, mixed>
	 *
	 * @return string
	 */
	public function generateConfig(array $config)
	{
		$esc = "'\\";

		$lines = [];
		foreach ($config AS $key => $value)
		{
			if (is_array($value))
			{
				if (empty($value))
				{
					continue;
				}
				foreach ($value AS $subKey => $subValue)
				{
					$lines[] = '$config[\'' . addcslashes($key, $esc) . '\'][\'' . addcslashes($subKey, $esc) . '\'] = \'' . addcslashes($subValue, $esc) . '\';';
				}
				$lines[] = '';
			}
			else
			{
				$lines[] = '$config[\'' . addcslashes($key, $esc) . '\'] = ' . var_export($value, true) . ';';
			}
		}

		return "<?php\n\n" . implode("\n", $lines);
	}

	/**
	 * @param string|null $jobName
	 * @param list<array{string, array<string, mixed>}> $extraJobs
	 * @param bool $withCoreData
	 * @param int|null $forUpgradeFrom
	 */
	public function insertRebuildJob($jobName = null, array $extraJobs = [], $withCoreData = true, $forUpgradeFrom = null)
	{
		if (!$jobName)
		{
			$jobName = $this->getDefaultRebuildJobName();
		}

		$jobs = [];
		if ($withCoreData)
		{
			$jobs[] = [AddOnData::class, ['addon_id' => 'XF']];
		}

		// The 2.0 upgrade wipes out phrase and template maps which can lead to incomplete builds.
		// Force a full rebuild here to ensure everything is ok (though it will usually be duplicated work).
		$fromPre20 = ($forUpgradeFrom !== null && $forUpgradeFrom < 2000010);
		$skipCoreRebuild = $fromPre20 ? false : true;

		$jobs[] = [PhraseRebuild::class, ['skipCore' => $skipCoreRebuild]];
		$jobs[] = [TemplateRebuild::class, ['skipCore' => $skipCoreRebuild]];
		$jobs[] = StylePropertyRebuild::class;
		$jobs[] = PermissionRebuild::class;
		$jobs[] = CoreCacheRebuild::class;
		$jobs[] = IconUsage::class;

		foreach ($extraJobs AS $key => $value)
		{
			if (is_string($key) && is_array($value))
			{
				$jobs[] = [$key, $value];
			}
			else
			{
				$jobs[] = $value;
			}
		}

		$this->app->jobManager()->enqueueUnique($jobName, Atomic::class, ['execute' => $jobs]);
	}

	/**
	 * @return string
	 */
	public function getDefaultRebuildJobName()
	{
		return 'xfInstallUpgrade';
	}

	/**
	 * @param string|null $jobName
	 *
	 * @return bool
	 */
	public function hasRebuildJobPending($jobName = null)
	{
		if (!$jobName)
		{
			$jobName = $this->getDefaultRebuildJobName();
		}

		$job = $this->app->jobManager()->getUniqueJob($jobName);
		return ($job ? true : false);
	}
}
