<?php

namespace XF\BbCode\Helper;

use XF\Entity\BbCodeMediaSite;

class Spotify
{
	public const URL_REGEX = '#open\.spotify\.com/(?P<type>track|album|artist|episode|playlist|user/\w+/playlist)/(?P<id>[A-Z0-9]+)#si';

	public static function matchCallback($url, $matchedId, BbCodeMediaSite $site, $siteId)
	{
		if (preg_match(self::URL_REGEX, $url, $media))
		{
			return str_replace('/', ':', $media['type']) . ':' . $media['id'];
		}

		return false;
	}
}
