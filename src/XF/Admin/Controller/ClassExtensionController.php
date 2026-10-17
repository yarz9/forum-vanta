<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\ClassExtension;
use XF\Finder\ClassExtensionFinder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Repository\AddOnRepository;
use XF\Repository\ClassExtensionRepository;

use function count;

class ClassExtensionController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertDevelopmentMode();
	}

	public function actionIndex()
	{
		/** @var ClassExtensionRepository $extensionRepo */
		$extensionRepo = $this->getExtensionRepo();
		$extensions = $extensionRepo->findExtensionsForList()->fetch();

		/** @var AddOnRepository $addOnRepo */
		$addOnRepo = $this->repository(AddOnRepository::class);
		$addOns = $addOnRepo->findAddOnsForList()->fetch();

		$viewParams = [
			'extensions' => $extensions->groupBy('addon_id'),
			'addOns' => $addOns,
			'totalExtensions' => count($extensions),
		];
		return $this->view('XF:ClassExtension\Listing', 'class_extension_list', $viewParams);
	}

	protected function extensionAddEdit(ClassExtension $extension)
	{
		$viewParams = [
			'extension' => $extension,
		];
		return $this->view('XF:ClassExtension\Edit', 'class_extension_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$extension = $this->assertExtensionExists($params['extension_id']);
		return $this->extensionAddEdit($extension);
	}

	public function actionAdd()
	{
		$extension = $this->em()->create(ClassExtension::class);
		return $this->extensionAddEdit($extension);
	}

	protected function extensionSaveProcess(ClassExtension $extension)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'from_class' => 'str',
			'to_class' => 'str',
			'execute_order' => 'uint',
			'active' => 'bool',
			'addon_id' => 'str',
		]);
		$form->basicEntitySave($extension, $input);

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params['extension_id'])
		{
			$extension = $this->assertExtensionExists($params['extension_id']);
		}
		else
		{
			$extension = $this->em()->create(ClassExtension::class);
		}

		$this->extensionSaveProcess($extension)->run();

		return $this->redirect($this->buildLink('class-extensions') . $this->buildLinkHash($extension->extension_id));
	}

	public function actionDelete(ParameterBag $params)
	{
		$extension = $this->assertExtensionExists($params['extension_id']);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$extension,
			$this->buildLink('class-extensions/delete', $extension),
			$this->buildLink('class-extensions/edit', $extension),
			$this->buildLink('class-extensions'),
			$extension->to_class
		);
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(ClassExtensionFinder::class);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return ClassExtension
	 */
	protected function assertExtensionExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(ClassExtension::class, $id, $with, $phraseKey);
	}

	/**
	 * @return ClassExtensionRepository
	 */
	protected function getExtensionRepo()
	{
		return $this->repository(ClassExtensionRepository::class);
	}
}
