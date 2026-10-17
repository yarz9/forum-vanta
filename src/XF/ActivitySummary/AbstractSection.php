<?php

namespace XF\ActivitySummary;

use XF\App;
use XF\Db\AbstractAdapter;
use XF\Entity\ActivitySummaryDefinition;
use XF\Entity\ActivitySummarySection;
use XF\Http\Request;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Manager;
use XF\Repository\ActivitySummaryRepository;

use function call_user_func_array, func_get_args, is_array, is_object;

abstract class AbstractSection
{
	/**
	 * @var App
	 */
	protected $app;

	/**
	 * @var ActivitySummarySection
	 */
	protected $section;

	protected $options;
	protected $defaultOptions = [];

	protected $fetchedData;

	protected $ids;
	protected $total = 0;

	abstract protected function getBaseFinderForFetch(): Finder;
	abstract protected function findDataForFetch(Finder $finder): Finder;
	abstract protected function renderInternal(Instance $instance): string;

	public function __construct(App $app, ActivitySummarySection $section)
	{
		$this->app = $app;
		$this->section = $section;
		$this->options = $this->setupOptions($section->options);
	}

	public function render(Instance $instance): string
	{
		$user = $instance->getUser();

		return \XF::asVisitor($user, function () use ($instance)
		{
			return trim($this->renderInternal($instance));
		});
	}

	public function fetchData()
	{
		$this->cacheDataIfNeeded();

		$data = $this->fetchedData;
		if (is_object($data))
		{
			// clone the collection so that we do not unset entries for subsequent runs
			$data = clone $data;
		}

		return $data;
	}

	public function cacheDataIfNeeded()
	{
		if ($this->fetchedData === null)
		{
			$finder = $this->getBaseFinderForFetch();

			if (is_array($this->ids))
			{
				$finder->whereIds($this->ids);
			}
			else
			{
				$finder = $this->findDataForFetch($finder);
			}

			$data = $finder->fetch();

			$this->ids = $data->keys();
			$this->total = $finder->total();

			$this->fetchedData = $data;
		}
	}

	public function getTotal(Instance $instance): int
	{
		$this->cacheDataIfNeeded();

		return $this->total;
	}

	public function getDataForJob(): array
	{
		return [
			'ids' => $this->ids,
			'total' => $this->total,
		];
	}

	public function setupDataFromJob(array $jobData)
	{
		$this->ids = $jobData['ids'];
		$this->total = $jobData['total'];

		$this->fetchedData = null; // decache, just in case
	}

	protected function setupOptions(array $options): array
	{
		return array_replace($this->defaultOptions, $options);
	}

	public function getActivityCutOff()
	{
		/** @var ActivitySummaryRepository $repo */
		$repo = $this->repository(ActivitySummaryRepository::class);
		return $repo->getMinLastActivityCutOff();
	}

	public function renderOptions(): string
	{
		$templateName = $this->getOptionsTemplate();
		if (!$templateName)
		{
			return '';
		}

		return $this->app->templater()->renderTemplate(
			$templateName,
			$this->getDefaultTemplateParams('options')
		);
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate()
	{
		return 'admin:activity_summary_options_' . $this->section->definition_id;
	}

	public function verifyOptions(Request $request, array &$options, &$error = null)
	{
		return true;
	}

	public function getSection()
	{
		return $this->section;
	}

	public function getTitle()
	{
		return $this->section->title;
	}

	public function getDefaultTitle(ActivitySummaryDefinition $definition)
	{
		return $definition->title;
	}

	protected function renderSectionTemplate(Instance $instance, $templateName, array $viewParams = [])
	{
		$viewParams = array_replace($this->getDefaultTemplateParams('render'), $viewParams);

		$user = $instance->getUser();
		$language = $this->app()->userLanguage($user);

		$mailer = $this->app->mailer();
		return $mailer->renderPartialMailTemplate($templateName, $viewParams, $language, $user);
	}

	protected function getDefaultTemplateParams($context)
	{
		$section = $this->section;
		return [
			'title' => $this->getTitle(),
			'section' => $section,
			'options' => $this->options,
		];
	}

	/**
	 * @return App
	 */
	public function app()
	{
		return $this->app;
	}

	/**
	 * @return AbstractAdapter
	 */
	public function db()
	{
		return $this->app->db();
	}

	/**
	 * @return Manager
	 */
	public function em()
	{
		return $this->app->em();
	}

	/**
	 * @template T of \XF\Mvc\Entity\Repository
	 *
	 * @param class-string<T> $repository
	 *
	 * @return T
	 */
	public function repository($repository)
	{
		return $this->app->repository($repository);
	}

	/**
	 * @template T of Finder
	 *
	 * @param class-string<T> $finder
	 *
	 * @return T
	 */
	public function finder($finder)
	{
		return $this->app->finder($finder);
	}

	/**
	 * @template T of \XF\Mvc\Entity\Entity
	 *
	 * @param class-string<T> $finder
	 * @param array $where
	 * @param array|string|null $with
	 *
	 * @return T|null
	 */
	public function findOne($finder, array $where, $with = null)
	{
		return $this->app->em()->findOne($finder, $where, $with);
	}

	/**
	 * @template T of \XF\Service\AbstractService
	 *
	 * @param class-string<T> $class
	 * @param mixed ...$arguments
	 *
	 * @return T
	 */
	public function service($class)
	{
		return call_user_func_array([$this->app, 'service'], func_get_args());
	}
}
