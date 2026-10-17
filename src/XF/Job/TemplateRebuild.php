<?php

namespace XF\Job;

use XF\Behavior\DevOutputWritable;
use XF\Entity\Template;
use XF\Entity\TemplateModification;
use XF\Repository\StyleRepository;
use XF\Service\Template\CompileService;
use XF\Service\Template\RebuildService;

class TemplateRebuild extends AbstractJob
{
	protected $defaultData = [
		'steps' => 0,
		'templateId' => 0,
		'batch' => 300,
		'mapped' => false,
		'skipCore' => false,
	];

	public function run($maxRunTime)
	{
		$start = microtime(true);

		if (!$this->data['mapped'])
		{
			/** @var RebuildService $rebuildService */
			$rebuildService = $this->app->service(RebuildService::class);
			$rebuildService->rebuildFullTemplateMap();

			$this->data['mapped'] = true;
		}

		$this->data['steps']++;

		$db = $this->app->db();
		$em = $this->app->em();
		$app = \XF::app();

		if ($this->data['skipCore'])
		{
			$skipCoreSql = "AND (addon_id <> 'XF' OR style_id > 0)";
		}
		else
		{
			$skipCoreSql = '';
		}

		$templateIds = $db->fetchAllColumn($db->limit(
			"
				SELECT template_id
				FROM xf_template
				WHERE template_id > ?
					{$skipCoreSql}
				ORDER BY template_id
			",
			$this->data['batch']
		), $this->data['templateId']);
		if (!$templateIds)
		{
			/** @var StyleRepository $repo */
			$repo = $this->app->repository(StyleRepository::class);
			$repo->updateAllStylesLastModifiedDateLater();

			return $this->complete();
		}

		/** @var CompileService $compileService */
		$compileService = $app->service(CompileService::class);

		$done = 0;

		foreach ($templateIds AS $templateId)
		{
			$this->data['templateId'] = $templateId;

			/** @var Template $template */
			$template = $em->find(Template::class, $templateId);
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

			$done++;

			if (microtime(true) - $start >= $maxRunTime)
			{
				break;
			}
		}

		// decache to reduce memory usage
		\XF::em()->clearEntityCache(Template::class);
		\XF::em()->clearEntityCache(TemplateModification::class);

		$this->data['batch'] = $this->calculateOptimalBatch($this->data['batch'], $done, $start, $maxRunTime, 300);

		return $this->resume();
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('rebuilding');
		$typePhrase = \XF::phrase('templates');
		return sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat('. ', $this->data['steps']));
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
