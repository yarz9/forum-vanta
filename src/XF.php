<?php

use Composer\Autoload\ClassLoader;
use League\Flysystem\MountManager;
use XF\App;
use XF\ComposerAutoload;
use XF\Container;
use XF\DataRegistry;
use XF\Db\AbstractAdapter;
use XF\Entity\ApiKey;
use XF\Entity\OAuthToken;
use XF\Entity\User;
use XF\Extension;
use XF\Http\Request;
use XF\InputFilterer;
use XF\Language;
use XF\Mail\Mailer;
use XF\Mvc\Entity\ArrayValidator;
use XF\Mvc\Entity\Manager;
use XF\Options;
use XF\PermissionCache;
use XF\Phrase;
use XF\PreEscaped;
use XF\PreEscapedInterface;
use XF\PrintableException;
use XF\Repository\ApiRepository;
use XF\Repository\UserRepository;
use XF\Session\Session;
use XF\StringBuilder;
use XF\Template\Template;
use XF\Util\Php;
use XF\Util\Random;

/**
 * Basic setup class and facade into app-specific configurations and the DIC.
 */
class XF
{
	/**
	 * Current printable and encoded versions. These are used for visual output
	 * and installation/upgrading.
	 *
	 * @var string
	 * @var int
	 */
	public static $version = '2.3.6';
	public static $versionId = 2030670; // abbccde = a.b.c d (alpha: 1, beta: 3, RC: 5, stable: 7, PL: 9) e

	public const XF_API_URL = '';
	public const XF_LICENSE_KEY = '';

	public const API_VERSION = 1;

	protected static $memoryLimit = null;

	/**
	 * @var ClassLoader
	 */
	public static $autoLoader = null;

	public static $debugMode = false;
	public static $developmentMode = false;

	/**
	 * @var int
	 */
	public static $time = 0;

	public static $DS = DIRECTORY_SEPARATOR;

	protected static $rootDirectory = '.';
	protected static $sourceDirectory = '.';

	protected static $app = null;

	/**
	 * @var User
	 */
	protected static $visitor = null;

	/**
	 * @var User
	 */
	protected static $preRegActionUser = null;

	/**
	 * @var Language
	 */
	protected static $language = null;

	/**
	 * @var ApiKey|null
	 */
	protected static $apiKey = null;

	/**
	 * @var OAuthToken|null
	 */
	protected static $accessToken = null;

	/**
	 * @var bool
	 */
	protected static $apiBypassPermissions = false;

	/**
	 * Starts the XF framework and standardized the environment.
	 */
	public static function start($rootDirectory)
	{
		self::bootstrap($rootDirectory);
		self::standardizeEnvironment();
		self::startAutoloader();
		self::setupClassAliases();
		self::startSystem();
	}

	public static function bootstrap(string $rootDirectory): void
	{
		self::$time = time();
		self::$rootDirectory = $rootDirectory;
		self::$sourceDirectory = __DIR__;
	}

	/**
	 * Sets up the PHP environment in the XF-expected way
	 */
	public static function standardizeEnvironment()
	{
		ignore_user_abort(true);

		self::setMemoryLimit(128 * 1024 * 1024);

		error_reporting(E_ALL & ~E_DEPRECATED);

		date_default_timezone_set('UTC');
		setlocale(LC_ALL, 'C');
		mb_internal_encoding('UTF-8');

		// if you really need to load a phar file, you can call stream_wrapper_restore('phar');
		@stream_wrapper_unregister('phar');

		@ini_set('output_buffering', false);
		@ini_set('default_charset', 'UTF-8');
		@ini_set('zlib.output_compression', false);

		if (PHP_VERSION_ID >= 70100)
		{
			@ini_set('serialize_precision', -1);
		}

		if (PHP_VERSION_ID >= 70400)
		{
			// since we catch exceptions, turn this off to aid debugging
			@ini_set('zend.exception_ignore_args', false);
		}

		if (PHP_VERSION_ID >= 80100)
		{
			mysqli_report(MYSQLI_REPORT_OFF);
		}

		// see http://bugs.php.net/bug.php?id=36514
		// and http://xenforo.com/community/threads/53637/
		if (!@ini_get('output_handler'))
		{
			$level = ob_get_level();
			while ($level)
			{
				@ob_end_clean();
				$newLevel = ob_get_level();
				if ($newLevel >= $level)
				{
					break;
				}
				$level = $newLevel;
			}
		}
	}

