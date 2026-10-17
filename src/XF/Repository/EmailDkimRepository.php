<?php

namespace XF\Repository;

use XF\Mvc\Entity\Repository;

class EmailDkimRepository extends Repository
{
	public function getDnsRecordName(): string
	{
		return 'xenforo._domainkey';
	}

	public function getDnsRecordValueFromPrivateKey(): string
	{
		$publicKey = $this->getPublicKeyFromPrivateKey();

		return 'v=DKIM1; k=rsa; h=sha256; t=s; p=' . $publicKey;
	}

	public function verifyDnsRecordForDomain(string $domain): bool
	{
		$dnsRecordName = $this->getDnsRecordName();
		$dnsRecord = dns_get_record("$dnsRecordName.$domain", DNS_TXT);

		if (empty($dnsRecord))
		{
			return false;
		}

		$dnsRecord = reset($dnsRecord);

		$correctRecordValue = $this->getDnsRecordValueFromPrivateKey();

		return $correctRecordValue === $dnsRecord['txt'];
	}

	public function generateAndSaveNewKey(): bool
	{
		$key = openssl_pkey_new([
			'digest_alg' => 'sha256',
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);

		if (!$key)
		{
			throw new \Exception('Email DKIM: Could not generate keypair');
		}

		openssl_pkey_export($key, $privateKey);

		if (PHP_MAJOR_VERSION < 8)
		{
			openssl_pkey_free($key);
		}

		$registry = $this->app()->registry();
		$registry->set('emailDkimKey', $privateKey);

		return true;
	}

	protected function getPublicKeyFromPrivateKey(): string
	{
		$registry = \XF::registry();
		$emailDkimKey = $registry->get('emailDkimKey');

		if (!$emailDkimKey)
		{
			throw new \RuntimeException('Email DKIM: Key not found in registry');
		}

		$key = openssl_pkey_get_private($emailDkimKey);
		if (!$key)
		{
			throw new \Exception('Email DKIM: Unable to get private key from specified key');
		}

		$keyDetails = openssl_pkey_get_details($key);
		if (!$keyDetails)
		{
			throw new \Exception('Email DKIM: Could not get key details from key resource');
		}

		$publicKey = $keyDetails['key'];
		$publicKey = preg_replace('/^-+.*?-+$/m', '', $publicKey);
		$publicKey = str_replace(["\r", "\n"], '', $publicKey);

		return $publicKey;
	}
}
