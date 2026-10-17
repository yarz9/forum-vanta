<?php

namespace XF\BbCode\ProcessorAction;

use XF\BbCode\Processor;

interface ProcessorAwareInterface
{
	public function setProcessor(Processor $processor);
}
