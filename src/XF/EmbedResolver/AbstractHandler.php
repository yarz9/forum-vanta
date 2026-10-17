<?php

namespace XF\EmbedResolver;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;

abstract class AbstractHandler
{
	protected $contentType;

	public function __construct(string $contentType)
	{
		$this->contentType = $contentType;
	}

	public function canViewContent(Entity $content, ?string &$error = null): bool
	{
		if (method_exists($content, 'canView'))
		{
			return $content->canView($error);
		}

		throw new \LogicException("Could not determine content viewability; please override");
	}

	public function getTemplateName(): string
	{
		return 'public:embed_resolver_' . $this->contentType;
	}

	public function getTemplateData(Entity $content): array
	{
		return [
			'content' => $content,
		];
	}

	public function render(Entity $content): string
	{
		$template = $this->getTemplateName();
		$data = $this->getTemplateData($content);

		return \XF::app()->templater()->renderTemplate($template, $data);
	}

	public function getOembedOutput(Entity $content): array
	{
		return [
			'version' => $this->getOembedVersion(),
			'type' => $this->getOembedType(),

			'provider_name' => $this->getOembedProviderName(),
			'provider_url' => $this->getOembedProviderUrl(),

			'author_name' => $content->User ? $content->User->username : $content->username ?? $this->getOembedProviderName(),
			'author_url' => $content->User ? \XF::app()->router('public')->buildLink('members', $content->User) : $this->getOembedProviderUrl(),

			'html' => $this->getEmbedCodeHtml($content),

			'referrer' => '',
			'cache_age' => 3600,
		];
	}

	public function getOembedVersion(): string
	{
		return '1.0';
	}

	public function getOembedType(): string
	{
		return 'rich';
	}

	public function getOembedProviderName(): string
	{
		return \XF::options()->boardTitle ?? '';
	}

	public function getOembedProviderUrl(): string
	{
		return \XF::options()->boardUrl ?? '';
	}

	public function getEmbedCodeHtml(Entity $content): string
	{
		return '<div class="js-xf-embed" data-url="' . $this->getOembedProviderUrl() . '" data-content="' . $content->getEntityContentTypeId() . '"></div><script defer src="' . $this->getOembedProviderUrl() . '/js/xf/external_embed.js?_v=' . \XF::app()['jsVersion'] . '"></script>';
	}

	public function getEntityWith(): array
	{
		return [];
	}

	/**
	 * @param $id
	 * @return null|ArrayCollection|Entity
	 */
	public function getContent($id)
	{
		return \XF::app()->findByContentType($this->contentType, $id, $this->getEntityWith());
	}

	public function getContentType(): string
	{
		return $this->contentType;
	}
}
