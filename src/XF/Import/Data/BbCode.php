<?php

namespace XF\Import\Data;

use XF\Repository\BbCodeRepository;

/**
 * @mixin \XF\Entity\BbCode
 */
class BbCode extends AbstractEmulatedData
{
	protected $title = '';
	protected $example = '';

	public function getImportType()
	{
		return 'bb_code';
	}

	public function getEntityShortName()
	{
		return 'XF:BbCode';
	}

	public function setTitle($title)
	{
		$this->title = $title;
	}

	public function setExample($example)
	{
		$this->example = $example;
	}

	protected function postSave($oldId, $newId)
	{
		/** @var \XF\Entity\BbCode $bbCode */
		$bbCode = $this->em()->find(\XF\Entity\BbCode::class, $newId);
		if ($bbCode)
		{
			$this->insertMasterPhrase($bbCode->getPhraseName(), $this->title);
			$this->insertMasterPhrase($bbCode->getPhraseName('example'), $this->example);

			$this->em()->detachEntity($bbCode);
		}

		/** @var BbCodeRepository $repo */
		$repo = $this->repository(BbCodeRepository::class);

		\XF::runOnce('rebuildBbCodeCache', function () use ($repo)
		{
			$repo->rebuildBbCodeCache();
		});
	}
}
