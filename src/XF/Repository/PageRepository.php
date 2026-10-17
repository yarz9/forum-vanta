<?php

namespace XF\Repository;

use XF\Entity\Page;
use XF\Entity\User;
use XF\Mvc\Entity\Repository;

class PageRepository extends Repository
{
	public function logView(Page $page, User $user)
	{
		// TODO: update batching?
		$this->db()->query(
			'-- XFDB=noForceAllWrite
				UPDATE xf_page
				SET view_count = view_count + 1
				WHERE node_id = ?',
			[$page->node_id]
		);
	}
}
