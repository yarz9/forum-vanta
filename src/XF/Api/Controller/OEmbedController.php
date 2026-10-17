<?php

namespace XF\Api\Controller;

use XF\Entity\EmbedResolverTrait;
use XF\Repository\EmbedResolverRepository;

class OEmbedController extends AbstractController
{
	public function allowUnauthenticatedRequest($action)
	{
		return $this->options()->allowExternalEmbed;
	}

	public function actionGet()
	{
		$this->assertRequiredApiInput('url');

		$url = $this->filter('url', 'str');

		/** @var EmbedResolverRepository $embedRepo */
		$embedRepo = $this->app->repository(EmbedResolverRepository::class);

		/** @var EmbedResolverTrait $content */
		$content = $embedRepo->getEntityFromUrl($url);

		if (!$content)
		{
			return $this->apiError(
				\XF::phrase('requested_content_for_url_x_unavailable', ['url' => $url]),
				'requested_content_unavailable',
				['url' => $url]
			);
		}

		return $this->apiResult($content->getOembedOutput());
	}
}
