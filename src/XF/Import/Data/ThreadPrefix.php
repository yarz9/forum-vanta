<?php

namespace XF\Import\Data;

use XF\Repository\ThreadPrefixRepository;

/**
 * @mixin \XF\Entity\ThreadPrefix
 */
class ThreadPrefix extends AbstractEmulatedData
{
	protected $title = '';
	protected $description = '';
	protected $help = '';

	protected $nodeIds = [];

	public function getImportType()
	{
		return 'thread_prefix';
	}

	public function getEntityShortName()
	{
		return 'XF:ThreadPrefix';
	}

	public function setTitle(string $title)
	{
		$this->title = $title;
	}

	public function setDescription(string $description)
	{
		$this->description = $description;
	}

	public function setHelp(string $help)
	{
		$this->help = $help;
	}

	public function setNodes(array $nodeIds)
	{
		$this->nodeIds = $nodeIds;
	}

	protected function postSave($oldId, $newId)
	{
		/** @var \XF\Entity\ThreadPrefix $prefix */
		$prefix = $this->em()->find(\XF\Entity\ThreadPrefix::class, $newId);
		if ($prefix)
		{
			$this->insertMasterPhrase($prefix->getPhraseName(), $this->title);
			$this->insertMasterPhrase($prefix->getDescriptionPhraseName(), $this->description);
			$this->insertMasterPhrase($prefix->getUsageHelpPhraseName(), $this->help);

			$this->em()->detachEntity($prefix);
		}

		if ($this->nodeIds)
		{
			$insert = [];
			foreach ($this->nodeIds AS $nodeId)
			{
				$insert[] = [
					'node_id' => $nodeId,
					'prefix_id' => $newId,
				];
			}

			$this->db()->insertBulk('xf_forum_prefix', $insert, false, false, 'IGNORE');
		}

		/** @var ThreadPrefixRepository $repo */
		$repo = $this->repository(ThreadPrefixRepository::class);

		\XF::runOnce('rebuildThreadPrefixImport', function () use ($repo)
		{
			$repo->rebuildPrefixMaterializedOrder();
			$repo->rebuildPrefixCache();
		});
	}
}
