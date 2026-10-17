<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

use function is_array;

/**
 * @method AbstractCollection<\XF\Entity\Report> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Report> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Report|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Report>
 */
class ReportFinder extends Finder
{
	public function isActive()
	{
		$this->where('report_state', ['open', 'assigned']);

		return $this;
	}

	public function inTimeFrame($timeFrame = null)
	{
		if ($timeFrame)
		{
			if (!is_array($timeFrame))
			{
				$timeFrom = $timeFrame;
				$timeTo = time();
			}
			else
			{
				$timeFrom = $timeFrame[0];
				$timeTo = $timeFrame[1];
			}

			$this->where(['last_modified_date', '>=', $timeFrom]);
			$this->where(['last_modified_date', '<=', $timeTo]);
		}

		return $this;
	}

	public function forContentUser($contentUser)
	{
		if (isset($contentUser['user_id']))
		{
			$userId = $contentUser['user_id'];
		}
		else
		{
			$userId = $contentUser;
		}
		$this->where('content_user_id', $userId);

		return $this;
	}
}
