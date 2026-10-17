<?php

namespace XF\Util;

use XF\Phrase;

use function defined, get_class, intval, is_array, is_object, is_string, strval;

class Php
{
	/**
	 * Validates a callback more strictly and with more detailed errors.
	 *
	 * @param string|object|array $class A class name, object, function name, or array containing class/object and method
	 * @param null|string $method If first param is class or object, the method name
	 * @param string $error Error key returned by reference
	 * @param bool $forceMethod If true, if no method is provided, never treat the class as a function
	 *
	 * @return bool
	 *
	 * @throws \InvalidArgumentException
	 */
	public static function validateCallback($class, $method = null, &$error = null, $forceMethod = true)
	{
		if (is_array($class))
		{
			if ($method)
			{
				throw new \InvalidArgumentException('Method cannot be provided with class as array');
			}

			$method = $class[1];
			$class = $class[0];
		}

		if ($forceMethod)
		{
			$method = strval($method);
		}
		else
		{
			if (!$method)
			{
				if (is_object($class))
				{
					throw new \InvalidArgumentException('Object given with no method');
				}

				if (!function_exists($class))
				{
					$error = 'invalid_function';
					return false;
				}
				else
				{
					return true;
				}
			}
		}

		if (!is_string($method))
		{
			throw new \InvalidArgumentException('Method to check is not a string');
		}

		if (!is_object($class))
		{
			if (!$class || preg_match('[\\\\\\\\]', $class) || !class_exists($class))
			{
				$error = 'invalid_class';
				return false;
			}
		}

		$reflectionClass = new \ReflectionClass($class);
		$isObject = is_object($class);

		if (
			($isObject && $reflectionClass->hasMethod('__call'))
			|| (!$isObject && $reflectionClass->hasMethod('__callStatic'))
		)
		{
			// magic method will always be called if a method can't be
			return true;
		}

		if (!$method || !$reflectionClass->hasMethod($method))
		{
			$error = 'invalid_method';
			return false;
		}

		$reflectionMethod = $reflectionClass->getMethod($method);

		if ($reflectionMethod->isAbstract() || !$reflectionMethod->isPublic())
		{
			$error = 'invalid_method_configuration';
			return false;
		}

		$isStatic = $reflectionMethod->isStatic();

		if ($isStatic && $isObject)
		{
			$error = 'method_static';
			return false;
		}
		else if (!$isStatic && !$isObject)
		{
			$error = 'method_not_static';
			return false;
		}

		return true;
	}

	/**
	 * Does a detailed validation of a callback and returns the error
	 * in a ready to print phrase
	 *
	 * @param string|object|array $class A class name, object, function name, or array containing class/object and method
	 * @param null|string $method If first param is class or object, the method name
	 * @param null|Phrase $errorPhrase If an error occurs, outputs the phrase
	 * @param bool $forceMethod If true, if no method is provided, never treat the class as a function
	 *
	 * @return bool
	 */
	public static function validateCallbackPhrased($class, $method = null, &$errorPhrase = null, $forceMethod = true)
	{
		$success = static::validateCallback($class, $method, $error, $forceMethod);
		if ($success)
		{
			return true;
		}

		$printableCallback = static::getPrintableCallback($class, $method);
		$innerErrorPhrase = \XF::phrase('error_' . $error);

		$errorPhrase = \XF::phrase('callback_x_invalid_y', [
			'callback' => $printableCallback,
			'error' => $innerErrorPhrase,
		]);

		return false;
	}

	/**
	 * Returns a callback in a simple printable form
	 *
	 * @param string|object|array $class A class name, object, function name, or array containing class/object and method
	 * @param null|string $method If first param is class or object, the method name
	 *
	 * @return string
	 *
	 * @throws \InvalidArgumentException
	 */
	public static function getPrintableCallback($class, $method = null)
	{
		if (is_array($class))
		{
			if ($method)
			{
				throw new \InvalidArgumentException('Method cannot be provided with class as array');
			}

			$method = $class[1];
			$class = $class[0];
		}

		if (!$method)
		{
			if (is_object($class))
			{
				throw new \InvalidArgumentException('Object given with no method');
			}

			return strval($class);
		}

		if (!is_string($method))
		{
			throw new \InvalidArgumentException('Method must be a string when given an object');
		}

		if (is_object($class))
		{
			return get_class($class) . '->' . $method;
		}
		else
		{
			return $class . '::' . $method;
		}
	}

	public static function nameIndicatesReadOnly($name)
	{
		$matches = [
			'first',
			'last',
		];
		if (preg_match('/^(' . implode('|', $matches) . ')$/i', $name))
		{
			return true;
		}

		$prefixes = [
			'are',
			'can',
			'count',
			'data',
			'display',
			'does',
			'exists',
			'fetch',
			'filter',
			'find',
			'get',
			'has',
			'is',
			'pluck',
			'print',
			'render',
			'return',
			'show',
			'total',
			'validate',
			'verify',
			'view',
		];
		if (preg_match('/^(' . implode('|', $prefixes) . ')/i', $name))
		{
			return true;
		}

		return false;
	}

