<?php

namespace XF\Repository;

use XF\EmbedResolver\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;

use function call_user_func, in_array;

class EmbedResolverRepository extends Repository
{
	public function getEntityFromUrl(string $url, ?string &$error = null): ?Entity
	{
		$routePath = $this->app()->request()->getRoutePathFromUrl($url, true);
		$routeMatch = $this->app()->router('public')->routeToController($routePath);
		$params = $routeMatch->getParameterBag();

		if (!$routeMatch->getController() || !$params->count())
		{
			return null;
		}

		$class = \XF::stringToClass($routeMatch->getController(), '%s\Pub\Controller\%s');
		$controller = $this->app()->extendClass($class);

		try
		{
			$valid = (
				$controller
				&& class_exists($controller)
				&& is_callable([$controller, 'getResolvableActions'])
				&& is_callable([$controller, 'resolveToEmbeddableContent'])
			);
		}
		catch (\Throwable $e)
		{
			$valid = false;
		}

		if (!$valid)
		{
			return null;
		}

		$actions = call_user_func([$controller, 'getResolvableActions']);
		if (!in_array($routeMatch->getAction(), $actions))
		{
			return null;
		}

		$content = call_user_func([$controller, 'resolveToEmbeddableContent'], $params, $routeMatch);
		if (!$content)
		{
			return null;
		}

		if (!method_exists($content, 'addEmbedResolverStructureElements'))
		{
			return null;
		}

		return $content;
	}

	public function addEmbedsToContent($content, $metadataKey = 'embed_metadata'): void
	{
		if (!$content)
		{
			return;
		}

		$embeds = [];
		foreach ($content AS $item)
		{
			$metadata = $item->{$metadataKey};
			if (isset($metadata['embeds']))
			{
				$embeds = array_merge_recursive($embeds, $metadata['embeds']);
			}
		}

		if (!$embeds)
		{
			return;
		}

		$embedContent = [];

		foreach ($embeds AS $contentType => $contentTypeIds)
		{
			$entityName = \XF::app()->getContentTypeFieldValue($contentType, 'entity');
			if (!$entityName)
			{
				continue;
			}

			$embedHandler = $this->getEmbedHandler($contentType);
			if (!$embedHandler)
			{
				continue;
			}

			$embedContent[$contentType] = $embedHandler->getContent($contentTypeIds);
		}

		foreach ($content AS $item)
		{
			$metadata = $item->{$metadataKey};
			if (isset($metadata['embeds']))
			{
				$contentEmbeds = [];
				foreach ($metadata['embeds'] AS $contentType => $contentIds)
				{
					foreach ($contentIds AS $contentId)
					{
						if (!isset($embedContent[$contentType][$contentId]))
						{
							continue;
						}
						$contentEmbeds[$contentType][$contentId] = $embedContent[$contentType][$contentId];
					}
				}
				$item->setEmbeds($contentEmbeds);
			}
		}
	}

	public function isValidEmbed(string $contentType, $contentId = null): bool
	{
		$embedHandler = $this->getEmbedHandler($contentType);

		if (!$embedHandler)
		{
			return false;
		}

		if ($contentId)
		{
			$content = $embedHandler->getContent($contentId);
			if (!$content || !$embedHandler->canViewContent($content))
			{
				return false;
			}
		}

		return true;
	}

	public function getEmbedHandler(string $type, $throw = false): ?AbstractHandler
	{
		$handlerClass = \XF::app()->getContentTypeFieldValue($type, 'embed_resolver_class');
		if (!$handlerClass)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("No embed resolver handler for '$type'");
			}
			return null;
		}

		if (!class_exists($handlerClass))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("Embed resolver handler for '$type' does not exist: $handlerClass");
			}
			return null;
		}

		$handlerClass = \XF::extendClass($handlerClass);
		return new $handlerClass($type);
	}
}
