<?php

namespace XF\Behavior;

use XF\Entity\LinkableInterface;
use XF\Job\ContentIndexNow;
use XF\Mvc\Entity\Behavior;

use function is_array, is_string;

class Indexable extends Behavior
{
	protected function getDefaultConfig()
	{
		return [
			'checkForUpdates' => null,
			'indexLocally' => true,
			'enqueueIndexNow' => false,
		];
	}

	protected function getDefaultOptions(): array
	{
		return [
			'skipIndexNow' => false,
		];
	}

	protected function verifyConfig()
	{
		if (!$this->contentType())
		{
			throw new \LogicException("Structure must provide a contentType value");
		}

		if ($this->config['checkForUpdates'] === null && !is_callable([$this->entity, 'requiresSearchIndexUpdate']))
		{
			throw new \LogicException("If checkForUpdates is null/not specified, the entity must define requiresSearchIndexUpdate");
		}
	}

	public function postSave()
	{
		if ($this->requiresIndexUpdate())
		{
			if ($this->indexLocally())
			{
				$this->triggerReindex();
			}

			if ($this->enqueueIndexNow())
			{
				$this->enqueueContentForIndexNow([
					'content_type' => $this->contentType(),
					'content_id' => $this->entity->getEntityId(),
				]);
			}
		}
	}

	public function preDelete()
	{
		if ($this->entity instanceof LinkableInterface && $this->enqueueIndexNow())
		{
			$this->enqueueContentForIndexNow([
				'content_url' => $this->entity->getContentUrl(true),
			]);
		}
	}

	public function triggerReindex()
	{
		// if inserting this content, it won't exist, so don't need to trigger a delete
		$deleteIfNeeded = $this->entity->isInsert() ? false : true;

		\XF::runOnce(
			'searchIndex-' . $this->contentType() . $this->entity->getEntityId(),
			function () use ($deleteIfNeeded)
			{
				$this->app()->search()->index($this->contentType(), $this->entity, $deleteIfNeeded);
			}
		);
	}

	public function enqueueContentForIndexNow(array $params): void
	{
		if ($this->options['skipIndexNow'])
		{
			return;
		}

		$options = $this->app()->options();
		if (!$options->useFriendlyUrls || !$options->indexNow['enabled'])
		{
			return;
		}

		\XF::app()->jobManager()->enqueue(ContentIndexNow::class, $params);
	}

	protected function requiresIndexUpdate()
	{
		if ($this->entity->isInsert())
		{
			return true;
		}

		$checkForUpdates = $this->config['checkForUpdates'];

		if ($checkForUpdates === null)
		{
			// method is verified above
			return $this->entity->requiresSearchIndexUpdate();
		}
		else if (is_array($checkForUpdates) || is_string($checkForUpdates))
		{
			return $this->entity->isChanged($checkForUpdates);
		}
		else
		{
			return $checkForUpdates;
		}
	}

	protected function indexLocally(): bool
	{
		return ($this->config['indexLocally']);
	}

	protected function enqueueIndexNow(): bool
	{
		return ($this->config['enqueueIndexNow']);
	}

	public function postDelete()
	{
		\XF::runOnce(
			'searchIndex-' . $this->contentType() . $this->entity->getEntityId(),
			function ()
			{
				$this->app()->search()->delete($this->contentType(), $this->entity);
			}
		);
	}
}
