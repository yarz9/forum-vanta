<?php

namespace XF\NodeType;

use XF\Entity\AbstractNode;
use XF\Entity\Node;
use XF\Entity\SearchForum;
use XF\InputFiltererArray;
use XF\Mvc\FormAction;
use XF\Searcher\Thread;
use XF\Util\Arr;

class SearchForumHandler extends AbstractHandler
{
	public function setupApiTypeDataEdit(
		Node $node,
		AbstractNode $data,
		InputFiltererArray $inputFilterer,
		FormAction $form
	)
	{
		$input = $inputFilterer->filter([
			'sort_order' => '?str',
			'sort_direction' => '?str',
			'max_results' => '?posint',
			'cache_ttl' => '?uint',
		]);
		$input = Arr::filterNull($input);
		$data->bulkSet($input);

		$criteria = $inputFilterer->filter('criteria', '?array');
		if ($criteria)
		{
			$searcher = \XF::app()->searcher(Thread::class, $criteria);
			/** @var SearchForum $data */
			$data->search_criteria = $searcher->getFilteredCriteria();
		}
	}
}
