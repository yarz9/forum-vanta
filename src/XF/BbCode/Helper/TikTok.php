<?php

namespace XF\BbCode\Helper;

use GuzzleHttp\Utils;
use XF\Entity\BbCodeMediaSite;

class TikTok
{
	public static function matchCallback($url, $matchedId, BbCodeMediaSite $site, $siteId)
	{
		if (strpos($url, 'vm.tiktok.com') !== false)
		{
			$client = \XF::app()->http()->reader();

			try
			{
				$json = Utils::jsonDecode($client->getUntrusted($site->oembed_api_endpoint . '?url=' . $url)->getBody()->getContents(), true);
				$matchedId = $json['embed_product_id'] ?? $matchedId;
			}
			catch (\Exception $e)
			{
			}
		}

		return $matchedId;
	}
}
