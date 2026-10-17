<?php

namespace XF\Cli\Command\Rebuild;

class AttachmentOptimization extends AbstractImageOptimizationCommand
{
	protected function getRebuildName(): string
	{
		return 'attachment-optimization';
	}

	protected function getRebuildDescription(): string
	{
		return 'Optimizes attachments to WebP format.';
	}

	protected function getRebuildClass(): string
	{
		return \XF\Job\AttachmentOptimization::class;
	}
}
