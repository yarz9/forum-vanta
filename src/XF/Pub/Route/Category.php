<?php

namespace XF\Pub\Route;

use XF\Entity\Node;
use XF\Mvc\RouteBuiltLink;
use XF\Mvc\Router;

use function intval, is_array;

class Category
{
	public static function build(&$prefix, array &$route, &$action, &$data, array &$params, Router $router)
	{
		if ($params || $action)
		{
			return null;
		}

		if ($data instanceof Node)
		{
			$node = $data;
		}
		else if ($data instanceof \XF\Entity\Category)
		{
			$node = $data->Node;
		}
		else if (is_array($data) && !empty($data['node_id']))
		{
			$node = $data;
		}
		else
		{
			$node = null;
		}

		if (!$node)
		{
			return null;
		}

		if (empty($node['depth']) && !empty($node['display_in_list']) && !\XF::options()->categoryOwnPage)
		{
			$route = (\XF::options()->forumsDefaultPage == 'forums' ? 'forums' : 'forums/list');

			$link = $router->buildLink('nopath:' . $route);

			if ($link === '.')
			{
				$link = '';
			}

			$title = $router->prepareStringForUrl($data['title']) . '.' . intval($data['node_id']);
			return new RouteBuiltLink($link . '#' . $title);
		}

		return null; // default processing otherwise
	}
}
