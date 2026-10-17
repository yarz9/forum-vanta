<?php

namespace XF\BbCode\Helper;

use XF\Entity\BbCodeMediaSite;

class XenForo
{
	public const MATCH_REGEX = '#^(?<route>.*?)\/[^\/]*\.(?<id>\d+).*$#i';

	public static function matchCallback($url, $matchedId, BbCodeMediaSite $site, $siteId)
	{
		if (preg_match(self::MATCH_REGEX, $matchedId, $parts))
		{
			return $parts['route'] . '/' . $parts['id'];
		}

		return false;
	}
}
