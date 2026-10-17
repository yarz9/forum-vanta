<?php

namespace XF\Less\Visitor;

use Less_Tree as Tree;
use Less_Tree_Call as Call;
use Less_Tree_Color as Color;
use Less_Tree_Mixin_Call as MixinCall;
use Less_Tree_Ruleset as Ruleset;
use Less_VisitorReplacing as VisitorReplacing;
use XF\Less\Tree\HslClamp;
use XF\Less\Tree\HslColor;
use XF\Less\Tree\HslColorFunction;
use XF\Less\Tree\HslColorVariable;
use XF\Less\Tree\HslMixinCall;

class HslColorPreEvalVisitor extends VisitorReplacing
{
	/**
	 * @var bool
	 */
	public $isPreEvalVisitor = true;

	public function run(Ruleset $root): Tree
	{
		return $this->visitObj($root);
	}

	public function visitCall(Call $call): Tree
	{
		if ($call->name === 'clamp')
		{
			return HslClamp::fromCall($call);
		}

		if ($call->name === 'hsl')
		{
			return HslColor::fromCall($call);
		}

		if ($call->name === 'var')
		{
			return HslColorVariable::fromCall($call);
		}

		if (HslColorFunction::isValidFunction($call->name))
		{
			return HslColorFunction::fromCall($call);
		}

		return $call;
	}

	public function visitColor(Color $color): HslColor
	{
		return HslColor::fromColor($color);
	}

	public function visitMixinCall(MixinCall $mixinCall): HslMixinCall
	{
		return HslMixinCall::fromMixinCall($mixinCall);
	}
}
