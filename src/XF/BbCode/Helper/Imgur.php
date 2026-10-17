<?php

namespace XF\BbCode\Helper;

use XF\Entity\BbCodeMediaSite;

use function strlen;

class Imgur
{
	public static function matchCallback($url, $matchedId, BbCodeMediaSite $site, $siteId)
	{
		if ($matchedId === 'user')
		{
			// special case user URLs - a link to a favorite belonging to a user can be embedded otherwise skip
			if (!strpos($url, 'favorites/') !== false)
			{
				return false;
			}

			if (!preg_match('#favorites/(.*)$#iUs', $url, $matches))
			{
				return false;
			}

			if (!strlen(trim($matches[1])))
			{
				return false;
			}

			return $matchedId = 'a/' . $matches[1];
		}

		if (strpos($url, 'gallery/' . $matchedId) !== false)
		{
			$lastHyphenPos = strrpos($matchedId, '-');
			if ($lastHyphenPos !== false)
			{
				$matchedId = substr($matchedId, $lastHyphenPos + 1);
			}

			return 'a/' . $matchedId;
		}

		if (strpos($url, 'a/' . $matchedId) !== false)
		{
			return 'a/' . $matchedId;
		}

		return $matchedId;
	}
}
