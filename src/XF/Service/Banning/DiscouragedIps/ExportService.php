<?php

namespace XF\Service\Banning\DiscouragedIps;

class ExportService extends \XF\Service\Banning\Ips\ExportService
{
	public function getRootName()
	{
		return 'discouraged_ips';
	}
}
