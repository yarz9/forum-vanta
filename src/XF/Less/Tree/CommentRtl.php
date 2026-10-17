<?php

namespace XF\Less\Tree;

use Less_Tree_Comment as Comment;

class CommentRtl extends Comment
{
	/**
	 * @var string
	 */
	public $rtlMode = 'enable';

	public function toCSS(): string
	{
		return $this->value;
	}

	public function isSilent(): bool
	{
		$isReference = (
			$this->currentFileInfo &&
			isset($this->currentFileInfo['reference']) &&
			(!isset($this->isReferenced) || !$this->isReferenced)
		);
		return $this->silent || $isReference;
	}

	public static function cloneFrom(Comment $comment, string $rtlMode): self
	{
		$new = new self($comment->value, false, null, $comment->currentFileInfo);
		$new->isReferenced = $comment->isReferenced;
		$new->rtlMode = $rtlMode;

		return $new;
	}
}
