<?php

namespace XF\Template\Compiler\Tag;

use XF\Template\Compiler;
use XF\Template\Compiler\Syntax\Tag;

class ExtensionParent extends AbstractTag
{
	public function compile(Tag $tag, Compiler $compiler, array $context, $inlineExpected)
	{
		$tag->nameToId()->assertEmpty();

		if (isset($tag->attributes['id']))
		{
			$rawContext = $context;
			$context['escape'] = false;

			$id = $tag->attributes['id']->compile($compiler, $rawContext, true);
		}
		else
		{
			$id = 'null';
		}

		$varContainer = $compiler->variableContainer;
		return "{$compiler->templaterVariable}->renderExtensionParent({$varContainer}, {$id}, {$compiler->extensionsVariable})";
	}
}
