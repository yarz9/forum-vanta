<?php

namespace XF\IndexNow;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

use function in_array;

class Api
{
	/**
	 * @var Client
	 */
	protected $client;

	protected $baseApiUrl = 'https://www.bing.com/indexnow';

	protected $key;
	protected $keyLocation;

	public function __construct()
	{
		$this->key = self::getKeyFileContents();
		$this->keyLocation = self::getKeyFileLocation();

		$this->client = \XF::app()->http()->createClient([
			'http_errors' => false,
		]);
	}

	public function index(string $contentUrl): bool
	{
		$this->request('/', [
			'url' => $contentUrl,
		], $error);

		if ($error)
		{
			return false;
		}

		return true;
	}

	protected function request(string $path, array $params = [], ?string &$error = null): bool
	{
		if (!$this->key)
		{
			$error = 'IndexNow API key not set.';
			return false;
		}

		$params = array_merge([
			'key' => $this->key,
			'keyLocation' => $this->keyLocation,
		], $params);

		$path .= '?' . http_build_query($params);

		$encodedVersion = urlencode(\XF::$version);
		$headers = [
			'X-Source-Info' => "https://xenforo.com/{$encodedVersion}/false",
		];

		try
		{
			$response = $this->client->get($this->baseApiUrl . $path, [
				'headers' => $headers,
			]);
			$statusCode = $response->getStatusCode();

			if (in_array($statusCode, [200, 202]))
			{
				$result = true;
			}
			else
			{
				$result = false;

				$body = $response->getBody();
				$contents = @json_decode($body->getContents(), true);
				$message = $contents['message'] ?? \XF::phrase('unexpected_error_occurred');

				$error = 'IndexNow error: [' . $statusCode . '] ' . $message;
				\XF::logError($error);
			}
		}
		catch (TransferException $e)
		{
			\XF::logException($e, false, 'IndexNow connection error: ');
			$result = false;

			$error = $e->getMessage();
		}

		return $result;
	}

	public static function getKeyFileLocation(): string
	{
		$keyFile = self::getKeyFileName();

		return \XF::app()->options()->boardUrl . "/{$keyFile}";
	}

	public static function getKeyFileName(): string
	{
		return 'indexNow-' . \XF::options()->indexNow['key'] . '.txt';
	}

	public static function getKeyFileContents(): string
	{
		return \XF::options()->indexNow['key'];
	}
}