	/**
	 * Unserializes a string, avoiding unserializing potentially dangerous objects.
	 *
	 * If an object is present, unserialization will fail and false will be returned.
	 *
	 * See serializedContainsObject for comments on false positives.
	 *
	 * @param string $serialized
	 *
	 * @return bool|mixed
	 */
	public static function safeUnserialize($serialized)
	{
		if (static::serializedContainsObject($serialized))
		{
			return false;
		}

		return @unserialize($serialized, ['allowed_classes' => false]);
	}

	/**
	 * Serializes a string only if it doesn't contain object constructs. This can be paired with safeUnserialize
	 * to block the serialization if unserialization will fail anyway. (Serialization itself is safe, but if it's going
	 * to fail to unserialize, it likely shouldn't be allowed.)
	 *
	 * See serializedContainsObject for comments on false positives.
	 *
	 * @param string $toSerialize
	 *
	 * @return string
	 */
	public static function safeSerialize($toSerialize)
	{
		$serialized = serialize($toSerialize);

		if (static::serializedContainsObject($serialized))
		{
			throw new \InvalidArgumentException("Serialized value contains an object and this is not allowed");
		}

		return $serialized;
	}

	/**
	 * This detects if a serialized string may contain an object definition.
	 * This can trigger a false positive if a string matches the format but it should be unlikely.
	 *
	 * This function could be implemented with a single, one-line regex but it has been optimized, particularly
	 * for the case that no object or object-like construct is present.
	 *
	 * @param string $serialized
	 *
	 * @return bool
	 */
	public static function serializedContainsObject($serialized)
	{
		if (strpos($serialized, 'O:') !== false && preg_match('#(?<=^|[;{}])O:[+-]?[0-9]+:"#', $serialized))
		{
			return true;
		}

		if (strpos($serialized, 'C:') !== false && preg_match('#(?<=^|[;{}])C:[+-]?[0-9]+:"#', $serialized))
		{
			return true;
		}

		if (strpos($serialized, 'o:') !== false && preg_match('#(?<=^|[;{}])o:[+-]?[0-9]+:"#', $serialized))
		{
			return true;
		}

		return false;
	}

	public static function kebabCase($string)
	{
		return str_replace(' ', '-', Str::strtolower($string));
	}

	public static function camelCase($string, $glue = '_')
	{
		return str_replace(' ', '', Str::ucwords(str_replace($glue, ' ', $string)));
	}

	public static function fromCamelCase($string, $glue = '_')
	{
		return preg_replace_callback('/([a-z])([A-Z])/', function ($match) use ($glue)
		{
			return $match[1] . $glue . Str::strtolower($match[2]);

		}, $string);
	}

	public static function isValidRegex($regex, $addDelimiter = null)
	{
		if ($addDelimiter !== null)
		{
			$regex = $addDelimiter . $regex . $addDelimiter;
		}

		set_error_handler(function () {}, E_WARNING);
		$isValidRegex = preg_match($regex, '') !== false;
		restore_error_handler();

		return $isValidRegex;
	}

	public static function invalidateOpcodeCache($file)
	{
		if (!file_exists($file) && substr($file, -4) == '.php')
		{
			return;
		}

		if (function_exists('opcache_invalidate'))
		{
			try
			{
				@opcache_invalidate($file);
			}
			catch (\Exception $e)
			{
			}
		}

		if (function_exists('apc_delete_file'))
		{
			try
			{
				@apc_delete_file($file);
			}
			catch (\Exception $e)
			{
			}
		}

		if (function_exists('accelerator_reset'))
		{
			// Zend Optimizer (probably shouldn't be used much, but no harm)
			try
			{
				@accelerator_reset();
			}
			catch (\Exception $e)
			{
			}
		}
	}

	public static function resetOpcache()
	{
		if (function_exists('opcache_reset'))
		{
			try
			{
				@opcache_reset();
			}
			catch (\Exception $e)
			{
			}
		}

		if (function_exists('apc_clear_cache'))
		{
			try
			{
				@apc_clear_cache();
			}
			catch (\Exception $e)
			{
			}
		}

		if (function_exists('accelerator_reset'))
		{
			// Zend Optimizer (probably shouldn't be used much, but no harm)
			try
			{
				@accelerator_reset();
			}
			catch (\Exception $e)
			{
			}
		}
	}

	public static function getUploadMaxFilesize()
	{
		return min(
			static::getBytesFromPhpConfigValue('upload_max_filesize'),
			static::getBytesFromPhpConfigValue('post_max_size')
		);
	}

