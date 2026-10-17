<?php

namespace XF\Cli\Command\Rebuild;

class AvatarOptimization extends AbstractImageOptimizationCommand
{
	protected function getRebuildName(): string
	{
		return 'avatar-optimization';
	}

	protected function getRebuildDescription(): string
	{
		return 'Optimizes avatars to WebP format.';
	}

	protected function getRebuildClass(): string
	{
		return \XF\Job\AvatarOptimization::class;
	}
}
