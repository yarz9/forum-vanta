<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ActivitySummarySection> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ActivitySummarySection> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ActivitySummarySection|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ActivitySummarySection>
 */
class ActivitySummarySectionFinder extends Finder
{
	public function definitionActive(): Finder
	{
		$this->with('ActivitySummaryDefinition', true)
			->whereAddOnActive([
				'relation' => 'ActivitySummaryDefinition.AddOn',
				'column' => 'ActivitySummaryDefinition.addon_id',
			]);

		return $this;
	}

	public function activeOnly(): Finder
	{
		$this->definitionActive();

		$this->where('active', 1);

		return $this;
	}
}
