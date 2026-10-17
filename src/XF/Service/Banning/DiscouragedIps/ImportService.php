<?php

namespace XF\Service\Banning\DiscouragedIps;

use XF\Service\Banning\AbstractIpXmlImport;

class ImportService extends AbstractIpXmlImport
{
	protected function getMethod()
	{
		return 'discourageIp';
	}
}
