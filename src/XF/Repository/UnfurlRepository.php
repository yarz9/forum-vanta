<?php

namespace XF\Repository;

use XF\Entity\UnfurlResult;
use XF\Finder\UnfurlResultFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;

use function strlen;

class UnfurlRepository extends Repository
{
	public function logPendingUnfurl($url)
	{
		if (!$url || !preg_match('#^https?://#i', $url))
		{
			throw new \InvalidArgumentException('Invalid URL');
		}

		if (strlen($url) > 2500)
		{
			return false;
		}

		$this->db()->beginTransaction();

		$affected = $this->db()->insert('xf_unfurl_result', [
			'url' => $url,
			'url_hash' => md5($url),
			'pending' => 1,
		], false, '
			result_id = LAST_INSERT_ID(result_id),
			pending = VALUES(pending)
		');
		if ($affected == 1)
		{
			$id = $this->db()->lastInsertId();
			$result = $this->em->find(UnfurlResult::class, $id);
		}
		else
		{
			$result = $this->getUnfurlResultByUrl($url);
		}

		$this->db()->commit();

		return $result;
	}

	public function logError(UnfurlResult $result)
	{
		$result->last_request_date = \XF::$time;
		$result->pending = false;
		$result->error_count = $result->error_count + 1;
		$result->save();
	}

	/**
	 * @param string $url
	 *
	 * @return null|Entity|UnfurlResult
	 */
	public function getUnfurlResultByUrl($url)
	{
		return $this->finder(UnfurlResultFinder::class)
			->where('url_hash', md5($url))
			->fetchOne();
	}

	public function addUnfurlsToContent($content, $skipRecrawl, $metadataKey = 'embed_metadata', $getterKey = 'Unfurls')
	{
		if (!$content)
		{
			return;
		}

		$unfurlIds = [];
		foreach ($content AS $item)
		{
			$metadata = $item->{$metadataKey};
			if (isset($metadata['unfurls']))
			{
				$unfurlIds = array_merge($unfurlIds, $metadata['unfurls']);
			}
		}

		$unfurls = [];
		if ($unfurlIds)
		{
			$unfurls = $this->finder(UnfurlResultFinder::class)
				->whereIds(array_unique($unfurlIds))
				->fetch();
		}

		if (!$unfurls || !$unfurls->count())
		{
			return;
		}

		if (!$skipRecrawl)
		{
			$this->recrawlUnfurlsIfNecessary($unfurls);
		}

		foreach ($content AS $item)
		{
			$metadata = $item->{$metadataKey};
			if (isset($metadata['unfurls']))
			{
				$contentUnfurls = [];
				foreach ($metadata['unfurls'] AS $id)
				{
					if (!isset($unfurls[$id]))
					{
						continue;
					}
					$contentUnfurls[$unfurls[$id]->url_hash] = $unfurls[$id];
				}

				$item->{"set$getterKey"}($contentUnfurls);
			}
		}
	}

	/**
	 * @param AbstractCollection<UnfurlResult> $unfurls
	 */
	public function recrawlUnfurlsIfNecessary(ArrayCollection $unfurls)
	{
		foreach ($unfurls AS $result)
		{
			if (!$result->pending && $result->requiresRecrawl())
			{
				$result->pending = true;
				$result->save();
			}
		}
	}
}
