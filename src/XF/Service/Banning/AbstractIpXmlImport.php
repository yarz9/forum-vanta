<?php

namespace XF\Service\Banning;

use XF\Finder\IpMatchFinder;
use XF\PrintableException;
use XF\Repository\BanningRepository;
use XF\Service\AbstractXmlImport;
use XF\Util\Php;
use XF\Util\Xml;

abstract class AbstractIpXmlImport extends AbstractXmlImport
{
	abstract protected function getMethod();

	public function import(\SimpleXMLElement $xml)
	{
		$banMethod = $this->getMethod();
		$banningRepo = $this->repository(BanningRepository::class);

		if (!Php::validateCallbackPhrased($banningRepo, $banMethod, $error))
		{
			throw new PrintableException($error);
		}

		$entries = $xml->entry;

		$ips = [];
		$type = null;

		foreach ($entries AS $entry)
		{
			$ips[] = (string) $entry['ip'];
			$type = (string) $entry['match_type'];
		}

		$existing = $this->finder(IpMatchFinder::class)
			->where('ip', $ips)
			->where('match_type', $type)
			->keyedBy('ip')
			->fetch();

		foreach ($entries AS $entry)
		{
			if (isset($existing[(string) $entry['ip']]))
			{
				// already exists
				continue;
			}

			$banningRepo->$banMethod((string) $entry['ip'], Xml::processSimpleXmlCdata($entry->reason));
		}
	}
}
