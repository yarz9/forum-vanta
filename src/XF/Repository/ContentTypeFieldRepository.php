<?php

namespace XF\Repository;

use XF\Finder\ContentTypeFieldFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class ContentTypeFieldRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findContentTypeFieldsForList()
	{
		return $this->finder(ContentTypeFieldFinder::class)->order(['content_type', 'field_name']);
	}

	public function getContentTypeCacheData()
	{
		$fields = $this->finder(ContentTypeFieldFinder::class)->whereAddOnActive()->fetch();
		$output = [];
		foreach ($fields AS $field)
		{
			$output[$field->content_type][$field->field_name] = $field->field_value;
		}

		return $output;
	}

	public function rebuildContentTypeCache()
	{
		$cache = $this->getContentTypeCacheData();
		\XF::registry()->set('contentTypes', $cache);
		return $cache;
	}
}
