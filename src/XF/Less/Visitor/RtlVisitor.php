<?php

namespace XF\Less\Visitor;

use Less_Tree as Tree;
use Less_Tree_Anonymous as Anonymous;
use Less_Tree_Comment as Comment;
use Less_Tree_Expression as Expression;
use Less_Tree_Keyword as Keyword;
use Less_Tree_NameValue as NameValue;
use Less_Tree_Rule as Rule;
use Less_Tree_Ruleset as Ruleset;
use Less_VisitorReplacing as VisitorReplacing;
use XF\Less\Tree\CommentRtl;

use function count, is_array, strlen;

class RtlVisitor extends VisitorReplacing
{
	/**
	 * @var array<string, bool>
	 */
	protected $shorthandProperties = [
		'margin' => true,
		'padding' => true,
		'border-color' => true,
		'border-width' => true,
		'border-radius' => true,
		'border-style' => true,
	];

	/**
	 * @var array<string, bool>
	 */
	protected $keywordProperties = [
		'float' => true,
		'text-align' => true,
		'clear' => true,
	];

	/**
	 * @var bool
	 */
	protected $doReverseKeywords = false;

	/**
	 * @var ''|'edge'|'corner'
	 */
	protected $doReorderShorthand = '';

	/**
	 * @var int
	 */
	protected $disabledCount = 0;

	/**
	 * @var bool
	 */
	protected $isRtl = false;

	public function __construct(bool $isRtl)
	{
		parent::__construct();

		$this->isRtl = $isRtl;
	}

	public function run(Ruleset $root): Tree
	{
		return $this->visitObj($root);
	}

	public function isRtl(): bool
	{
		return $this->isRtl;
	}

	public function visitComment(Comment $comment): ?Comment
	{
		if ($comment instanceof CommentRtl)
		{
			if ($comment->rtlMode == 'enable')
			{
				if ($this->disabledCount > 0)
				{
					$this->disabledCount--;
				}
			}
			else
			{
				$this->disabledCount++;
			}

			return null;
		}
		else
		{
			return $comment;
		}
	}

	public function visitRule(Rule $ruleNode): ?Rule
	{
		if ($ruleNode->variable || $this->disabledCount)
		{
			return $ruleNode;
		}

		$nodeName = $this->processNodeName($ruleNode->name);
		if (!$nodeName)
		{
			return null;
		}

		if ($nodeName != $ruleNode->name)
		{
			return new Rule(
				$nodeName,
				$ruleNode->value,
				$ruleNode->important,
				$ruleNode->merge,
				$ruleNode->index,
				$ruleNode->currentFileInfo,
				$ruleNode->inline
			);
		}
		else
		{
			return $ruleNode;
		}
	}

	public function visitRuleOut(): void
	{
		$this->resetForNewRule();
	}

	public function visitNameValue(NameValue $nameValue): ?NameValue
	{
		if ($this->disabledCount)
		{
			return $nameValue;
		}

		$nodeName = $this->processNodeName($nameValue->name);
		if (!$nodeName)
		{
			return null;
		}

		$value = $nameValue->value;
		if (substr($value, -11) == ' !important')
		{
			$value = substr($value, 0, -11);
			$important = ' !important';
		}
		else
		{
			$important = '';
		}

		$reversed = $this->reverseKeyword($value);

		if ($nodeName != $nameValue->name || $reversed)
		{
			return new NameValue(
				$nodeName,
				($reversed ?: $value) . $important,
				$nameValue->index,
				$nameValue->currentFileInfo
			);
		}
		else
		{
			return $nameValue;
		}
	}

	public function visitNameValueOut(): void
	{
		$this->resetForNewRule();
	}

	public function visitAnonymous(Anonymous $anonymous): ?Anonymous
	{
		$reversed = $this->reverseKeyword($anonymous->value);
		if ($reversed)
		{
			return new Anonymous(
				$reversed,
				$anonymous->index,
				$anonymous->currentFileInfo,
				$anonymous->mapLines
			);
		}

		// When not compressing, an anonymous value is output for simple cases ("1px 2px 3px 4px") so we need
		// to account for that.
		if ($this->doReorderShorthand)
		{
			$parts = preg_split('/\s+/', $anonymous->value);
			if (count($parts) == 4)
			{
				if ($this->doReorderShorthand === 'corner')
				{
					$value = "{$parts[1]} {$parts[0]} {$parts[3]} {$parts[2]}";
				}
				else
				{
					$value = "{$parts[0]} {$parts[3]} {$parts[2]} {$parts[1]}";
				}

				return new Anonymous(
					$value,
					$anonymous->index,
					$anonymous->currentFileInfo,
					$anonymous->mapLines
				);
			}
		}

		return $anonymous;
	}

	public function visitKeyword(Keyword $keyword): ?Keyword
	{
		$reversed = $this->reverseKeyword($keyword->value);
		if ($reversed)
		{
			return new Keyword($reversed);
		}
		else
		{
			return $keyword;
		}
	}

	public function visitExpression(Expression $expression): ?Expression
	{
		$value = $expression->value;

		if ($this->doReorderShorthand && is_array($value) && count($value) == 4)
		{
			if ($this->doReorderShorthand === 'corner')
			{
				$value = [$value[1], $value[0], $value[3], $value[2]];
			}
			else
			{
				$value = [$value[0], $value[3], $value[2], $value[1]];
			}

			$this->doReorderShorthand = '';

			return new Expression($value, $expression->parens);
		}
		else
		{
			return $expression;
		}
	}

	protected function processNodeName(string $nodeName): ?string
	{
		$dirPrefix = substr($nodeName, 0, 5);
		$doReverse = $this->isRtl;

		if (preg_match('/^(-rtl-ltr-|-ltr-rtl-)/', $nodeName, $match))
		{
			// disable reversing
			$nodeName = substr($nodeName, strlen($match[0]));
			$doReverse = false;
		}
		else if ($dirPrefix == '-ltr-')
		{
			// LTR only
			if ($this->isRtl)
			{
				return null;
			}
			else
			{
				$nodeName = substr($nodeName, 5);
			}
		}
		else if ($dirPrefix == '-rtl-')
		{
			// RTL only - this won't be reversed either
			if ($this->isRtl)
			{
				$nodeName = substr($nodeName, 5);
				$doReverse = false;
			}
			else
			{
				return null;
			}
		}

		if ($doReverse)
		{
			if (preg_match('/(^|-)(left|right)($|-)/', $nodeName))
			{
				$nodeName = preg_replace_callback(
					'/(^|-)(left|right)($|-)/i',
					function ($match)
					{
						if ($match[2] == 'left')
						{
							$replacePart = 'right';
						}
						else
						{
							$replacePart = 'left';
						}

						return $match[1] . $replacePart . $match[3];
					},
					$nodeName
				);
			}

			if (isset($this->keywordProperties[$nodeName]))
			{
				$this->doReverseKeywords = true;
			}

			if (isset($this->shorthandProperties[$nodeName]))
			{
				$this->doReorderShorthand = $nodeName === 'border-radius'
					? 'corner'
					: 'edge';
			}
		}

		return $nodeName;
	}

	protected function reverseKeyword(string $value): ?string
	{
		if ($this->doReverseKeywords)
		{
			switch ($value)
			{
				case 'left': return 'right';
				case 'right': return 'left';
			}
		}

		return null;
	}

	protected function resetForNewRule(): void
	{
		$this->doReverseKeywords = false;
		$this->doReorderShorthand = '';
	}
}
