<?php

namespace XF\Pub\View\Search;

use XF\Mvc\View;

class AutoComplete extends View
{
	/**
	 * @return array{
	 *     results: array<string, array{
	 *         id: string,
	 *         text: string,
	 *         extraParams: array{type: string, url: string}
	 *         q: string,
	 *         desc?: string,
	 *         icon?: string,
	 *         iconHtml?: string,
	 *     }>,
	 *     q: string,
	 * }
	 */
	public function renderJson(): array
	{
		$results = array_map(
			function (array $result)
			{
				$result['extraParams'] = [
					'type' => $result['type'],
					'url' => $result['url'],
				];

				$result['q'] = $this->params['q'];

				unset($result['type'], $result['url']);

				return $result;
			},
			$this->params['results']
		);

		$q = $this->params['q'];

		return [
			'results' => $results,
			'q' => $q,
		];
	}
}
