<?php

namespace XF\EmbedResolver;

use XF\Entity\LinkableInterface;
use XF\Http\Response;
use XF\Mvc\Renderer\AbstractRenderer;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Message;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;
use XF\Repository\EmbedResolverRepository;

use function get_class;

class EmbedController
{
	protected $app;

	public function __construct(App $app)
	{
		$this->app = $app;
	}

	public function run(): Response
	{
		$app = $this->app;

		$content = $app->request()->filter('content', 'str');
		[$contentType, $contentId] = $app->getContentTypeIdFromString($content);

		if (!$contentType || !$contentId)
		{
			return $this->renderError('Required parameters not provided.', 400);
		}

		/** @var EmbedResolverRepository $embedResolver */
		$embedResolver = $app->repository(EmbedResolverRepository::class);
		$embedHandler = $embedResolver->getEmbedHandler($contentType);

		if (!$embedHandler)
		{
			return $this->renderError('Requested content type not found.', 404);
		}

		$content = $embedHandler->getContent($contentId);

		if (!$content)
		{
			return $this->renderError('Requested content not found.', 404);
		}

		if (!method_exists($content, 'canViewEmbed') || !$content->canViewEmbed())
		{
			return $this->renderError('Cannot render requested content.', 403, $content);
		}

		$reply = new View('', 'embed_view', [
			'content' => $content,
			'url' => $content instanceof LinkableInterface ? $content->getContentUrl(true) : null,
		]);

		return $this->render($reply);
	}

	public function render(AbstractReply $reply, string $responseType = 'Html'): Response
	{
		// TODO: CREATE CODE EVENTS

		$this->app->fire('embed_pre_render', [$this, $reply, $responseType]);

		$this->app->preRender($reply, $responseType);

		$renderer = $this->app->renderer($responseType);

		$content = $this->renderReply($renderer, $reply);
		$this->setupRenderer($renderer, $reply);

		$content = $this->app->renderPage($content, $reply, $renderer);
		$content = $renderer->postFilter($content, $reply);

		$response = $renderer->getResponse();

		$this->app->fire('embed_post_render', [$this, &$content, $reply, $renderer, $response]);

		$response->body($content);

		return $response;
	}

	public function renderError(string $error, int $httpCode = 200, $content = null): Response
	{
		$reply = new View('', 'embed_view_error', [
			'error' => $error,
			'content' => $content,
			'url' => $content instanceof LinkableInterface ? $content->getContentUrl(true) : null,
		]);

		$reply->setResponseCode($httpCode);

		return $this->render($reply);
	}

	protected function setupRenderer(AbstractRenderer $renderer, AbstractReply $reply)
	{
		$renderer->setReply($reply);

		$renderer->getResponse()->header('Last-Modified', gmdate('D, d M Y H:i:s', \XF::$time) . ' GMT');
		$renderer->getResponse()->setHeaders($reply->getResponseHeaders());
		$renderer->getResponse()->removeHeader('X-Frame-Options');
		$renderer->setResponseCode($reply->getResponseCode());
		$renderer->getTemplater()->setPageParams($reply->getPageParams());
	}

	protected function renderReply(AbstractRenderer $renderer, AbstractReply $reply)
	{
		if ($reply instanceof Error)
		{
			return $renderer->renderErrors($reply->getErrors());
		}
		else if ($reply instanceof Message)
		{
			return $renderer->renderMessage($reply->getMessage());
		}
		else if ($reply instanceof Redirect)
		{
			$url = $this->app->request()->convertToAbsoluteUri($reply->getUrl());
			return $renderer->renderRedirect($url, $reply->getType(), $reply->getMessage());
		}
		else if ($reply instanceof View)
		{
			return $this->renderView($renderer, $reply);
		}
		else
		{
			throw new \InvalidArgumentException("Unknown reply type: " . get_class($reply));
		}
	}

	public function renderView(AbstractRenderer $renderer, View $reply)
	{
		$params = $reply->getParams();

		$template = $reply->getTemplateName();
		if ($template && !strpos($template, ':'))
		{
			$template = $this->app['app.defaultType'] . ':' . $template;
		}

		return $renderer->renderView($reply->getViewClass(), $template, $params);
	}
}
