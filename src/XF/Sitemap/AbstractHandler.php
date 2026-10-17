<?php

namespace XF\Sitemap;

use XF\App;

/**
 * @template T of \XF\Mvc\Entity\Entity
 */
abstract class AbstractHandler
{
	/**
	 * @var string
	 */
	protected $contentType;

	/**
	 * @var App
	 */
	protected $app;

	/**
	 * @param string $contentType
	 */
	public function __construct($contentType, App $app)
	{
		$this->contentType = $contentType;
		$this->app = $app;
	}

	/**
	 * @param string $table
	 * @param string $column
	 * @param int $start
	 * @param int $limit
	 *
	 * @return list<int>
	 */
	protected function getIds($table, $column, $start, $limit = 2000)
	{
		$db = $this->app->db();

		$ids = $db->fetchAllColumn($db->limit(
			"
				SELECT $column
				FROM $table
				WHERE $column > ?
				ORDER BY $column
			",
			$limit
		), $start);

		return $ids;
	}

	/**
	 * @param int $start
	 *
	 * @return \XF\Mvc\Entity\AbstractCollection<T>
	 */
	abstract public function getRecords($start);

	/**
	 * @param T $record
	 *
	 * @return Entry
	 */
	abstract public function getEntry($record);

	/**
	 * Performs the base, global permission check before checking for records. This
	 * can be bypassed on a per-content basis if needed.
	 *
	 * @return bool
	 */
	public function basePermissionCheck()
	{
		return \XF::visitor()->hasPermission('general', 'view');
	}

	/**
	 * @param T $record
	 *
	 * @return bool
	 */
	public function isIncluded($record)
	{
		return true;
	}
}
