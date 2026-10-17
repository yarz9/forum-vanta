<?php

namespace XF\Repository;

use XF\Finder\AdminFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class AdminRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findAdminsForList()
	{
		return $this->finder(AdminFinder::class)
			->with('User')
			->order('User.username');
	}
}
