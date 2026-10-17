<?php

namespace XF\Cli\Command\Rebuild;

class ProfileBannerOptimization extends AbstractImageOptimizationCommand
{
	protected function getRebuildName(): string
	{
		return 'profile-banner-optimization';
	}

	protected function getRebuildDescription(): string
	{
		return 'Optimizes profile banners to WebP format.';
	}

	protected function getRebuildClass(): string
	{
		return \XF\Job\ProfileBannerOptimization::class;
	}
}
