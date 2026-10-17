<?php

namespace XF\Entity;

use XF\EmbedResolver\AbstractHandler;
use XF\Mvc\Entity\Repository;
use XF\Mvc\Entity\Structure;
use XF\Repository\EmbedResolverRepository;

trait EmbedResolverTrait
{
	public function canViewEmbed(): bool
	{
		$handler = $this->getEmbedHandler();

		if (!$handler)
		{
			return false;
		}

		return $handler->canViewContent($this);
	}

	public function renderEmbed(): string
	{
		$handler = $this->getEmbedHandler();

		if (!$handler)
		{
			return '';
		}

		return $handler->render($this);
	}

	public function getOembedOutput(): array
	{
		$handler = $this->getEmbedHandler();

		if (!$handler)
		{
			return [];
		}

		return $handler->getOembedOutput($this);
	}

	public function getEmbedCodeHtml(): string
	{
		$handler = $this->getEmbedHandler();

		if (!$handler)
		{
			return '';
		}

		return $handler->getEmbedCodeHtml($this);
	}

	public function getOembedEndpointUrl(): string
	{
		if (!method_exists($this, 'getContentUrl'))
		{
			return '';
		}

		return \XF::app()->router('api')->buildLink('canonical:oembed', null, ['url' => $this->getContentUrl(true)]);
	}

	public function getEmbedHandler(): AbstractHandler
	{
		return $this->getEmbedRepo()->getEmbedHandler($this->getEntityContentType());
	}

	public static function addEmbedResolverStructureElements(Structure $structure)
	{
	}

	/**
	 * @return Repository|EmbedResolverRepository
	 */
	protected function getEmbedRepo(): EmbedResolverRepository
	{
		return $this->repository(EmbedResolverRepository::class);
	}
}
