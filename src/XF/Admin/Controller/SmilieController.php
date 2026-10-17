<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\XmlPlugin;
use XF\Entity\Smilie;
use XF\Finder\SmilieFinder;
use XF\Mvc\ParameterBag;
use XF\Repository\SmilieCategoryRepository;
use XF\Repository\SmilieRepository;
use XF\Service\Smilie\ImportService;
use XF\Util\Xml;

use function is_array;

class SmilieController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('bbCodeSmilie');
	}

	public function actionIndex()
	{
		$smilieData = $this->getSmilieRepo()->getSmilieListData();

		$viewParams = [
			'smilieData' => $smilieData,
			'exportView' => $this->filter('export', 'bool'),
		];
		return $this->view('XF:Smilie\Listing', 'smilie_list', $viewParams);
	}

	public function smilieAddEdit(Smilie $smilie)
	{
		$viewParams = [
			'smilie' => $smilie,
			'smilieCategories' => $this->getSmilieCategoryRepo()->getSmilieCategoryTitlePairs(),
		];
		return $this->view('XF:Smilie\Edit', 'smilie_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$smilie = $this->assertSmilieExists($params['smilie_id']);
		return $this->smilieAddEdit($smilie);
	}

	public function actionAdd()
	{
		$smilie = $this->em()->create(Smilie::class);

		return $this->smilieAddEdit($smilie);
	}

	protected function smilieSaveProcess(Smilie $smilie)
	{
		$entityInput = $this->filter([
			'title' => 'str',
			'smilie_text' => 'str',
			'image_url' => 'str',
			'image_url_2x' => 'str',
			'emoji_shortname' => 'str',
			'sprite_mode' => 'uint',
			'sprite_params' => 'array',
			'smilie_category_id' => 'uint',
			'display_order' => 'uint',
			'display_in_editor' => 'uint',
		]);

		// If not in sprite mode, don't update the sprite params values. This can prevent a tedious
		// bit of data loss if the option is accidentally unselected.
		if (!$entityInput['sprite_mode'])
		{
			unset($entityInput['sprite_params']);
		}

		$form = $this->formAction();
		$form->basicEntitySave($smilie, $entityInput);

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params['smilie_id'])
		{
			$smilie = $this->assertSmilieExists($params['smilie_id']);
		}
		else
		{
			$smilie = $this->em()->create(Smilie::class);
		}

		$this->smilieSaveProcess($smilie)->run();

		return $this->redirect($this->buildLink('smilies'));
	}

	public function actionDelete(ParameterBag $params)
	{
		$smilie = $this->assertSmilieExists($params['smilie_id']);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$smilie,
			$this->buildLink('smilies/delete', $smilie),
			$this->buildLink('smilies/edit', $smilie),
			$this->buildLink('smilies'),
			$smilie->title
		);
	}

	public function actionExport()
	{
		$smilies = $this->finder(SmilieFinder::class)
			->where('smilie_id', $this->filter('export', 'array-str'))
			->order(['Category.display_order', 'display_order', 'title']);

		return $this->plugin(XmlPlugin::class)->actionExport($smilies, 'XF:Smilie\Export');
	}

	public function actionImport()
	{
		if ($this->isPost())
		{
			$input = $this->filterFormJson([
				'categories' => 'array',
				'import' => 'array-int',
				'smilies' => 'array',
			]);

			$smilies = [];

			foreach ($input['import'] AS $smilieId)
			{
				if (empty($input['smilies'][$smilieId]) || !is_array($input['smilies'][$smilieId]))
				{
					continue;
				}

				$smilies[$smilieId] = $this->filterSmilieImportInput($input['smilies'][$smilieId]);
			}

			/** @var ImportService $smilieImporter */
			$smilieImporter = $this->service(ImportService::class);
			$smilieImporter->importSmilies($smilies, $input['categories'], $errors);

			if (empty($errors))
			{
				return $this->redirect($this->buildLink('smilies'));
			}
			else
			{
				return $this->error($errors);
			}
		}
		else
		{
			$viewParams = [
				'smilieCategories' => $this->getSmilieCategoryRepo()->getSmilieCategoryTitlePairs(),
				'smilieXmlFiles' => $this->getSmilieRepo()->getSmilieImportXmlFiles(),
			];
			return $this->view('XF:Smilie\Import', 'smilie_import', $viewParams);
		}
	}

	protected function filterSmilieImportInput(array $smilieInput)
	{
		return $this->filterArray($smilieInput, [
			'title' => 'str',
			'smilie_text' => 'str',
			'image_url' => 'str',
			'image_url_2x' => 'str',
			'emoji_shortname' => 'str',
			'sprite_mode' => 'uint',
			'sprite_params' => 'array',
			'smilie_category_id' => 'str',
			'display_order' => 'uint',
			'display_in_editor' => 'uint',
		]);
	}

	public function actionImportForm()
	{
		$this->assertPostOnly();

		$input = $this->filter([
			'mode' => 'str',
			'directory' => 'str',
		]);

		/** @var ImportService $smilieImporter */
		$smilieImporter = $this->service(ImportService::class);

		if ($input['mode'] == 'directory')
		{
			$directory = $this->filter('directory', 'str');

			$smilieData = $smilieImporter->getSmilieDataFromDirectory($directory);
		}
		else
		{
			if ($input['mode'] == 'upload')
			{
				$upload = $this->request->getFile('upload', false);
				if (!$upload)
				{
					return $this->error(\XF::phrase('please_upload_valid_smilies_xml_file'));
				}

				try
				{
					$xml = Xml::openFile($upload->getTempFile());
				}
				catch (\Exception $e)
				{
					$xml = null;
				}
			}
			else
			{
				$xml = Xml::open($this->app()->fs()->read(
					$this->getSmilieRepo()->getAbstractedImportedXmlFilePath($this->filter('filename', 'str'))
				));
			}

			if (!$xml || $xml->getName() != 'smilies_export')
			{
				return $this->error(\XF::phrase('please_upload_valid_smilies_xml_file'));
			}

			$smilieData = $smilieImporter->getSmilieDataFromXml($xml);
		}

		$viewParams = [
			'uploadMode' => ($input['mode'] == 'upload'),
			'smilies' => $smilieData['smilies'],
			'smilieCategoryMap' => $smilieData['smilieCategoryMap'],
			'newCategories' => $smilieData['categories'],
			'newCategoryPairs' => $smilieData['categoryPairs'],
			'categoryPairs' => $this->getSmilieCategoryRepo()->getSmilieCategoryTitlePairs(),
		];
		return $this->view('XF:Smilie\ImportForm', 'smilie_import_form', $viewParams);
	}

	public function actionSort(ParameterBag $params)
	{
		if ($this->isPost())
		{
			$smilies = $this->finder(SmilieFinder::class)->fetch();

			foreach ($this->filter('smilies', 'array-json-array') AS $smiliesInCategory)
			{
				$lastOrder = 0;
				foreach ($smiliesInCategory AS $key => $smilieValue)
				{
					if (!isset($smilieValue['id']) || !isset($smilies[$smilieValue['id']]))
					{
						continue;
					}

					$lastOrder += 10;

					/** @var Smilie $smilie */
					$smilie = $smilies[$smilieValue['id']];
					$smilie->smilie_category_id = $smilieValue['parent_id'];
					$smilie->display_order = $lastOrder;
					$smilie->saveIfChanged();
				}
			}

			return $this->redirect($this->buildLink('smilies'));
		}
		else
		{
			$smilieData = $this->getSmilieRepo()->getSmilieListData();

			$viewParams = [
				'smilieData' => $smilieData,
			];
			return $this->view('XF:Smilie\Sort', 'smilie_sort', $viewParams);
		}
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return Smilie
	 */
	protected function assertSmilieExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(Smilie::class, $id, $with, $phraseKey);
	}

	/**
	 * @return SmilieRepository
	 */
	protected function getSmilieRepo()
	{
		return $this->repository(SmilieRepository::class);
	}

	/**
	 * @return SmilieCategoryRepository
	 */
	protected function getSmilieCategoryRepo()
	{
		return $this->repository(SmilieCategoryRepository::class);
	}
}