	/**
	 * Handler for set_error_handler to convert notices, warnings, and other errors
	 * into exceptions.
	 *
	 * @param int $errorType Type of error (one of the E_* constants)
	 * @param string $errorString
	 * @param string $file
	 * @param int $line
	 *
	 * @throws \ErrorException
	 */
	public static function handlePhpError($errorType, $errorString, $file, $line)
	{
		if ($errorType & error_reporting())
		{
			$errorString = '[' . Php::convertErrorCodeToString($errorType) . '] ' . $errorString;

			$trigger = true;

			$isDevError = (
				$errorType & E_DEPRECATED ||
				$errorType & E_USER_DEPRECATED ||
				(PHP_VERSION_ID < 70400 && $errorType & E_STRICT) // PHP 7.4+ does not use strict errors
			);

			if (!self::$debugMode)
			{
				// production (non-debug) mode
				if ($isDevError)
				{
					// do not log anything for these, they're developer notices
					$trigger = false;
				}
				else if ($errorType & E_NOTICE || $errorType & E_USER_NOTICE)
				{
					// minor developer issues, log and let execution continue
					$trigger = false;
					$e = new \ErrorException($errorString, 0, $errorType, $file, $line);
					self::app()->logException($e);
				}
			}
			else
			{
				// debug mode specific behaviors
				if ($isDevError && preg_match('#src(/|\\\\)vendor(/|\\\\)#', $file))
				{
					// dev/deprecation error in a vendor library, log but let execution continue
					$trigger = false;
					$e = new \ErrorException($errorString, 0, $errorType, $file, $line);
					self::app()->logException($e);
				}
			}

			if ($trigger)
			{
				throw new \ErrorException($errorString, 0, $errorType, $file, $line);
			}
		}
	}

	/**
	 * Default exception handler.
	 *
	 * @param \Exception $e
	 */
	public static function handleException($e)
	{
		$app = self::app();
		$app->logException($e, true); // exiting so rollback
		$app->displayFatalExceptionMessage($e);
	}

	/**
	 * @param \Exception|\Throwable $e
	 * @param bool $rollback
	 * @param string $messagePrefix
	 * @param bool $forceLog
	 */
	public static function logException($e, $rollback = false, $messagePrefix = '', $forceLog = false)
	{
		self::app()->error()->logException($e, $rollback, $messagePrefix, $forceLog);
	}

	public static function logError($message, $forceLog = false)
	{
		self::app()->error()->logError($message, $forceLog);
	}

	/**
	 * Try to log fatal errors so that debugging is easier.
	 */
	public static function handleFatalError()
	{
		$error = @error_get_last();
		if (!$error)
		{
			return;
		}

		if (empty($error['type']) || !($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)))
		{
			return;
		}

