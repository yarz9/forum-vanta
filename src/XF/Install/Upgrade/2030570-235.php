<?php

namespace XF\Install\Upgrade;

use XF\Db\Schema\Alter;
use XF\Finder\PaymentProviderLogFinder;

class Version2030570 extends AbstractUpgrade
{
	public function getVersionName(): string
	{
		return '2.3.5';
	}

	public function step1(): void
	{
		$this->executeUpgradeQuery("
			UPDATE xf_connected_account_provider
			SET provider_id = 'x'
			WHERE provider_id = 'twitter'
		");
	}

	public function step2(): void
	{
		$this->executeUpgradeQuery("
			UPDATE xf_user_connected_account
			SET provider = 'x'
			WHERE provider = 'twitter'
		");
	}

	public function step3(): void
	{
		$this->schemaManager()->alterTable('xf_oauth_client', function (Alter $table)
		{
			$table->changeColumn('client_id')->primaryKey();
		});
	}

	public function step4(): void
	{
		$this->schemaManager()->alterTable('xf_oauth_request', function (Alter $table)
		{
			$table->changeColumn('oauth_request_id')->primaryKey();
		});
	}

	public function step5(): void
	{
		$this->schemaManager()->alterTable('xf_passkey', function (Alter $table)
		{
			$table->changeColumn('credential_id')->type('varbinary', 1024);
		});
	}

	/**
	 * @param array{} $stepData
	 *
	 * @return array{int, int, array{}}|bool
	 */
	public function step6(int $position, array $stepData)
	{
		$db = $this->db();

		$batch = 500;
		$offset = $position * $batch;
		$requests = $db->fetchAllKeyed(
			$db->limit(
				'SELECT oauth_request.*, oauth_client.allowed_scopes
					FROM xf_oauth_request AS oauth_request
					LEFT JOIN xf_oauth_client AS oauth_client
						ON (oauth_client.client_id = oauth_request.client_id)
					ORDER BY oauth_request.oauth_request_id ASC',
				$batch,
				$offset
			),
			'oauth_request_id'
		);
		if (!$requests)
		{
			return true;
		}

		$next = $position + 1;

		foreach ($requests AS $requestId => $request)
		{
			$scopes = json_decode($request['scopes'], true);
			$allowedScopes = json_decode(
				$request['allowed_scopes'] ?? '[]',
				true
			);
			$validScopes = array_intersect_key(
				$scopes,
				array_flip($allowedScopes)
			);
			if (!array_diff_key($scopes, $validScopes))
			{
				continue;
			}

			$validScopes = json_encode($validScopes);
			$db->update(
				'xf_oauth_request',
				['scopes' => $validScopes],
				'oauth_request_id = ?',
				[$requestId]
			);
		}

		return [$next, $next * $batch, $stepData];
	}

	/**
	 * @param array{max?: int} $stepData
	 *
	 * @return array{int, string, array{max?: int}}|bool
	 */
	public function step7(int $position, array $stepData)
	{
		$db = $this->db();

		if (!isset($stepData['max']))
		{
			$stepData['max'] = (int) $this->db()->fetchOne(
				'SELECT MAX(token_id) FROM xf_oauth_token'
			);
		}

		$tokens = $db->fetchAllKeyed(
			$db->limit(
				'SELECT oauth_token.*, oauth_client.allowed_scopes
					FROM xf_oauth_token AS oauth_token
					LEFT JOIN xf_oauth_client AS oauth_client
						ON (oauth_client.client_id = oauth_token.client_id)
					WHERE oauth_token.token_id > ?
					ORDER BY oauth_token.token_id ASC',
				500
			),
			'token_id',
			[$position]
		);
		if (!$tokens)
		{
			return true;
		}

		$next = 0;

		foreach ($tokens AS $tokenId => $token)
		{
			$next = $tokenId;

			$scopes = json_decode($token['scopes'], true);
			$allowedScopes = json_decode(
				$token['allowed_scopes'] ?? '[]',
				true
			);
			$validScopes = array_intersect_key(
				$scopes,
				array_flip($allowedScopes)
			);
			if (!array_diff_key($scopes, $validScopes))
			{
				continue;
			}

			$validScopes = json_encode($validScopes);
			$db->update(
				'xf_oauth_token',
				['scopes' => $validScopes],
				'token_id = ?',
				[$tokenId]
			);
		}

		return [$next, "{$next} / {$stepData['max']}", $stepData];
	}

	/**
	 * @param array{max?: int} $stepData
	 *
	 * @return array{int, string, array{max?: int}}|bool
	 */
	public function step8(int $position, array $stepData)
	{
		$db = $this->db();

		if (!isset($stepData['max']))
		{
			$this->alterTable('xf_purchase_request', function (Alter $table)
			{
				$table->changeColumn('provider_metadata', 'varbinary', 500);
			});

			$stepData['max'] = (int) $this->db()->fetchOne(
				'SELECT MAX(purchase_request_id) FROM xf_purchase_request WHERE provider_id = \'stripe\''
			);
		}

		$purchaseRequests = $db->fetchAllKeyed(
			$db->limit(
				'SELECT *
					FROM xf_purchase_request
					WHERE purchase_request_id > ?
					AND provider_id = \'stripe\'
					ORDER BY purchase_request_id ASC',
				500
			),
			'purchase_request_id',
			[$position]
		);
		if (!$purchaseRequests)
		{
			return true;
		}

		$next = 0;

		foreach ($purchaseRequests AS $purchaseRequestId => $purchaseRequest)
		{
			$next = $purchaseRequestId;

			$existingProviderMetadata = json_decode($purchaseRequest['provider_metadata'], true);
			$providerMetadata = [];

			if ($existingProviderMetadata === null)
			{
				$existingProviderMetadata = $purchaseRequest['provider_metadata'];

				$prefix = substr($existingProviderMetadata, 0, strpos($existingProviderMetadata, '_') + 1);
				switch ($prefix)
				{
					case 'pi_':
						$providerMetadata['payment_intent'] = $existingProviderMetadata;
						break;

					case 'sub_':
						$providerMetadata['subscription'] = $existingProviderMetadata;
						break;

					case 'ch_':
						$providerMetadata['charge'] = $existingProviderMetadata;
						break;
				}

				$this->expandProviderMetadata($purchaseRequest['request_key'], $providerMetadata);

				ksort($providerMetadata);
				$providerMetadata = json_encode($providerMetadata);

				$db->update('xf_purchase_request', ['provider_metadata' => $providerMetadata], 'purchase_request_id = ?', $purchaseRequestId);
			}
			// skip if already JSON
		}

		return [$next, "{$next} / {$stepData['max']}", $stepData];
	}

	protected function expandProviderMetadata(string $purchaseRequestKey, array &$providerMetadata)
	{
		$paymentIntent = $providerMetadata['payment_intent'] ?? null;
		$subscription = $providerMetadata['subscription'] ?? null;
		$charge = $providerMetadata['charge'] ?? null;

		$logs = \XF::finder(PaymentProviderLogFinder::class)
			->where('provider_id', 'stripe')
			->where('purchase_request_key', $purchaseRequestKey)
			->order('log_date', 'desc')
			->fetch();

		foreach ($logs AS $log)
		{
			$details = $log->log_details;

			if ($paymentIntent === null)
			{
				if (!empty($details['payment_intent']))
				{
					$providerMetadata['payment_intent'] = $details['payment_intent'];
				}
			}
			if ($subscription === null)
			{
				if (!empty($details['subscription']))
				{
					$providerMetadata['subscription'] = $details['subscription'];
				}
			}
			if ($charge === null)
			{
				if (isset($details['object']) && $details['object'] === 'charge')
				{
					$providerMetadata['charge'] = $details['id'];
				}
				else if (!empty($details['charge']))
				{
					if (is_string($details['charge']) && strpos($details['charge'], 'ch_') === 0)
					{
						$providerMetadata['charge'] = $details['charge'];
					}
				}
			}
		}
	}
}
