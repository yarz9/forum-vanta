<?php

namespace XF\Install\Controller;

use XF\Db\Exception;
use XF\Db\Mysqli\Adapter;
use XF\Install\Helper;
use XF\Install\Upgrader;
use XF\Job\JobResult;
use XF\Mvc\Controller;
use XF\Mvc\ParameterBag;
use XF\Phrase;

abstract class AbstractController extends Controller
{
	protected function preDispatchType($action, ParameterBag $params)
	{
		$this->preDispatchController($action, $params);
	}

	protected function preDispatchController($action, ParameterBag $params)
	{
	}

	protected function manualJobRunner($submitUrl, $redirect)
	{
		$app = $this->app;
		$status = '';

		if ($this->filter('execute', 'bool') && $this->isPost())
		{
			$job = $app->jobManager()->runUnique(
				$this->getInstallHelper()->getDefaultRebuildJobName(),
				$app->config('jobMaxRunTime')
			);
			if (!$job || $job->result === JobResult::RESULT_COMPLETED)
			{
				return $this->redirect($redirect);
			}
			$status = $job->statusMessage;
		}

		$viewParams = [
			'status' => $status,
			'redirect' => $redirect,
			'submitUrl' => $submitUrl,
		];
		return $this->view('XF:Install\RunJob', 'run_job', $viewParams);
	}

	public function actionConfigDownload()
	{
		$this->assertPostOnly();

		$config = $this->filter('config', 'json-array');

		$this->setResponseType('raw');

		$viewParams = [
			'generated' => $this->getInstallHelper()->generateConfig($config),
		];
		return $this->view('XF:Install\ConfigDownload', '', $viewParams);
	}

	protected function testConfig(array $config, &$error)
	{
		try
		{
			$db = new Adapter($config['db'], $config['fullUnicode']);
			$db->connect();

			$this->getInstallHelper()->hasApplicationTables($db);
			$error = null;
		}
		catch (Exception $e)
		{
			$db = null;
			$error = \XF::phrase('following_error_occurred_while_connecting_database', ['error' => $e->getMessage()]);
		}

		return $db;
	}

	public function noPermission($message = null)
	{
		if ($message instanceof Phrase
			&& preg_match('/_not_found$/', $message->getName())
		)
		{
			// phrase coming from an error that looks like a 404 error so trigger it as such
			return $this->notFound($message);
		}

		if (!$message)
		{
			$message = \XF::phrase('do_not_have_permission');
		}

		return $this->error($message, 403);
	}

	public function notFound($message = null)
	{
		if (!$message)
		{
			$message = \XF::phrase('requested_page_not_found');
		}

		return $this->error($message, 404);
	}

	public function assertCorrectVersion($action)
	{
	}

	/**
	 * @return Helper
	 */
	protected function getInstallHelper()
	{
		return new Helper($this->app);
	}

	/**
	 * @return Upgrader
	 */
	protected function getUpgrader()
	{
		return new Upgrader($this->app);
	}
}
