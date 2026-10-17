<?php

namespace XF\Webhook\Event;

use XF\Mvc\Entity\Entity;
use XF\Phrase;

use function in_array;

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
	 * The plural title of this webhook action handler used in the admin control panel when displaying supported webhook events.
	 *
	 * @return Phrase
	 */
	public function getTitle(): Phrase
	{
		return \XF::phrase(\XF::app()->getContentTypePhraseName($this->contentType, true));
	}

	/**
	 * An array of event strings that this webhook handler may be triggered for allowing administrators to opt-in to only sending webhooks on specific events.
	 * Events not in this array can still be used for webhooks, but they will only be sent if the webhook is configured to send all events.
	 *
	 * @return string[]
	 */
	public function getEvents(): array
	{
		$events = ['insert', 'update', 'delete'];

		$fields = \XF::app()->getFieldsForContentType($this->contentType);
		$handlers = preg_grep('#_handler_class$#', array_keys($fields));
		$handlers = array_intersect_key($fields, array_flip($handlers));

		foreach ($handlers AS $handlerClass)
		{
			if (class_exists($handlerClass))
			{
				$handlerClass = \XF::extendClass($handlerClass);

				if (method_exists($handlerClass, 'getWebhookEvents'))
				{
					$events = array_merge($events, $handlerClass::getWebhookEvents());
				}
			}
		}

		return $events;
	}

	/**
	 * The name of the event. Used in the control panel when displaying supported webhook events.
	 *
	 * @param string $event
	 * @return string
	 */
	public function getEventName(string $event): string
	{
		return $this->contentType . '.' . $event;
	}

	/**
	 * A hint to describe when the event might be triggered. Used in the control panel when displaying supported webhook events.
	 *
	 * @param string $event
	 * @return string
	 */
	public function getEventHint(string $event): string
	{
		return '';
	}

	/**
	 * Used to check whether this webhook handler can be used for the given content type and event. If a webhook is configured to send all events,
	 * events not in the getEvents() array will still be sent.
	 *
	 * @param string $event
	 * @param Entity $webhook
	 * @return bool
	 */
	public function canSendForContentTypeEvent(string $event, Entity $webhook): bool
	{
		$contentTypeEvents = $webhook->events[$this->contentType] ?? [];

		if ($contentTypeEvents === '*')
		{
			return true;
		}

		if (in_array($event, $contentTypeEvents))
		{
			return true;
		}

		return false;
	}

	public function getCriteriaClass(): ?string
	{
		return null;
	}

	public function renderCriteria(Entity $webhook): string
	{
		$templateName = $this->getCriteriaTemplateName();
		if (!$templateName)
		{
			return '';
		}
		return \XF::app()->templater()->renderTemplate(
			$templateName,
			$this->getCriteriaTemplateParams($webhook)
		);
	}

	/**
	 * The template to use when displaying criteria options for this webhook. If null, no additional criteria will be displayed.
	 *
	 * @return string|null
	 */
	public function getCriteriaTemplateName(): ?string
	{
		return null;
	}

	/**
	 * The parameters to pass to the criteria template.
	 *
	 * @return array
	 */
	public function getCriteriaTemplateParams(Entity $webhook): array
	{
		$contentType = $this->getContentType();
		return [
			'contentType' => $contentType,
			'criteria' => $webhook->criteria[$contentType] ?? [],
		];
	}
}
