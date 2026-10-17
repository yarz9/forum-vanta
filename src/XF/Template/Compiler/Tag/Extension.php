<?php

namespace XF\Template\Compiler\Tag;

use XF\Template\Compiler;
use XF\Template\Compiler\CodeScope;
use XF\Template\Compiler\Syntax\Str;
use XF\Template\Compiler\Syntax\Tag;

class Extension extends AbstractTag
{
	public function compile(Tag $tag, Compiler $compiler, array $context, $inlineExpected)
	{
		$tag->nameToId()->assertAttribute('id');

		$attributes = $tag->attributes;

		if (!($attributes['id'] instanceof Str))
		{
			throw $tag->exception(\XF::phrase('extension_ids_must_be_literal_strings'));
		}
		$id = $attributes['id']->content;

		if (!preg_match('#^[a-z0-9_]+$#i', $id))
		{
			throw $tag->exception(\XF::phrase('extension_ids_may_only_contain_alphanumeric_underscore'));
		}

		$skipPrint = false;

		if (isset($tag->attributes['value']))
		{
			$tag->assertEmpty();

			$context['escape'] = false;
			$value = $tag->attributes['value']->compile($compiler, $context, true);

			$extensionCode = "return {$value};";

			// if the extension is being defined this way, assume it's for message passing or non-printed usage
			$skipPrint = true;
		}
		else
		{
			$globalScope = $compiler->getCodeScope();

			$extensionScope = new CodeScope($compiler->finalVarName, $compiler);
			$compiler->setCodeScope($extensionScope);

			$compiler->traverseBlockChildren($tag->children, $context);

			$extensionCode = "{$compiler->finalVarName} = '';
	" . implode("\n", $compiler->getOutput()) . "
	return {$compiler->finalVarName};";

			$compiler->setCodeScope($globalScope);
		}

		$compiler->defineExtension($id, $extensionCode, $tag);

		if (isset($tag->attributes['skipprint']))
		{
			$skipPrint = (
				$tag->attributes['skipprint'] instanceof Str
				&& strtolower($tag->attributes['skipprint']->content) == 'true'
			);
		}

		if ($skipPrint)
		{
			return $inlineExpected ? "''" : false;
		}
		else
		{
			$idCode = $compiler->getStringCode($id);

			return "{$compiler->templaterVariable}->renderExtension({$idCode}, {$compiler->variableContainer}, {$compiler->extensionsVariable})";
		}
	}
}
