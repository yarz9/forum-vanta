<?php

namespace XF\Pub\Controller;

use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\RouteMatch;

trait EmbedResolverTrait
{
	public static function getResolvableActions(): array
	{
		return ['index'];
	}

	abstract public static function resolveToEmbeddableContent(ParameterBag $params, RouteMatch $routeMatch): ?Entity;
}
