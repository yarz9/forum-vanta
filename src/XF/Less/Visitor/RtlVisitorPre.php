<?php

namespace XF\Less\Visitor;

use Less_Tree as Tree;
use Less_Tree_Comment as Comment;
use Less_Tree_Ruleset as Ruleset;
use Less_VisitorReplacing as VisitorReplacing;
use XF\Less\Tree\CommentRtl;

class RtlVisitorPre extends VisitorReplacing
{
	/**
	 * @var bool
	 */
	public $isPreVisitor = true;

	public function run(Ruleset $root): Tree
	{
		return $this->visitObj($root);
	}

	public function visitComment(Comment $comment): ?Comment
	{
		if (preg_match(
			'#/\*\s*XF-RTL:\s*(enable|disable)\s*\*/#',
			$comment->value,
			$match
		))
		{
			$mode = $match[1];
		}
		else if (preg_match(
			'#//\s*XF-RTL:\s*(enable|disable)(\s|$)#',
			$comment->value,
			$match
		))
		{
			$mode = $match[1];
		}
		else
		{
			return $comment;
		}

		return CommentRtl::cloneFrom($comment, $mode);
	}
}
