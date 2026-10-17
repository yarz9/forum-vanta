<?php

namespace XF\Service\Upgrade;

use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Utils;
use XF\Entity\UpgradeCheck;
use XF\Finder\AddOnFinder;
use XF\Repository\SessionActivityRepository;
use XF\Service\AbstractService;

class CheckerService extends AbstractService
{
	protected $apiKey;

	protected $stableOnly;

	protected $boardUrl;

	protected $usingBranding;

	protected $addOnVersions = [];

	protected $stats = '';

	protected function setup()
	{
		$options = $this->app->options();

		$this->apiKey = \XF::XF_LICENSE_KEY;
		$this->boardUrl = $options->boardUrl;
		$this->stableOnly = $options->upgradeCheckStableOnly;
		$this->usingBranding = trim(\XF::getCopyrightHtml()) ? true : false;
		$this->addOnVersions = $this->getAddOnVersions();
		$this->stats = $this->getStats();
	}

	protected function getAddOnVersions()
	{
		$addOns = $this->app->finder(AddOnFinder::class)->fetch()
			->pluckNamed('version_id', 'addon_id');
		$addOns['XF'] = \XF::$versionId; // trust the file version more than the DB version

		// only pass XF add-ons
		return array_filter($addOns, function ($key)
		{
			return (strpos($key, 'XF') === 0);
		}, ARRAY_FILTER_USE_KEY);
	}

	protected function getStats()
	{
		return [
			'online' => $this->repository(SessionActivityRepository::class)->getOnlineCounts()['total'],
		] + array_filter($this->app->forumStatistics, function ($key)
		{
			return $key != 'latestUser';
		}, ARRAY_FILTER_USE_KEY);
	}

	public function setStableOnly($stable)
	{
		$this->stableOnly = $stable;
	}

	public function setApiKey($key)
	{
		$this->apiKey = $key;
	}

	public function check(&$detailedError = null)
	{
		$client = $this->app->http()->client();
		$errorMessage = null;
		$errorCode = null;
		$checkData = [];

		try
		{
			$response = $client->post(\XF::XF_API_URL . 'upgrade-check.json', [
				'http_errors' => false,
				'headers' => [
					'XF-LICENSE-API-KEY' => $this->apiKey,
				],
				'form_params' => [
					'board_url' => $this->boardUrl,
					'addons' => $this->addOnVersions,
					'stats' => $this->stats,
					'using_branding' => $this->usingBranding ? 1 : 0,
					'stable_only' => $this->stableOnly ? 1 : 0,
				],
			]);

			$contents = $response->getBody()->getContents();

			try
			{
				$responseJson = Utils::jsonDecode($contents, true);
			}
			catch (\InvalidArgumentException $e)
			{
				$responseJson = null;
			}

			if (isset($responseJson['error']))
			{
				$errorCode = $responseJson['error'];

				if (isset($responseJson['error_message']))
				{
					$errorMessage = $responseJson['error_message'];
				}
				else
				{
					$errorMessage = 'An unexpected error occurred.';
				}
			}
			else if ($response->getStatusCode() === 200 && isset($responseJson['boardUrlValid']))
			{
				$checkData = [
					'board_url_valid' => $responseJson['boardUrlValid'],
					'branding_valid' => $responseJson['brandingValid'],
					'license_expired' => $responseJson['licenseExpired'],
					'last_agreement_date' => $responseJson['lastAgreementDate'],
					'last_agreement_update' => $responseJson['lastAgreementUpdate'],
					'invalid_add_ons' => $responseJson['invalidAddOns'],
					'installable_add_ons' => $responseJson['installableAddOns'],
					'available_updates' => $responseJson['availableUpdates'],
					'response_data' => $responseJson,
				];
			}
			else
			{
				if (!isset($e))
				{
					$e = new \Exception('');
				}
				$this->logCheckFailure($e, "Unexpected result, starting '" . substr($contents, 0, 100) . "' // ");
				return null;
			}
		}
		catch (TransferException $e)
		{
			$this->logCheckFailure($e);
			return null;
		}

		if ($errorCode)
		{
			\XF::logError('XenForo upgrade check failed: ' . $errorMessage);
		}

		try
		{
			$upgradeCheck = $this->app->em()->create(UpgradeCheck::class);
			$upgradeCheck->bulkSet($checkData);
			$upgradeCheck->error_code = $errorCode ?: null;
			$upgradeCheck->save();
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, "Error saving upgrade check result:");
			return null;
		}

		return $upgradeCheck;
	}

	protected function logCheckFailure(\Exception $e, $extraMessage = '')
	{
		\XF::logException($e, false, "XenForo upgrade check failed: $extraMessage ");
	}
}
