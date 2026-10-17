<?php

namespace XF\ConnectedAccount\Service;

trait ProviderIdAware
{
	/**
	 * @var string
	 */
	protected $providerId;

	public function setProviderId(string $providerId): void
	{
		$this->providerId = $providerId;
	}

	public function getProviderId(): string
	{
		return $this->providerId;
	}
}
