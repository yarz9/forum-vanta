<?php

namespace XF\FeaturedContent;

use XF\Entity\ContainableInterface;
use XF\Entity\DatableInterface;
use XF\Entity\FeaturedContent;
use XF\Entity\LinkableInterface;
use XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Phrase;
use XF\Repository\AttachmentRepository;
use XF\Repository\FeaturedContentRepository;
use XF\Repository\WebhookRepository;

use function get_class;

/**
 * @template T of Entity
 */
abstract class AbstractHandler
{
	/**
	 * @var int
	 */
	public const SNIPPET_LENGTH = 500;

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
	 * Determines whether or not the given content may be viewed.
	 *
	 * @param T $content
	 */
	public function canViewContent(Entity $content, &$error = null): bool
	{
		if (method_exists($content, 'canView'))
		{
			return $content->canView($error);
		}

		throw new \LogicException(
			'Override ' . get_class($this) . '::canViewContent'
		);
	}

	/**
	 * Determines the container ID of the given content.
	 *
	 * @param T $content
	 */
	public function getContentContainerId(Entity $content): int
	{
		if (!($content instanceof ContainableInterface))
		{
			throw new \LogicException(
				'Could not determine content container ID; please override'
			);
		}

		return $content->getContentContainerId();
	}

	/**
	 * Determines the user ID of the user who created the given content.
	 *
	 * @param T $content
	 */
	public function getContentUserId(Entity $content): int
	{
		if (isset($content->user_id))
		{
			return $content->user_id;
		}

		if (isset($content->User) && $content->User instanceof User)
		{
			return $content->User->user_id;
		}

		throw new \LogicException(
			'Override ' . get_class($this) . '::getContentUserId'
		);
	}

	/**
	 * Determines the username of the user who created the given content.
	 *
	 * @param T $content
	 */
	public function getContentUsername(Entity $content): string
	{
		if (isset($content->username))
		{
			return $content->username;
		}

		if (isset($content->User) && $content->User instanceof User)
		{
			return $content->User->username;
		}

		throw new \LogicException(
			'Override ' . get_class($this) . '::getContentUsername'
		);
	}

	/**
	 * Determines the date the given content was created.
	 *
	 * @param T $content
	 */
	public function getContentDate(Entity $content): int
	{
		if (!($content instanceof DatableInterface))
		{
			throw new \LogicException(
				'Could not determine content date; please override'
			);
		}

		return $content->getContentDate();
	}

	/**
	 * Determines whether the given content is visible.
	 *
	 * @param T $content
	 */
	public function getContentVisibility(Entity $content): bool
	{
		if (method_exists($content, 'isVisible'))
		{
			return $content->isVisible();
		}

		throw new \LogicException(
			'Override ' . get_class($this) . '::getContentVisibility'
		);
	}

	/**
	 * Determines the title of the given content.
	 *
	 * This will be displayed on the feature when a custom title is not set.
	 *
	 * @param T $content
	 */
	public function getContentTitle(Entity $content): string
	{
		if ($content instanceof LinkableInterface)
		{
			$title = $content->getContentTitle('featured_content');

			if ($title instanceof Phrase)
			{
				$title = $title->render('raw');
			}

			return $title;
		}

		throw new \LogicException(
			'Implement XF\Entity\LinkableInterface for ' . get_class($content)
			. ' or override ' . get_class($this) . '::getContentTitle'
		);
	}

	/**
	 * Determines the image of the given content.
	 *
	 * This will be displayed on the feature when a custom image is not set.
	 * Note that the size code is not currently utilized.
	 *
	 * @param T $content
	 */
	abstract public function getContentImage(
		Entity $content,
		?string $sizeCode = null
	): ?string;

	/**
	 * Determines the snippet of the given content.
	 *
	 * This will be displayed on the feature when a custom snippet is not set.
	 *
	 * @param T $content
	 */
	abstract public function getContentSnippet(Entity $content): string;

	protected function getSnippetFromString(?string $string): string
	{
		if (!$string)
		{
			return '';
		}

		$stringFormatter = \XF::app()->stringFormatter();
		return $stringFormatter->snippetString(
			$string,
			static::SNIPPET_LENGTH,
			['stripQuote' => true]
		);
	}

	/**
	 * @param T $content
	 *
	 * @return array<string, mixed>
	 */
	public function getContentStructuredData(Entity $content): array
	{
		return [];
	}

	/**
	 * Determines the link of the given content.
	 *
	 * @param T $content
	 */
	public function getContentLink(
		Entity $content,
		bool $canonical = false
	): string
	{
		if ($content instanceof LinkableInterface)
		{
			return $content->getContentUrl($canonical);
		}

		throw new \LogicException(
			'Implement XF\Entity\LinkableInterface for ' . get_class($content)
			. ' or override ' . get_class($this) . '::getContentLink'
		);
	}

