<?php

namespace XF\BbCode\Helper;

use XF\Entity\BbCodeMediaSite;

class Misc
{
	protected static $ampersandFind = '&';
	protected static $ampersandReplace = ':';

	public static function matchEncodeAmpersands($url, $matchedId, BbCodeMediaSite $site, $siteId)
	{
		return str_replace(self::$ampersandFind, self::$ampersandReplace, $matchedId);
	}

	public static function embedDecodeAmpersands($mediaKey, array $site, $siteId)
	{
		return \XF::app()->templater()->renderTemplate('public:_media_site_embed_' . $siteId, [
			'id' => str_replace(self::$ampersandReplace, self::$ampersandFind, rawurldecode($mediaKey)),
			'site' => $site,
		]);
	}
}