		try
		{
			self::app()->logException(
				new \ErrorException("Fatal Error: " . $error['message'], $error['type'], 1, $error['file'], $error['line']),
				true
			);
		}
		catch (\Exception $e)
		{
		}
	}

	/**
	 * Sets up XF's autoloader
	 */
	public static function startAutoloader()
	{
		if (self::$autoLoader)
		{
			return;
		}

		/** @var ClassLoader $autoLoader */
		$autoLoader = require __DIR__ . '/vendor/autoload.php';

		self::$autoLoader = $autoLoader;
	}

	public static function registerComposerAutoloadData(string $pathPrefix, array $data, $prepend = false)
	{
		$composerAutoload = new ComposerAutoload(self::app(), $pathPrefix);

		if (!method_exists($composerAutoload, 'checkPaths'))
		{
			return;
		}

		$composerAutoload->checkPaths(false);

		if ($data['namespaces'])
		{
			$composerAutoload->autoloadNamespaces($prepend);
		}
		if ($data['psr4'])
		{
			$composerAutoload->autoloadPsr4($prepend);
		}
		if ($data['classmap'])
		{
			$composerAutoload->autoloadClassmap();
		}
		if ($data['files'])
		{
			$composerAutoload->autoloadFiles();
		}
	}

	public static function registerComposerAutoloadDir($dir, $prepend = false)
	{
		try
		{
			$composerAutoload = new ComposerAutoload(self::app(), $dir);
			$composerAutoload->checkPaths(true);
			$composerAutoload->autoloadAll($prepend);
		}
		catch (\Exception $e)
		{
			if (\XF::$debugMode)
			{
				throw $e;
			}
			else
			{
				self::logException($e, true, 'Error registering composer autoload directory: ');
			}
		}
	}

	/**
	 * Support BC-safe class renames
	 */
	public static function setupClassAliases(): void
	{
		spl_autoload_register(
			[self::class, 'createAliasForClass'],
			true,
			true
		);
	}

	/**
	 * @var array<string, bool>
	 */
	protected static $classAliasLock = [];

	/**
	 * Takes a FQCN and registers its alias.
	 */
	public static function createAliasForClass(string $class): void
	{
		if (static::$classAliasLock[$class] ?? false)
		{
			return;
		}

		$alias = self::getAliasForClass($class);
		if ($alias === $class)
		{
			$class = self::getClassForAlias($alias);
		}

		if ($class === $alias)
		{
			return;
		}

		static::$classAliasLock[$class] = true;
		static::$classAliasLock[$alias] = true;

		spl_autoload_call($class);

		if (class_exists($class, false))
		{
			class_alias($class, $alias, false);
			unset(static::$classAliasLock[$class]);
			unset(static::$classAliasLock[$alias]);
			return;
		}

		spl_autoload_call($alias);

		if (class_exists($alias, false))
		{
			class_alias($alias, $class, false);
			unset(static::$classAliasLock[$class]);
			unset(static::$classAliasLock[$alias]);
			return;
		}

		unset(static::$classAliasLock[$class]);
		unset(static::$classAliasLock[$alias]);
	}

	public static function getAliasForClass(string $class): string
	{
		if (strpos($class, '\\') === false)
		{
			return $class;
		}

		foreach (self::getUnaliasableNamespaces() AS $namespace)
		{
			if (stripos($class, $namespace . '\\') === 0)
			{
				return $class;
			}
		}

		foreach (self::getAliasableNamespaces() AS $namespace => $suffix)
		{
			if (!is_string($namespace))
			{
				$namespace = $suffix;
			}

			if (stripos($class, '\\' . $namespace . '\\') === false)
			{
				continue;
			}

			if (stripos($class, $suffix, -strlen($suffix)) === false)
			{
				return $class;
			}

			return substr($class, 0, -strlen($suffix));
		}

		return $class;
	}

	public static function getClassForAlias(string $alias): string
	{
		if (strpos($alias, '\\') === false)
		{
			return $alias;
		}

		foreach (self::getUnaliasableNamespaces() AS $namespace)
		{
			if (stripos($alias, $namespace . '\\') === 0)
			{
				return $alias;
			}
		}

		foreach (self::getAliasableNamespaces() AS $namespace => $suffix)
		{
			if (!is_string($namespace))
			{
				$namespace = $suffix;
			}

			if (stripos($alias, '\\' . $namespace . '\\') === false)
			{
				continue;
			}

			if (stripos($alias, $suffix, -strlen($suffix)) !== false)
			{
				return $alias;
			}

			return $alias . $suffix;
		}

		return $alias;
	}

	/**
	 * @return array<int|string, string>
	 */
	public static function getAliasableNamespaces(): array
	{
		return [
			'ConnectedAccount\\Service' => 'Service',
			'Service',

			'ActivityLog' => 'Handler',
			'ActivitySummary' => 'Section',
			'AdminSearch' => 'Handler',
			'Alert' => 'Handler',
			'ApprovalQueue' => 'Handler',
			'Attachment' => 'Handler',
			'Bookmark' => 'Handler',
			'ChangeLog' => 'Handler',
			'ConnectedAccount\\Provider' => 'Provider',
			'ConnectedAccount\\ProviderData' => 'ProviderData',
			'ContentVote' => 'Handler',
			'Controller',
			'ControllerPlugin' => 'Plugin',
			'Criteria',
			'EditHistory' => 'Handler',
			'EmailStop' => 'Handler',
			'EmbedResolver' => 'Handler',
			'FeaturedContent' => 'Handler',
			'Finder',
			'FindNew' => 'Handler',
			'ForumType' => 'Handler',
			'InlineMod' => 'Handler',
			'Like' => 'Handler',
			'LogSearch' => 'Handler',
			'ModeratorLog' => 'Handler',
			'NewsFeed' => 'Handler',
			'NodeType' => 'Handler',
			'Poll' => 'Handler',
			'Reaction' => 'Handler',
			'Report' => 'Handler',
			'Repository',
			'Sitemap' => 'Handler',
			'Stats' => 'Handler',
			'Tag' => 'Handler',
			'ThreadType' => 'Handler',
			'TrendingContent' => 'Handler',
			'Warning' => 'Handler',
			'Webhook\\Event' => 'Handler',
		];
	}

	/**
	 * @return list<string>
	 */
	public static function getUnaliasableNamespaces(): array
	{
		return [
			'Authy',
			'Aws',
			'Base32',
			'Base64Url',
			'Braintree',
			'Brick',
			'Doctrine',
			'Egulias',
			'FG',
			'GuzzleHttp',
			'Interop',
			'JmesPath',
			'Jose',
			'JoyPixels',
			'Laminas',
			'League',
			'Minishlink',
			'OAuth',
			'Otp',
			'ParagonIE',
			'Pelago',
			'Psr',
			'Sabberworm',
			'Stripe',
			'Symfony',
			'TrueBV',
			'enshrined',
			'lbuchs',
		];
	}

	public static function startSystem()
	{
		register_shutdown_function(['XF', 'triggerRunOnce']);

		require __DIR__ . '/utf8.php';

		set_error_handler(['XF', 'handlePhpError']);
		set_exception_handler(['XF', 'handleException']);
		register_shutdown_function(['XF', 'handleFatalError']);
	}

	protected static $runOnce = [];

	public static function runOnce($key, \Closure $fn)
	{
		if (isset(self::$runOnce[$key]))
		{
			// if this key already exists, allow a new function with the
			// same key to replace it and move to the end of the queue.
			unset(self::$runOnce[$key]);
		}
		self::$runOnce[$key] = $fn;
	}

	public static function runLater(\Closure $fn)
	{
		self::$runOnce[] = $fn;
	}

	/**
	 * Dequeues running of a specific run once action.
	 *
	 * @param string $key
	 *
	 * @return bool True if an action was dequeue
	 */
	public static function dequeueRunOnce($key): bool
	{
		if (isset(self::$runOnce[$key]))
		{
			unset(self::$runOnce[$key]);

			return true;
		}
		else
		{
			return false;
		}
	}

	public static function triggerRunOnce($rethrow = false)
	{
		$i = 0;

		do
		{
			foreach (self::$runOnce AS $key => $fn)
			{
				unset(self::$runOnce[$key]);

				try
				{
					$fn();
				}
				catch (\Exception $e)
				{
					self::logException($e, true);
					// can't know if we have an open transaction from before so have to roll it back

					if ($rethrow)
					{
						throw $e;
					}
				}
			}

			$i++;
		}
		while (self::$runOnce && $i < 5);
	}

	public static function getRootDirectory()
	{
		return self::$rootDirectory;
	}

	public static function getSourceDirectory()
	{
		return self::$sourceDirectory;
	}

	public static function getAddOnDirectory()
	{
		return \XF::getSourceDirectory() . self::$DS . 'addons';
	}

	/**
	 * @param int|null $versionId
	 * @param string|null $operator
	 *
	 * @return int|bool
	 */
	public static function isAddOnActive(
		string $addOnId,
		$versionId = null,
		$operator = '>='
	)
	{
		$addOns = \XF::app()->container('addon.cache');
		if (!isset($addOns[$addOnId]))
		{
			return false;
		}

		$activeVersionId = $addOns[$addOnId];
		if ($versionId === null)
		{
			return $activeVersionId;
		}

		switch ($operator)
		{
			case '>':
				return ($activeVersionId > $versionId);

			case '>=':
				return ($activeVersionId >= $versionId);

			case '<':
				return ($activeVersionId < $versionId);

			case '<=':
				return ($activeVersionId <= $versionId);
		}

		return $activeVersionId;
	}

	public static function getVendorDirectory()
	{
		return \XF::getSourceDirectory() . self::$DS . 'vendor';
	}

	public static function updateTime()
	{
		self::$time = time();
	}

	/**
	 * @param App $app
	 */
	public static function setApp(App $app)
	{
		if (self::$app)
		{
			throw new \LogicException(
				'A second app cannot be setup. '
				. 'Tried to set ' . get_class($app) . ' after setting ' . get_class(self::$app)
			);
		}

		self::$app = $app;
	}

	/**
	 * @return App
	 */
	public static function app()
	{
		if (!self::$app)
		{
			return self::setupApp('\XF\App');
		}

		return self::$app;
	}

	/**
	 * @template T of \XF\App
	 *
	 * @param class-string<T> $appClass
	 *
	 * @return T
	 */
	public static function setupApp($appClass, array $setupOptions = [])
	{
		$app = new $appClass(new Container());
		self::setApp($app);
		$app->setup($setupOptions);

		return $app;
	}

	/**
	 * Detects if the request URL matches the API path
	 *
	 * @return bool
	 */
	public static function requestUrlMatchesApi()
	{
		$baseRequest = new Request(new InputFilterer());
		return boolval(preg_match('#^api(?:/|$)#i', $baseRequest->getRoutePath()));
	}

	/**
	 * @template T of \XF\App
	 *
	 * @param class-string<T> $appClass
	 */
	public static function runApp($appClass)
	{
		$app = self::setupApp($appClass);

		ob_start();

		$response = $app->run();

		$extraOutput = ob_get_clean();
		if (strlen($extraOutput))
		{
			$body = $response->body();
			if (is_string($body))
			{
				if ($response->contentType() == 'text/html')
				{
					if (strpos($body, '<!--XF:EXTRA_OUTPUT-->') !== false)
					{
						$body = str_replace('<!--XF:EXTRA_OUTPUT-->', $extraOutput . '<!--XF:EXTRA_OUTPUT-->', $body);
					}
					else
					{
						$body = preg_replace('#<body[^>]*>#i', "\\0$extraOutput", $body);
					}
					$response->body($body);
				}
				else
				{
					$response->body($extraOutput . $body);
				}
			}
		}

		if (\XF::$debugMode)
		{
			$app = \XF::app();
			$container = $app->container();

			if ($container->isCached('db'))
			{
				$queryCount = \XF::db()->getQueryCount();
			}
			else
			{
				$queryCount = null;
			}

			$debug = [
				'time' => round(microtime(true) - $app->container('time.granular'), 4),
				'queries' => $queryCount,
				'memory' => round(memory_get_peak_usage() / 1024 / 1024, 2),
			];

			$response->header('X-XF-Debug-Stats', json_encode($debug));
		}

		$response->send($app->request());
	}

	/**
	 * @return User
	 */
	public static function visitor()
	{
		if (!self::$visitor)
		{
			/** @var UserRepository $userRepo */
			$userRepo = self::repository(UserRepository::class);
			self::$visitor = $userRepo->getVisitor(0);
		}

		return self::$visitor;
	}

	public static function setVisitor(?User $user = null)
	{
		self::$visitor = $user;
	}

	/**
	 * Temporarily take an action with the given user considered to be the visitor
	 *
	 * @param User $user
	 * @param \Closure $action
	 * @param bool $withLanguage If true, the action will be taken with the given user's language
	 *
	 * @return mixed
	 *
	 * @throws \Exception
	 */
	public static function asVisitor(User $user, \Closure $action, bool $withLanguage = false)
	{
		$oldVisitor = self::$visitor;
		self::setVisitor($user);

		if ($withLanguage)
		{
			$oldLang = self::$language;

			$newLang = self::app()->userLanguage($user);
			$newLangeOrigTz = $newLang->getTimeZone();
			$newLang->setTimeZone($user->timezone);
			self::setLanguage($newLang);
		}

		try
		{
			return $action();
		}
		finally
		{
			self::setVisitor($oldVisitor);

			if ($withLanguage)
			{
				$newLang->setTimeZone($newLangeOrigTz);
				self::$language = $oldLang;
			}
		}
	}

	/**
	 * @return User
	 */
	public static function preRegActionUser(): User
	{
		if (!self::$preRegActionUser)
		{
			/** @var UserRepository $userRepo */
			$userRepo = self::repository(UserRepository::class);
			self::$preRegActionUser = $userRepo->getPreRegActionUser();
		}

		return self::$preRegActionUser;
	}

	/**
	 * @param \Closure $action
	 *
	 * @return mixed
	 */
	public static function asPreRegActionUser(\Closure $action)
	{
		return self::asVisitor(self::preRegActionUser(), $action);
	}

	/**
	 * Helper to make conditional pre-registration user actions syntactically simpler.
	 *
	 * If the first argument as true, the closure will be run in the context of the pre-reg action user. Otherwise,
	 * it will be run in the context of the visitor.
	 *
	 * @param bool $isNeeded
	 * @param \Closure $action
	 *
	 * @return mixed
	 */
	public static function asPreRegActionUserIfNeeded(bool $isNeeded, \Closure $action)
	{
		if ($isNeeded)
		{
			return self::asPreRegActionUser($action);
		}
		else
		{
			return $action();
		}
	}

	/**
	 * Helper to make it easier to determine if a particular action can be performed by a pre-reg action user.
	 * This is specifically for things like permission checks and is expected to only return true false.
	 *
	 * @param \Closure $action
	 * @return bool
	 */
	public static function canPerformPreRegAction(\Closure $action): bool
	{
		$visitor = self::visitor();
		if (!$visitor->canTriggerPreRegAction())
		{
			return false;
		}

		return \XF::asVisitor(\XF::preRegActionUser(), $action);
	}

	/**
	 * @return Language
	 */
	public static function language()
	{
		if (!self::$language)
		{
			self::$language = self::app()->language(0);
		}

		return self::$language;
	}

	public static function setLanguage(Language $language)
	{
		self::$language = $language;
	}

	/**
	 * @return ApiKey
	 */
	public static function apiKey()
	{
		if (!self::$apiKey)
		{
			/** @var ApiRepository $apiRepo */
			$apiRepo = self::repository(ApiRepository::class);
			self::$apiKey = $apiRepo->getFallbackApiKey();
		}

		return self::$apiKey;
	}

	public static function setApiKey(?ApiKey $key = null)
	{
		self::$apiKey = $key;
	}

	public static function accessToken(): ?OAuthToken
	{
		if (!self::$accessToken)
		{
			self::$accessToken = null;
		}

		return self::$accessToken;
	}

	public static function setAccessToken(?OAuthToken $accessToken = null): void
	{
		self::$accessToken = $accessToken;
	}

	/**
	 * True if the API has been set to bypass permissions for the current request.
	 * This is only possible if a super user key is being used.
	 *
	 * @return bool
	 */
	public static function isApiBypassingPermissions()
	{
		// TODO: something for access tokens?
		return self::$apiBypassPermissions && self::apiKey()->is_super_user;
	}

	/**
	 * True in most cases, this is just the inverse of isApiBypassingPermissions(), in contexts where
	 * the inverted logic is easier to read.
	 *
	 * @return bool
	 */
	public static function isApiCheckingPermissions()
	{
		return !self::isApiBypassingPermissions();
	}

	public static function setApiBypassPermissions($bypass)
	{
		self::$apiBypassPermissions = $bypass;
	}

	/**
	 * @return bool
	 */
	public static function isPushUsable()
	{
		$options = self::options();

		if (!isset($options->enablePush) || !$options->enablePush)
		{
			return false;
		}

		$request = self::app()->request();

		if ($request->isHostLocal())
		{
			return true;
		}

		if ($request->isSecure())
		{
			return true;
		}

		return false;
	}

	public static function phrasedException($name, array $params = [])
	{
		return new PrintableException(
			self::phrase($name, $params)->render(),
			$name
		);
	}

	public static function phrase($name, array $params = [], $allowHtml = true)
	{
		return self::language()->phrase($name, $params, true, $allowHtml);
	}

	public static function phraseDeferred($name, array $params = [])
	{
		return self::language()->phrase($name, $params, false);
	}

	public static function string(array $parts = [])
	{
		return new StringBuilder($parts);
	}

	public static function config($key = null)
	{
		return self::app()->config($key);
	}

	/**
	 * @return Session
	 */
	public static function session()
	{
		return self::app()->session();
	}

	/**
	 * @return Options
	 */
	public static function options()
	{
		return self::app()->options();
	}

	/**
	 * @return Mailer
	 */
	public static function mailer()
	{
		return self::app()->mailer();
	}

	/**
	 * @return AbstractAdapter
	 */
	public static function db()
	{
		return self::app()->db();
	}

	/**
	 * @return PermissionCache
	 */
	public static function permissionCache()
	{
		return self::app()->permissionCache();
	}

	/**
	 * @return Manager
	 */
	public static function em()
	{
		return self::app()->em();
	}

	/**
	 * @template T of \XF\Mvc\Entity\Finder
	 *
	 * @param class-string<T> $identifier
	 *
	 * @return T
	 */
	public static function finder($identifier)
	{
		return self::app()->finder($identifier);
	}

	/**
	 * @template T of \XF\Mvc\Entity\Repository
	 *
	 * @param class-string<T> $identifier
	 *
	 * @return T
	 */
	public static function repository($identifier)
	{
		return self::app()->repository($identifier);
	}

	/**
	 * @template T of \XF\Service\AbstractService
	 *
	 * @param class-string<T> $class
	 * @param mixed ...$args
	 *
	 * @return T
	 */
	public static function service($class)
	{
		$args = func_get_args();
		return call_user_func_array([self::app(), 'service'], $args);
	}

	/**
	 * @template T
	 *
	 * @param class-string<T> $class
	 * @param mixed ...$args
	 *
	 * @return T
	 */
	public static function helper($class)
	{
		$args = func_get_args();
		return call_user_func_array([self::app(), 'helper'], $args);
	}

	/**
	 * @param array $columns
	 * @param array $existingValues
	 * @param bool $isUpdating
	 *
	 * @return ArrayValidator
	 */
	public static function arrayValidator(
		array $columns,
		array $existingValues = [],
		bool $isUpdating = false
	): ArrayValidator
	{
		return self::app()->arrayValidator($columns, $existingValues, $isUpdating);
	}

	/**
	 * @return DataRegistry
	 */
	public static function registry()
	{
		return self::app()->registry();
	}

	/**
	 * @return MountManager
	 */
	public static function fs()
	{
		return self::app()->fs();
	}

	/**
	 * @return Extension
	 */
	public static function extension()
	{
		return self::app()->extension();
	}

	/**
	 * Fires a code event for an extension point
	 *
	 * @param string $event
	 * @param array $args
	 * @param null|string $hint
	 *
	 * @return bool
	 */
	public static function fire($event, array $args, $hint = null)
	{
		return self::extension()->fire($event, $args, $hint);
	}

	/**
	 * Gets the callable class name for a dynamically extended class.
	 *
	 * @template TBase
	 * @template TFakeBase
	 * @template TSubclass of TBase
	 *
	 * @param class-string<TBase>          $class
	 * @param class-string<TFakeBase>|null $fakeBaseClass
	 *
	 * @return class-string<TSubclass>
	 */
	public static function extendClass($class, $fakeBaseClass = null)
	{
		return self::app()->extendClass($class, $fakeBaseClass);
	}

	/**
	 * Sets the memory limit. Will not shrink the limit.
	 *
	 * @param int $limit Limit must be given in integer (byte) format.
	 *
	 * @return bool True if the limit was updated (or already met)
	 */
	public static function setMemoryLimit($limit)
	{
		$existingLimit = self::getMemoryLimit();
		if ($existingLimit < 0)
		{
			return true;
		}

		$limit = intval($limit);
		if ($limit == -1 || ($limit > $existingLimit && $existingLimit))
		{
			if (@ini_set('memory_limit', $limit) === false)
			{
				return false;
			}

			self::$memoryLimit = $limit;
		}

		return true;
	}

	public static function increaseMemoryLimit($amount)
	{
		$amount = intval($amount);
		if ($amount <= 0)
		{
			return false;
		}

		$currentLimit = self::getMemoryLimit();
		if ($currentLimit < 0)
		{
			return true;
		}

		return self::setMemoryLimit($currentLimit + $amount);
	}

	/**
	 * Gets the current memory limit.
	 *
	 * @return int
	 */
	public static function getMemoryLimit()
	{
		if (self::$memoryLimit === null)
		{
			$curLimit = @ini_get('memory_limit');
			if ($curLimit === false)
			{
				// reading failed, so we have to treat it as unlimited - unlikely to be able to change anyway
				$curLimitInt = -1;
			}
			else
			{
				$curLimitInt = intval($curLimit);

				switch (substr($curLimit, -1))
				{
					case 'g':
					case 'G':
						$curLimitInt *= 1024;
						// no break

					case 'm':
					case 'M':
						$curLimitInt *= 1024;
						// no break

					case 'k':
					case 'K':
						$curLimitInt *= 1024;
				}
			}

			self::$memoryLimit = $curLimitInt;
		}

		return self::$memoryLimit;
	}

	/**
	 * Attempts to determine the current available amount of memory.
	 * If there is no memory limit
	 *
	 * @return int
	 */
	public static function getAvailableMemory()
	{
		$limit = self::getMemoryLimit();
		if ($limit < 0)
		{
			return PHP_INT_MAX;
		}

		$used = memory_get_usage();
		$available = $limit - $used;

		return ($available < 0 ? 0 : $available);
	}

	/**
	 * Generates a psuedo-random string of the specified length.
	 *
	 * @param int $length
	 * @param bool $raw If true, raw binary is returned, otherwise modified base64
	 *
	 * @return string
	 */
	public static function generateRandomString($length, $raw = false)
	{
		if ($raw)
		{
			return Random::getRandomBytes($length);
		}
		else
		{
			return Random::getRandomString($length);
		}
	}

	public static function stringToClass($string, $formatter, $defaultInfix = null)
	{
		if (!$string)
		{
			return '';
		}

		if (strpos($string, ':') === false)
		{
			$pattern = '#^\\\?'
				. str_replace('%s', '([A-Za-z0-9_\\\]+)', preg_quote(ltrim($formatter, '\\')))
				. '$#';
			if (!preg_match($pattern, $string, $matches))
			{
				throw new \InvalidArgumentException(sprintf(
					'Class %s does not match formatter pattern %s',
					$string,
					$formatter
				));
			}

			// already a class
			return $string;
		}

		$parts = explode(':', $string, 3);
		$prefix = $parts[0];
		if (isset($parts[2]))
		{
			$infix = $parts[1];
			$suffix = $parts[2];
		}
		else
		{
			$infix = $defaultInfix;
			$suffix = $parts[1];
		}

		return $defaultInfix === null
			? sprintf($formatter, $prefix, $suffix)
			: sprintf($formatter, $prefix, $infix, $suffix);
	}

	public static function classToString(
		string $class,
		string $formatter,
		?string $defaultInfix = null
	): string
	{
		if (strpos($class, ':') !== false)
		{
			// already a short name
			return $class;
		}

		$pattern = '#^\\\?'
			. str_replace('%s', '([A-Za-z0-9_\\\]+)', preg_quote($formatter))
			. '$#';
		if (!preg_match($pattern, $class, $matches))
		{
			throw new \InvalidArgumentException(sprintf(
				'Class %s does not match formatter pattern %s',
				$class,
				$formatter
			));
		}

		$prefix = $matches[1];
		if (isset($matches[3]))
		{
			$infix = $matches[2];
			$suffix = $matches[3];
		}
		else
		{
			$infix = $defaultInfix;
			$suffix = $matches[2];
		}

		return $infix === $defaultInfix
			? sprintf('%s:%s', $prefix, $suffix)
			: sprintf('%s:%s:%s', $prefix, $infix, $suffix);
	}

	public static function getCopyrightHtml()
	{
		return 'Community platform by XenForo<sup>&reg;</sup> <span class="copyright">&copy; 2010-2025 XenForo Ltd.</span>';
	}

	public static function getCopyrightHtmlAcp()
	{
		return 'Community platform by XenForo<sup>&reg;</sup></a>';
	}

	public static function isPreEscaped($value, $type = 'html')
	{
		if ($value instanceof PreEscaped && $value->escapeType == $type)
		{
			return true;
		}
		else if ($value instanceof PreEscapedInterface && $value->getPreEscapeType() == $type)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @param string|int|float|bool $value
	 * @param 'html'|'raw'|'js'|'json'|'htmljs'|'datauri'|bool $type
	 *
	 * @return string
	 */
	public static function escapeString($value, $type = 'html')
	{
		if ($type === false)
		{
			$type = 'raw';
		}
		else if ($type === true)
		{
			$type = 'html';
		}

		if (self::isPreEscaped($value, $type))
		{
			return strval($value);
		}
		else if ($type == 'html' && ($value instanceof Phrase || $value instanceof Template))
		{
			return strval($value);
		}
		else if (is_array($value))
		{
			trigger_error("Array passed into XF::escapeString unexpectedly.", E_USER_NOTICE);
			return 'Array';
		}

		$value = strval($value);

		switch ($type)
		{
			case 'html':
				return htmlspecialchars($value, ENT_QUOTES, 'utf-8');

			case 'raw':
				return $value;

			case 'js':
				$value = strtr($value, [
					'\\' => '\\\\',
					'"' => '\\"',
					"'" => "\\'",
					"\r" => '\r',
					"\n" => '\n',
					'</' => '<\\/',
				]);
				$value = preg_replace('/-(?=-)/', '-\\', $value);
				return $value;

			case 'json':
				$value = strtr($value, [
					'\\' => '\\\\',
					'"' => '\\"',
					"\t" => '\t',
					"\r" => '\r',
					"\n" => '\n',
					'/' => '\\/',
					'<!' => '\u003C!',
				]);
				return $value;

			case 'htmljs':
				return \XF::escapeString(\XF::escapeString($value, 'html'), 'js');

			case 'datauri':
				$value = strtr($value, [
					"\r" => '%0D',
					"\n" => '%0A',
					'%' => '%25',
					'#' => '%23',
					'(' => '%28',
					')' => '%29',
					'<' => '%3C',
					'>' => '%3E',
					'?' => '%3F',
					'[' => '%5B',
					']' => '%5D',
					'\\' => '%5C',
					'^' => '%5E',
					'`' => '%60',
					'{' => '%7B',
					'}' => '%7D',
					'|' => '%7C',
				]);
				return $value;

			default:
				return htmlspecialchars($value, ENT_QUOTES, 'utf-8');
		}
	}

	/**
	 * Renders the input to a string with a plain text use case expected.
	 *
	 * The most significant use case is if you might have a phrase object which defaults to HTML escaping its params.
	 * This function will render its parameters without escaping.
	 *
	 * @param mixed $input
	 *
	 * @return string
	 */
	public static function renderPlainString($input): string
	{
		if ($input instanceof Phrase)
		{
			return $input->render('raw');
		}

		return strval($input);
	}

	public static function cleanString($string, $trim = true)
	{
		return self::app()->inputFilterer()->cleanString($string, $trim);
	}

	public static function cleanArrayStrings(array $input, $trim = true)
	{
		return self::app()->inputFilterer()->cleanArrayStrings($input, $trim);
	}

	public static function dump($var)
	{
		self::app()->debugger()->dump($var);
	}

	public static function dumpSimple($var, $echo = true)
	{
		return self::app()->debugger()->dumpSimple($var, $echo);
	}

	public static function dumpToFile($var, $logName = null)
	{
		return self::app()->debugger()->dumpToFile($var, $logName);
	}

	public static function canonicalizeUrl($uri)
	{
		return self::convertToAbsoluteUrl($uri, self::options()->boardUrl);
	}

	public static function convertToAbsoluteUrl($uri, $fullBasePath)
	{
		$fullBasePath = rtrim($fullBasePath, '/');
		$baseParts = parse_url($fullBasePath);
		if (!$baseParts)
		{
			return $uri;
		}

		if ($uri == '.')
		{
			$uri = ''; // current directory
		}

		if (empty($baseParts['scheme']))
		{
			$baseParts['scheme'] = 'http';
		}

		if (substr(strval($uri), 0, 2) == '//')
		{
			return $baseParts['scheme'] . ':' . $uri;
		}
		else if (substr(strval($uri), 0, 1) == '/')
		{
			if (empty($baseParts['host']))
			{
				return $uri; // really can't guess
			}

			return $baseParts['scheme'] . '://' . $baseParts['host']
				. (!empty($baseParts['port']) ? ":$baseParts[port]" : '') . $uri;
		}
		else if (preg_match('#^[a-z0-9-]+://#i', strval($uri)))
		{
			return $uri;
		}
		else
		{
			return $fullBasePath . '/' . $uri;
		}
	}
}
