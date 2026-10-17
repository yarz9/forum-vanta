<?php

namespace XF\ConnectedAccount\Service;

interface ProviderIdAwareInterface
{
	public function setProviderId(string $providerId): void;

	public function getProviderId(): string;
}
