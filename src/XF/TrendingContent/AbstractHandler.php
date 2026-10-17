<?php

namespace XF\TrendingContent;

use XF\Entity\TrendingResult;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Repository\AttachmentRepository;
use XF\Repository\TrendingContentRepository;

/**
 * @template T of Entity
 */
abstract class AbstractHandler
{
	/**
	 * @var string
	 */
	protected $contentType;

	public function __construct(string $contentType)
	{
		$this->contentType = $contentType;
	}

	public function getContentType(): string
	{
		return $this->contentType;
	}

	/**
	 * Renders the given content for display.
	 *
	 * @param T $content
	 */
	public function render(
		TrendingResult $result,
		Entity $content,
		string $macroName,
		int $snippetLength = 0
	): string
	{
		$templateName = $this->getTemplateName();
		$macroArguments = $this->getMacroArguments(
			$macroName,
			$result,
			$content,
			$snippetLength
		);

		$templater = \XF::app()->templater();
		return $templater->renderMacro(
			$templateName,
			$macroName,
			$macroArguments
		);
	}

	/**
	 * Returns the template to use for rendering this content type.
	 */
	protected function getTemplateName(): string
	{
		$contentType = $this->contentType;
		return "public:trending_content_item_{$contentType}";
	}

	/**
	 * Returns the arguments to use for rendering the given macro.
	 *
	 * @param T $content
	 */
	protected function getMacroArguments(
		string $macroName,
		TrendingResult $result,
		Entity $content,
		int $snippetLength = 0
	): array
	{
		return [
			'result' => $result,
			'content' => $content,
			'snippetLength' => $snippetLength,
		];
	}

	/**
	 * Fetches the content for a content ID or array of content IDs and style.
	 *
	 * @param int|list<int> $contentId
	 *
	 * @return AbstractCollection<T>|T|null
	 */
	public function getContent($contentId, string $style)
	{
		return \XF::app()->findByContentType(
			$this->contentType,
			$contentId,
			$this->getEntityWith($style)
		);
	}

	/**
	 * Returns the relations to eager-load when fetching content for this
	 * content type and style.
	 *
	 * @return list<string>
	 */
	public function getEntityWith(string $style): array
	{
		return [];
	}

	/**
	 * Filters content to ensure it is visible to the visitor.
	 *
	 * @param AbstractCollection<T> $content
	 *
	 * @return AbstractCollection<T>
	 */
	public function filterContent(AbstractCollection $content): AbstractCollection
	{
		return $content;
	}

	/**
	 * Hydrates attachments for a collection of content entities.
	 *
	 * @param AbstractCollection<T> $content A collection of all content entities
	 */
	public function addAttachmentsToContent(AbstractCollection $content): void
	{
	}

	/**
	 * A mechanism for hydrating attachments for a collection of content
	 * entities.
	 *
	 * This may be called within a handler's addAttachmentsToContent method.
	 *
	 * @param AbstractCollection<T> $content
	 */
	protected function addAttachmentsToContentInternal(
		AbstractCollection $content,
		string $countKey = 'attach_count',
		string $relationKey = 'Attachments'
	): void
	{
		$attachmentRepo = \XF::repository(AttachmentRepository::class);
		$attachmentRepo->addAttachmentsToContent(
			$content,
			$this->contentType,
			$countKey,
			$relationKey
		);
	}

	protected function areAttachmentsHydratedForStyle(string $style): bool
	{
		$trendingRepo = \XF::repository(TrendingContentRepository::class);
		return $trendingRepo->areAttachmentsHydratedForStyle($style);
	}
}
