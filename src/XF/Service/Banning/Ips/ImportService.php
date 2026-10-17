<?php

namespace XF\Service\Banning\Ips;

use XF\Service\Banning\AbstractIpXmlImport;

class ImportService extends AbstractIpXmlImport
{
	protected function getMethod()
	{
		return 'banIp';
	}
}
