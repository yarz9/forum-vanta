<?php

namespace XF\Less\Tree;

use Less_Tree_Mixin_Call as MixinCall;
use Less_Tree_Selector as Selector;

class HslMixinCall extends MixinCall
{
	/**
	 * @var string
	 */
	public $type = 'HslMixinCall';

	/**
	 * @param string[]|null $currentFileInfo
	 */
	public function __construct(
		Selector $selector,
		array $arguments,
		int $index,
		array $currentFileInfo,
		?bool $important = false
	)
	{
		$this->selector = $selector;
		$this->arguments = $arguments;
		$this->index = $index;
		$this->currentFileInfo = $currentFileInfo;
		$this->important = $important;
	}

	public static function fromMixinCall(MixinCall $call): self
	{
		return new self(
			$call->selector,
			$call->arguments,
			$call->index,
			$call->currentFileInfo,
			$call->important
		);
	}

	/**
	 * @param \Less_Visitor $visitor
	 */
	public function accept($visitor): void
	{
		$this->selector = $visitor->visitObj($this->selector);

		$this->arguments = array_map(
			function ($argument) use ($visitor)
			{
				$argument['value'] = $visitor->visitObj($argument['value']);

				return $argument;
			},
			$this->arguments
		);
	}
}
