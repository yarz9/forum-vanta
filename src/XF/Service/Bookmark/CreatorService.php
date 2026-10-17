<?php

namespace XF\Service\Bookmark;

use XF\App;
use XF\Db\DuplicateKeyException;
use XF\Entity\BookmarkItem;
use XF\Entity\BookmarkTrait;
use XF\Mvc\Entity\Entity;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class CreatorService extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var Entity|BookmarkTrait
	 */
	protected $content;

	/**
	 * @var BookmarkItem
	 */
	protected $bookmark;

	/**
	 * @var PreparerService
	 */
	protected $preparer;

	/**
	 * @var LabelChangerService
	 */
	protected $labelChanger;

	public function __construct(App $app, Entity $content)
	{
		parent::__construct($app);

		$this->content = $content;

		$this->setupBookmark();

		$this->preparer = $this->service(PreparerService::class, $this->bookmark);
		$this->labelChanger = $this->service(LabelChangerService::class, $this->bookmark, $this->bookmark->User);
	}

	protected function setupBookmark()
	{
		$visitor = \XF::visitor();
		$bookmark = $this->content->getNewBookmark();
		$bookmark->user_id = $visitor->user_id;

		$this->bookmark = $bookmark;
	}

	public function getContent()
	{
		return $this->content;
	}

	public function getBookmark()
	{
		return $this->bookmark;
	}

	public function getBookmarkPreparer()
	{
		return $this->preparer;
	}

	public function setMessage($message, $format = true)
	{
		return $this->preparer->setMessage($message, $format);
	}

	public function setLabels($labels)
	{
		$this->labelChanger->setLabels($labels);
	}

	protected function finalSetup()
	{
	}

	protected function _validate()
	{
		$this->finalSetup();

		$this->bookmark->preSave();
		return $this->bookmark->getErrors();
	}

	protected function _save()
	{
		$bookmark = $this->bookmark;

		$this->db()->beginTransaction();

		try
		{
			$saved = $bookmark->save();
		}
		catch (DuplicateKeyException $e)
		{
			$saved = false;

			$dupe = $this->em()->findOne(BookmarkItem::class, [
				'user_id' => $bookmark->user_id,
				'content_type' => $bookmark->content_type,
				'content_id' => $bookmark->content_id,
			]);
			if ($dupe)
			{
				$bookmark = $dupe;
			}
		}

		if ($saved)
		{
			$this->preparer->afterInsert();
			$this->labelChanger->save();
		}

		$this->db()->commit();

		return $bookmark;
	}
}
