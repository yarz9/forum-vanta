<?php

namespace XF\Search;

use function intval, strval;

class IndexRecord
{
	/**
	 * @var string
	 */
	public $type;

	/**
	 * @var int
	 */
	public $id;

	/**
	 * @var string
	 */
	public $title;

	/**
	 * @var string
	 */
	public $originalTitle;

	/**
	 * @var string
	 */
	public $message;

	/**
	 * @var int
	 */
	public $date;

	/**
	 * @var int
	 */
	public $userId = 0;

	/**
	 * @var int
	 */
	public $discussionId = 0;

	/**
	 * @var array<string, mixed>
	 */
	public $metadata = [];

	/**
	 * @var bool
	 */
	public $hidden = false;

	/**
	 * @param string $type
	 * @param int $id
	 * @param string $title
	 * @param string $message
	 * @param int|null $date,
	 * @param int $userId
	 * @param int $discussionId
	 * @param array<string, mixed> $metadata
	 */
	public function __construct($type, $id, $title, $message, $date = null, $userId = 0, $discussionId = 0, array $metadata = [])
	{
		$this->type = strval($type);
		$this->id = intval($id);
		$this->title = strval($title);
		$this->originalTitle = strval($title);
		$this->message = strval($message);
		$date = $date === null ? \XF::$time : intval($date);
		$this->date = $date;
		$this->userId = intval($userId);
		$this->discussionId = intval($discussionId);
		$this->metadata = $metadata;
	}

	/**
	 * @param string $type
	 * @param int $id
	 * @param array<string, string|int|array<string, mixed>|bool> $data
	 *
	 * @return self
	 */
	public static function create($type, $id, array $data)
	{
		$data = array_merge([
			'title' => '',
			'message' => '',
			'date' => \XF::$time,
			'user_id' => 0,
			'discussion_id' => 0,
			'metadata' => [],
			'hidden' => false,
		], $data);

		$index = new self(
			$type,
			$id,
			$data['title'],
			$data['message'],
			$data['date'],
			$data['user_id'],
			$data['discussion_id'],
			$data['metadata']
		);
		if ($data['hidden'])
		{
			$index->setHidden();
		}

		return $index;
	}

	public function setHidden()
	{
		$this->hidden = true;
	}

	/**
	 * @param array<int, string> $tags
	 * @param bool $withMetadata
	 */
	public function indexTags(array $tags, $withMetadata = true)
	{
		if ($tags)
		{
			$tagIds = [];
			$title = '';
			foreach ($tags AS $tagId => $tag)
			{
				$title .= " $tag[tag]";
				$tagIds[] = $tagId;
			}

			$this->title .= $title;
			if ($withMetadata)
			{
				$this->metadata['tag'] = $tagIds;
			}
		}
	}
}
