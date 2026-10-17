<?php

namespace XF\BbCode\Helper;

use XF\Entity\BbCodeMediaSite;

class Reddit
{
	public const POST_URL_REGEX = '#reddit\.com/r/(?P<group>\w+)/comments/(?P<postid>[A-Za-z0-9]+)(/(?P<title_slug>[\w\-]+))?/?#i';
	public const COMMENT_URL_REGEX = '#reddit\.com/r/(?P<group>\w+)/comments/(?P<postid>[A-Za-z0-9]+)(/[\w\-]+)?/comment/(?P<commentid>[A-Za-z0-9]+)#i';

	public static function matchCallback($url, $matchedId, BbCodeMediaSite $site, $siteId)
	{
		if (preg_match(self::COMMENT_URL_REGEX, $url, $mediaInfo))
		{
			$matchedId = $mediaInfo['group'] . '/comments/' . $mediaInfo['postid'];
			if (!empty($mediaInfo['commentid']))
			{
				$matchedId .= '/comment/' . $mediaInfo['commentid'];
			}
		}
		else if (preg_match(self::POST_URL_REGEX, $url, $mediaInfo))
		{
			$matchedId = $mediaInfo['group'] . '/comments/' . $mediaInfo['postid'];
			if (!empty($mediaInfo['title_slug']))
			{
				$matchedId .= '/' . $mediaInfo['title_slug'];
			}
		}

		return $matchedId;
	}

	public static function htmlCallback($mediaKey, array $site, $siteId)
	{
		$idUrl = str_replace('%2F', '/', rawurlencode($mediaKey));

		return \XF::app()->templater()->renderTemplate('public:_media_site_embed_oembed', [
			'provider' => $siteId,
			'id' => $mediaKey,
			'site' => $site,
			'jsState' => 'reddit',
			'url' => str_replace('{$id}', $idUrl, $site['oembed_url_scheme']),
		]);
	}
}
