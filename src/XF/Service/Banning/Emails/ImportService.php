<?php

namespace XF\Service\Banning\Emails;

use XF\Repository\BanningRepository;
use XF\Service\AbstractXmlImport;
use XF\Util\Xml;

use function in_array;

class ImportService extends AbstractXmlImport
{
	public function import(\SimpleXMLElement $xml)
	{
		$bannedEmailsCache = (array) $this->app->container('bannedEmails');
		$bannedEmailsCache = array_map('strtolower', $bannedEmailsCache);

		$entries = $xml->entry;
		foreach ($entries AS $entry)
		{
			$email = (string) $entry['banned_email'];

			$normalizedEmail = strtolower($email);
			if (in_array($normalizedEmail, $bannedEmailsCache))
			{
				// already exists
				continue;
			}

			$this->repository(BanningRepository::class)->banEmail(
				$email,
				Xml::processSimpleXmlCdata($entry->reason)
			);
			$bannedEmailsCache[] = $normalizedEmail;
		}
	}
}
