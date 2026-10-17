<?php

namespace XF\AddOn;

use XF\App;
use XF\Db\AbstractStatement;
use XF\Db\Exception;
use XF\Install\InstallHelperTrait;
use XF\Job\FileCleanUp;

abstract class AbstractSetup
{
	use InstallHelperTrait;

	/**
	 * @var AddOn
	 */
	protected $addOn;

	/**
	 * @param array $stepParams
	 *
	 * @return null|StepResult
	 */
	abstract public function install(array $stepParams = []);

	/**
	 * @param array $stepParams
	 *
	 * @return null|StepResult
	 */
	abstract public function upgrade(array $stepParams = []);

	/**
	 * @param array $stepParams
	 *
	 * @return null|StepResult
	 */
	abstract public function uninstall(array $stepParams = []);

	public function __construct(AddOn $addOn, App $app)
	{
		$this->addOn = $addOn;
		$this->app = $app;
	}

	/**
	 * Perform additional requirement checks.
	 *
	 * @param array $errors Errors will block the setup from continuing
	 * @param array $warnings Warnings will be displayed but allow the user to continue setup
	 *
	 * @return void
	 */
	public function checkRequirements(&$errors = [], &$warnings = [])
	{
		return;
	}

	public function postInstall(array &$stateChanges)
	{
	}

	public function postUpgrade($previousVersion, array &$stateChanges)
	{
	}

	public function postRebuild()
	{
	}

	public function onActiveChange($newActive, array &$jobList)
	{
	}

	public function enqueuePostUpgradeCleanUp(): void
	{
		$addOn = $this->addOn;

		$uniqueId = $addOn->prepareAddOnIdForFilename() . 'FileCleanUp' . $addOn->version_id;

		$this->app->jobManager()->enqueueUnique($uniqueId, FileCleanUp::class, [
			'addon_id' => $addOn->addon_id,
		], false);
	}

	public function prepareForAction($action)
	{
		if ($action == 'uninstall')
		{
			\XF::db()->ignoreLegacyTableWriteError(true);
		}
	}

	/**
	 * @param $sql
	 * @param array $bind
	 * @param bool $suppressAll
	 *
	 * @return bool|AbstractStatement
	 * @throws Exception
	 */
	protected function query($sql, $bind = [], $suppressAll = false)
	{
		return $this->executeUpgradeQuery($sql, $bind, $suppressAll);
	}
}
