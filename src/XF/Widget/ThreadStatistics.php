<?php

namespace XF\Widget;

class ThreadStatistics extends AbstractWidget
{
	public function render(): ?WidgetRenderer
	{
		$thread = $this->contextParams['thread'] ?? null;
		if (!$thread)
		{
			return null;
		}

		$viewParams = [
			'thread' => $thread,
		];

		return $this->renderer('widget_thread_statistics', $viewParams);
	}
}
