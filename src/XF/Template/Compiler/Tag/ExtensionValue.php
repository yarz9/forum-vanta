<?php

namespace XF\Template\Compiler\Tag;

use XF\Template\Compiler;
use XF\Template\Compiler\Syntax\Tag;

class ExtensionValue extends AbstractTag
{
	public function compile(Tag $tag, Compiler $compiler, array $context, $inlineExpected)
	{
		$tag->nameToId()->assertAttribute('id')->assertEmpty();

		$context['escape'] = false;
		$idCode = $tag->attributes['id']->compile($compiler, $context, true);

		return "{$compiler->templaterVariable}->renderExtension({$idCode}, {$compiler->variableContainer}, {$compiler->extensionsVariable})";
	}
}
