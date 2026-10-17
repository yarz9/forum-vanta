<?php

namespace XF\Import\Importer;

use XF\Entity\Node;
use XF\Job\Forum;
use XF\Job\Poll;
use XF\Job\Thread;

abstract class AbstractForumImporter extends AbstractCoreImporter
{
	public function canRetainIds()
	{
		if (!parent::canRetainIds())
		{
			return false;
		}

		$db = $this->app->db();

		$maxThreadId = $db->fetchOne("SELECT MAX(thread_id) FROM xf_thread");
		if ($maxThreadId)
		{
			return false;
		}

		$maxPostId = $db->fetchOne("SELECT MAX(post_id) FROM xf_post");
		if ($maxPostId)
		{
			return false;
		}

		$maxNodeId = $db->fetchOne("SELECT MAX(node_id) FROM xf_node");
		if ($maxNodeId > 2)
		{
			// 1 and 2 are default nodes
			return false;
		}

		return true;
	}

	public function resetDataForRetainIds()
	{
		// nodes 1 and 2 are created by default in the installer so we need to remove those if retaining IDs
		$node = $this->em()->find(Node::class, 1);
		if ($node)
		{
			$node->delete();
		}

		$node = $this->em()->find(Node::class, 2);
		if ($node)
		{
			$node->delete();
		}
	}

	public function getFinalizeJobs(array $stepsRun)
	{
		$jobs = parent::getFinalizeJobs($stepsRun);

		$jobs[] = Thread::class;
		$jobs[] = Forum::class;
		$jobs[] = Poll::class;

		return $jobs;
	}

	protected function getNodePermissionDefinitionsGrouped()
	{
		static $permissionsGrouped = null;

		if ($permissionsGrouped === null)
		{
			$permissions = $this->db()->fetchAll("
				SELECT *
				FROM xf_permission
				WHERE permission_group_id IN('category', 'forum', 'linkForum')
			");

			foreach ($permissions AS $p)
			{
				$permissionsGrouped[$p['permission_group_id']][$p['permission_id']] = $p;
			}
		}

		return $permissionsGrouped;
	}
}