	/**
	 * Determines whether or not the given content should be automatically
	 * featured.
	 *
	 * This is called by the featurable behavior when the entity is first
	 * inserted.
	 *
	 * @param T $content
	 */
	public function shouldAutoFeature(Entity $content): bool
	{
		return false;
	}

	/**
	 * A hook called when the given content is featured.
	 *
	 * @param T $content
	 */
	public function onContentFeature(
		Entity $content,
		FeaturedContent $feature
	): void
	{
		$content->fastUpdate('featured', true);

		\XF::repository(WebhookRepository::class)->queueWebhook(
			$content->getEntityContentType(),
			$content->getEntityId(),
			'feature',
			$content
		);
	}

	/**
	 * A hook called when the given content is unfeatured.
	 *
	 * @param T $content
	 */
	public function onContentUnfeature(
		Entity $content,
		FeaturedContent $feature
	): void
	{
		$content->fastUpdate('featured', false);

		\XF::repository(WebhookRepository::class)->queueWebhook(
			$content->getEntityContentType(),
			$content->getEntityId(),
			'unfeature',
			$content
		);
	}

	/**
	 * Renders the given feature for display on the featured content page.
	 *
	 * If the macro does not exist in the content-type template, it will
	 * fallback to the global macro.
	 */
	public function render(
		FeaturedContent $feature,
		string $macroName,
		int $snippetLength = 0
	): string
	{
		if (!$feature->Content)
		{
			return '';
		}

		$templateName = $this->getTemplateName();
		$macroArguments = $this->getMacroArguments(
			$macroName,
			$feature,
			$snippetLength
		);

		$templater = \XF::app()->templater();
		if ($templater->isKnownMacro($templateName, $macroName))
		{
			return $templater->renderMacro(
				$templateName,
				$macroName,
				$macroArguments
			);
		}

		return $templater->renderMacro(
			'featured_content_item',
			$macroName,
			$macroArguments
		);
	}

	/**
	 * Returns the template to use for rendering features for this content
	 * type.
	 */
	protected function getTemplateName(): string
	{
		$contentType = $this->contentType;
		return "public:featured_content_item_{$contentType}";
	}

	/**
	 * Returns the macro arguments to use for rendering the given feature macro.
	 *
	 * @return array<string, mixed>
	 */
	protected function getMacroArguments(
		string $macroName,
		FeaturedContent $feature,
		int $snippetLength = 0
	): array
	{
		$content = $feature->Content;

		return [
			'feature' => $feature,
			'content' => $content,
			'snippetLength' => $snippetLength,
		];
	}

	/**
	 * Fetches the content given a content ID or an array of content IDs.
	 *
	 * @param int|list<int> $contentId
	 *
	 * @return AbstractCollection<T>|T|null
	 */
	public function getContentForStyle($contentId, string $style)
	{
		return \XF::app()->findByContentType(
			$this->contentType,
			$contentId,
			$this->getEntityWithForStyle($style)
		);
	}

	/**
	 * @deprecated
	 */
	public function getContent($contentId)
	{
		return $this->getContentForStyle($contentId, 'article');
	}

	/**
	 * Returns the relations to eager-load when fetching content for this
	 * content type.
	 *
	 * @return list<string>
	 */
	public function getEntityWithForStyle(string $style): array
	{
		return $this->getEntityWith();
	}

	/**
	 * @deprecated
	 */
	public function getEntityWith(): array
	{
		return [];
	}

	/**
	 * @param AbstractCollection<T> $imagelessContent A collection of content entities with no custom feature image
	 * @param AbstractCollection<T> $content A collection of all content entities
	 */
	public function addAttachmentsToContentExternal(
		AbstractCollection $imagelessContent,
		AbstractCollection $content
	): void
	{
		$this->addAttachmentsToContent($imagelessContent, $content);
	}

	/**
	 * Hydrates attachments for a collection of content entities.
	 *
	 * This is typically only necessary when the default content image is
	 * derived from the content attachments.
	 *
	 * @param AbstractCollection<T> $imagelessContent A collection of content entities with no custom feature image
	 * @param AbstractCollection<T> $content A collection of all content entities
	 */
	protected function addAttachmentsToContent(
		AbstractCollection $imagelessContent,
		AbstractCollection $content
	): void
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

	/**
	 * @return list<string>
	 */
	public static function getWebhookEvents(): array
	{
		return ['feature', 'unfeature'];
	}

	protected function areAttachmentsHydratedForStyle(string $style): bool
	{
		$featureRepo = \XF::repository(FeaturedContentRepository::class);
		return $featureRepo->areAttachmentsHydratedForStyle($style);
	}
}
