<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ApiScope> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ApiScope> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ApiScope|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ApiScope>
 */
class ApiScopeFinder extends Finder
{
	public function usableForOAuth(bool $oAuthEnabled = true): Finder
	{
		return $this->where('usable_with_oauth_clients', $oAuthEnabled);
	}
}
