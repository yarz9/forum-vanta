<?php

namespace XF\LogSearch;

use XF\AdminSearch\AbstractFieldSearch;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Router;

abstract class AbstractHandler extends AbstractFieldSearch
{
	abstract protected function getDateField();

	public function applyDateConstraints($start, $end)
	{
		$dateField = $this->getDateField();
		$finder = $this->getFinder()->setDefaultOrder($dateField, 'DESC');

		if ($start && $end)
		{
			$finder->where($dateField, 'BETWEEN', [$start, $end]);
		}
		else if ($start)
		{
			$finder->where($dateField, '>=', $start);
		}
		else if ($end)
		{
			$finder->where($dateField, '<=', $end);
		}
	}

	/**
	 * Returns elements for the search result xf:label.
	 * Return a string for a simple label, or an array for an inline UL
	 *
	 * @param Entity $record
	 *
	 * @return array|string
	 */
	protected function getLabel(Entity $record)
	{
		return $record->get($this->searchFields[0]);
	}

	/**
	 * Returns the HTML for the search result xf:hint, if any
	 *
	 * @param Entity $record
	 *
	 * @return string
	 */
	protected function getHint(Entity $record)
	{
		return '';
	}

	/**
	 * Returns the user logged as having performed the action, if available
	 *
	 * @param Entity $record
	 *
	 * @return User|null
	 */
	protected function getLogUser(Entity $record)
	{
		return null;
	}

	public function getTemplateData(Entity $record)
	{
		/** @var Router $router */
		$router = $this->app->container('router.admin');

		return $this->getTemplateParams($router, $record, [
			'link' => $router->buildLink($this->getRouteName(), $record),
			'label' => $this->getLabel($record),
			'hint' => $this->getHint($record),
			'date' => $record->get($this->getDateField()),
			'User' => $this->getLogUser($record),
			'ip' => $record->ip_address ?? null,
		]);
	}

	public function getTemplateName()
	{
		return 'admin:log_search_type';
	}

	public function isSearchable()
	{
		return \XF::visitor()->hasAdminPermission('viewLogs');
	}

	public function getDisplayOrder()
	{
		return 1;
	}
}
