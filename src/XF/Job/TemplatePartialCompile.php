<?php

namespace XF\Job;

use XF\Behavior\DevOutputWritable;
use XF\Entity\Template;
use XF\Entity\TemplateModification;
use XF\Repository\StyleRepository;
use XF\Service\Template\CompileService;

class TemplatePartialCompile extends AbstractJob
{
	protected $defaultData = [
		'templateIds' => [],
		'position' => 0,
	];

	public function run($maxRunTime)
	{
		$s = microtime(true);

		/** @var CompileService $compileService */
		$compileService = $this->app->service(CompileService::class);

		foreach ($this->data['templateIds'] AS $k => $templateId)
		{
			/** @var Template $template */
			$template = $this->app->find(Template::class, $templateId);
			if (!$template)
			{
				continue;
			}

			$template->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', false);

			$needsSave = $template->reparseTemplate(true);
			if ($needsSave)
			{
				// this will recompile
				$template->save();
			}
			else
			{
				$compileService->recompile($template);
			}

			\XF::dequeueRunOnce('styleLastModifiedDate'); // we'll update this later

			unset($this->data['templateIds'][$k]);

			if ($maxRunTime && microtime(true) - $s > $maxRunTime)
			{
				break;
			}
		}

		// decache to reduce memory usage
		\XF::em()->clearEntityCache(Template::class);
		\XF::em()->clearEntityCache(TemplateModification::class);

		if (!$this->data['templateIds'])
		{
			/** @var StyleRepository $repo */
			$repo = $this->app->repository(StyleRepository::class);
			$repo->updateAllStylesLastModifiedDateLater();

			return $this->complete();
		}
		else
		{
			$this->data['position']++;
			return $this->resume();
		}
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('rebuilding');
		$typePhrase = \XF::phrase('templates');
		return sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat('. ', $this->data['position']));
	}

	public function canCancel()
	{
		return false;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}