	protected static function getBytesFromPhpConfigValue($configParam)
	{
		$configValue = ini_get($configParam);

		preg_match('/^(?P<value>\d+)(?P<units>k|kb|m|mb|g|gb)?$/i', $configValue, $matches);

		$value = isset($matches['value']) ? intval($matches['value']) : 0;
		$units = isset($matches['units']) ? strtoupper($matches['units']) : '';

		// note that KB, MB and GB are not actually valid in PHP config, but are frequently encountered

		switch ($units)
		{
			case 'K':
			case 'KB':
				return $value * 1024;
			case 'G':
			case 'GB':
				return $value * 1073741824;
			case 'MB':
			case 'M':
			default:
				return $value * 1048576;
		}
	}

	public static function convertErrorCodeToString($code)
	{
		switch ($code)
		{
			case E_ERROR: return 'E_ERROR';
			case E_WARNING: return 'E_WARNING';
			case E_PARSE: return 'E_PARSE';
			case E_NOTICE: return 'E_NOTICE';
			case E_CORE_ERROR: return 'E_CORE_ERROR';
			case E_CORE_WARNING: return 'E_CORE_WARNING';
			case E_COMPILE_ERROR: return 'E_COMPILE_ERROR';
			case E_COMPILE_WARNING: return 'E_COMPILE_WARNING';
			case E_USER_ERROR: return 'E_USER_ERROR';
			case E_USER_WARNING: return 'E_USER_WARNING';
			case E_USER_NOTICE: return 'E_USER_NOTICE';
			case E_RECOVERABLE_ERROR: return 'E_RECOVERABLE_ERROR';
			case E_DEPRECATED: return 'E_DEPRECATED';
			case E_USER_DEPRECATED: return 'E_USER_DEPRECATED';

			// deprecated in PHP 8.4, should be evaluated last
			case E_STRICT: return 'E_STRICT';

			default: return "$code";
		}
	}

	public static function getEnvironmentReport()
	{
		$env = [
			'curl_version' => function_exists('curl_version')
				? curl_version()['version'] : false,
			'ssl_version' => function_exists('curl_version')
				? curl_version()['ssl_version'] : false,
			'openssl_version' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : null,
			'snuffleupagus' => extension_loaded('snuffleupagus'),
			'imagick' => class_exists('Imagick'),
			'exif' => function_exists('exif_read_data'),
			'gzip' => function_exists('gzopen'),
			'gmp' => function_exists('gmp_init'),
			'intl' => extension_loaded('intl'),
			'zip' => class_exists('ZipArchive'),
		];

		if (isset($_SERVER['SERVER_SOFTWARE']) && preg_match('/^(.+\/[\d\.]+)/', $_SERVER['SERVER_SOFTWARE'], $match))
		{
			$env['server_software'] = $match[1];
		}
		else
		{
			$env['server_software'] = false;
		}

		$env['phpVersion'] = phpversion();
		$env['phpVersionRecommended'] = '8.3.0';

		if (version_compare($env['phpVersion'], $env['phpVersionRecommended'], '>='))
		{
			$env['phpVersionState'] = 'recommended';
		}
		else
		{
			$env['phpVersionState'] = 'not_newest';
		}

		$db = \XF::db();
		$env['mysqlVersion'] = $db->getServerVersion();

		try
		{
			$mysqlVersionVar = $db->fetchOne('
				SELECT @@version
			');
			if ($env['mysqlVersion'] != $mysqlVersionVar)
			{
				$env['mysqlVersion'] = "$env[mysqlVersion] ($mysqlVersionVar)";
			}
		}
		catch (\XF\Db\Exception $e)
		{
		}

		$iniVars = [
			'memory_limit',
			'post_max_size',
			'upload_max_filesize',
			'max_input_vars',
			'max_execution_time',
		];

		$env['ini'] = [];
		foreach ($iniVars AS $iniVar)
		{
			$env['ini'][$iniVar] = ini_get($iniVar);
		}

		return $env;
	}

	public static function getCompatibleMySqlVersion(
		string $serverVersion,
		string $serverInfo
	): string
	{
		if (!preg_match('/^\d+\.\d+/', $serverVersion, $matches))
		{
			throw new \InvalidArgumentException(
				'Invalid MySQL version provided'
			);
		}

		$version = $matches[0];

		$isMariaDb = strpos($serverInfo, 'MariaDB') !== false;
		if (!$isMariaDb)
		{
			return $version;
		}

		if (version_compare($version, '5.5', '<'))
		{
			return '5.1';
		}

		switch ($version)
		{
			case '5.5':
				return '5.5';

			case '10.0':
			case '10.1':
				return '5.6';

			case '10.2':
			case '10.3':
			case '10.4':
				return '5.7';
		}

		return '8.0';
	}
}
