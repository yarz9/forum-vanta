<?php

namespace XF\Less\Visitor;

use Less_Tree as Tree;
use Less_Tree_Color as Color;
use Less_Tree_Ruleset as Ruleset;
use Less_VisitorReplacing as VisitorReplacing;
use XF\Less\Tree\HslColor;

class HslColorPreVisitor extends VisitorReplacing
{
	/**
	 * @var bool
	 */
	public $isPreVisitor = true;

	public function run(Ruleset $root): Tree
	{
		return $this->visitObj($root);
	}

	public function visitColor(Color $color): HslColor
	{
		return HslColor::fromColor($color);
	}
}
