<?php

namespace XF\Template\Compiler\Syntax;

use XF\Template\Compiler;
use XF\Template\Compiler\Parser;

class BinaryOperator extends AbstractSyntax
{
	public $operator;
	public $lhs;
	public $rhs;

	public $map = [
		Parser::T_OP_AND => 'AND',
		Parser::T_OP_CONCAT => '.',
		Parser::T_OP_DIVIDE => '/',
		Parser::T_OP_EQ => '==',
		Parser::T_OP_GT => '>',
		Parser::T_OP_GTEQ => '>=',
		Parser::T_OP_ID => '===',
		Parser::T_OP_LT => '<',
		Parser::T_OP_LTEQ => '<=',
		Parser::T_OP_MINUS => '-',
		Parser::T_OP_MULTIPLY => '*',
		Parser::T_OP_MOD => '%',
		Parser::T_OP_NE => '!=',
		Parser::T_OP_NID => '!==',
		Parser::T_OP_OR => 'OR',
		Parser::T_OP_PLUS => '+',
	];

	public function __construct($operator, AbstractSyntax $lhs, AbstractSyntax $rhs, $line)
	{
		$this->operator = $operator;
		$this->lhs = $lhs;
		$this->rhs = $rhs;
		$this->line = $line;
	}

	public function compile(Compiler $compiler, array $context, $inlineExpected)
	{
		if ($this->operator !== Parser::T_OP_CONCAT)
		{
			$context['escape'] = false;
		}

		$lhs = $this->lhs->compile($compiler, $context, true);
		$rhs = $this->rhs->compile($compiler, $context, true);

		if ($this->operator === Parser::T_OP_INSTANCEOF)
		{
			return "{$compiler->templaterVariable}->isA($lhs, $rhs)";
		}

		if (isset($this->map[$this->operator]))
		{
			if (!$this->lhs->isSimpleValue())
			{
				$lhs = "($lhs)";
			}
			if (!$this->rhs->isSimpleValue())
			{
				$rhs = "($rhs)";
			}

			$operator = $this->map[$this->operator];
			return "$lhs $operator $rhs";
		}

		throw new \InvalidArgumentException("Unexpected binary operator $this->operator");
	}

	public function isSimpleValue()
	{
		return false;
	}
}
