<?php

namespace XF\Service\Bookmark;

use XF\App;
use XF\Entity\BookmarkItem;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class EditorService extends AbstractService
{
	use ValidateAndSavableTrait;

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

	public function __construct(App $app, BookmarkItem $bookmark)
	{
		parent::__construct($app);

		$this->bookmark = $bookmark;
		$this->preparer = $this->service(PreparerService::class, $this->bookmark);
		$this->labelChanger = $this->service(LabelChangerService::class, $this->bookmark, $this->bookmark->User);
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

		$bookmark->save();
		$this->preparer->afterUpdate();
		$this->labelChanger->save();

		$this->db()->commit();

		return $bookmark;
	}
}
