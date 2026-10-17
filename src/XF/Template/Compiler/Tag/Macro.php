<?php

namespace XF\Template\Compiler\Tag;

use XF\Install\App;
use XF\Template\Compiler;
use XF\Template\Compiler\CodeScope;
use XF\Template\Compiler\Syntax\Str;
use XF\Template\Compiler\Syntax\Tag;

class Macro extends AbstractTag
{
	public function compile(Tag $tag, Compiler $compiler, array $context, $inlineExpected)
	{
		$tag->nameToId()->assertAttribute('id');

		$attributes = $tag->attributes;

		$rawContext = $context;
		$rawContext['escape'] = false;

		$argumentContext = $context;
		$argumentContext['forceEscapePhrase'] = true;

		$arguments = [];
		foreach ($attributes AS $attribute => $value)
		{
			if (preg_match('#^arg-([a-zA-Z0-9_-]+)$#', $attribute, $match))
			{
				if (strpos($match[1], '-') !== false)
				{
					throw $tag->exception(\XF::phrase('macro_argument_names_may_only_contain_alphanumeric_underscore'));
				}

				$arguments[$match[1]] = $value;
			}
		}

		if ($tag->children)
		{
			// defining a macro
			if (!($attributes['id'] instanceof Str))
			{
				throw $tag->exception(\XF::phrase('macro_ids_must_be_literal_strings'));
			}
			$id = $attributes['id']->content;

			if (!preg_match('#^[a-z0-9_]+$#i', $id))
			{
				// We only enforce this with development on and outside of the install/upgrade system because this
				// constraint was bugged prior to 2.2. This should avoid end users running into it or upgrades being
				// blocked because of it.
				if (\XF::$developmentMode && !(\XF::app() instanceof App))
				{
					throw $tag->exception(\XF::phrase('macro_id_x_may_only_contain_alphanumeric_underscore', ['id' => $id]));
				}
			}

			$codeParts = [];

			if (isset($attributes['extends']))
			{
				if (!($attributes['extends'] instanceof Str))
				{
					throw $tag->exception(\XF::phrase('extension_ids_must_be_literal_strings'));
				}
				$extends = $attributes['extends']->content;

				$codeParts[] = "'extends' => " . $compiler->getStringCode($extends);
			}

			$globalScope = $compiler->getCodeScope();

			$macroScope = new CodeScope($compiler->finalVarName, $compiler);
			$compiler->setCodeScope($macroScope);

			$currentMacro = $compiler->getCurrentMacro();
			$tag->extensions = [];
			$compiler->setCurrentMacro($tag);

			if ($arguments)
			{
				$arguments = $this->compileAttributesAsArray($arguments, $compiler, $argumentContext);
				$indent = $compiler->indent();
				$argumentsCode = "array(" . implode('', $arguments) . "\n$indent)";

				$codeParts[] = "'arguments' => function({$compiler->templaterVariable}, array {$compiler->variableContainer}) { return $argumentsCode; }";
			}

			$compiler->traverseBlockChildren($tag->children, $context);

			if (!empty($tag->attributes['global']))
			{
				// the presence of this attribute is now treated as the macro being global
				$codeParts[] = "'global' => true";
			}

			$macroCode = "function({$compiler->templaterVariable}, array {$compiler->variableContainer}, {$compiler->extensionsVariable} = null)
{
	{$compiler->finalVarName} = '';
" . implode("\n", $compiler->getOutput()) . "
	return {$compiler->finalVarName};
}";

			$compiler->setCodeScope($globalScope);
			$compiler->setCurrentMacro($currentMacro);

			if ($tag->extensions)
			{
				$codeParts[] = "'extensions' => array(" . implode(",\n", $tag->extensions) . ")";
			}

			$codeParts[] = "'code' => $macroCode";
			$finalMacroCode = "array(\n" . implode(",\n", $codeParts) . "\n)";

			$compiler->defineMacro($id, $finalMacroCode);

			return '';
		}
		else
		{
			// accessing a macro
			if ($arguments)
			{
				$arguments = $this->compileAttributesAsArray($arguments, $compiler, $argumentContext);
				$indent = $compiler->indent();
				$argumentsCode = "array(" . implode('', $arguments) . "\n$indent)";
			}
			else
			{
				$argumentsCode = 'array()';
			}

			if (!empty($attributes['args']))
			{
				$argsArrayCode = $compiler->compileForcedExpression($attributes['args'], $argumentContext);
				$argumentsCode = "{$compiler->templaterVariable}->combineMacroArgumentAttributes({$argsArrayCode}, {$argumentsCode})";
			}

			$id = $attributes['id']->compile($compiler, $rawContext, true);

			if (empty($attributes['template']))
			{
				$template = 'null';
			}
			else
			{
				$template = $attributes['template']->compile($compiler, $rawContext, true);
			}

			return "{$compiler->templaterVariable}->callMacro({$template}, {$id}, {$argumentsCode}, {$compiler->variableContainer})";
		}
	}
}
