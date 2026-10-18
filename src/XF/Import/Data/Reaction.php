<?php

namespace XF\Import\Data;

use XF\Repository\ReactionRepository;
use XF\Util\File;

/**
 * @mixin \XF\Entity\Reaction
 */
class Reaction extends AbstractEmulatedData
{
	protected $title;

	protected $sourceFile;
	protected $filename;

	public function getImportType()
	{
		return 'reaction';
	}

	public function getEntityShortName()
	{
		return 'XF:Reaction';
	}

	public function setTitle($title)
	{
		$this->title = $title;
	}

	public function setSourceImagePath($sourceFile, $filename)
	{
		$this->sourceFile = $sourceFile;
		$this->filename = $filename;

		$this->image_url = "data/imported_reactions/$filename";
	}

	protected function preSave($oldId)
	{
		if (!$this->emoji_shortname && !$this->image_url)
		{
			$this->image_url = 'styles/default/xenforo/missing-image.png';
		}
	}

	protected function postSave($oldId, $newId)
	{
		$this->insertMasterPhrase('reaction_title.' . $newId, $this->title);

		if ($this->sourceFile)
		{
			$image = $this->app()->imageManager()->imageFromFile($this->sourceFile);
			$image->resizeAndCrop(32, 32);

			$newTempFile = File::getTempFile();
			if ($newTempFile && $image->save($newTempFile))
			{
				File::copyFileToAbstractedPath($newTempFile, 'data://imported_reactions/' . $this->filename);
			}
		}

		/** @var ReactionRepository $repo */
		$repo = $this->repository(ReactionRepository::class);

		\XF::runOnce('rebuildReactionImport', function () use ($repo)
		{
			$repo->rebuildReactionCache();
			$repo->rebuildReactionSpriteCache();
		});
	}
}
