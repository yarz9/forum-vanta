<?php

namespace XF\EmbedResolver;

use XF\Container;
use XF\Http\EmbedResponse;
use XF\Mvc\Renderer\AbstractRenderer;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Message;
use XF\Mvc\Reply\View;
use XF\Repository\UserRepository;
use XF\Session\Session;
use XF\Style;

class App extends \XF\App
{
	public function initializeExtra()
	{
		$container = $this->container;

		$container['app.classType'] = 'EmbedResolver';
		$container['app.defaultType'] = 'public';
		$container['job.manual.allow'] = false;

		$container['session'] = function (Container $c)
		{
			return $c['session.embedResolver'];
		};

		$container['response'] = function (Container $c)
		{
			$response = new EmbedResponse();

			$config = $c['config'];
			if (!$config['enableGzip'])
			{
				$response->compressIfAble(false);
			}
			if (!$config['enableContentLength'])
			{
				$response->includeContentLength(false);
			}

			return $response;
		};
	}

	protected function getVisitorFromSession(Session $session, array $extraWith = [])
	{
		/** @var UserRepository $userRepo */
		$userRepo = $this->repository(UserRepository::class);
		return $userRepo->getGuestUser();
	}

	public function run()
	{
		$response = $this->start(true);
		if (!($response instanceof EmbedResponse))
		{
			$controller = new EmbedController($this);
			$response = $controller->run();
		}

		$this->complete($response);
		$this->finalOutputFilter($response);

		return $response;
	}

	public function start($allowShortCircuit = false)
	{
		$response = parent::start($allowShortCircuit);

		if (!$this->options()->allowExternalEmbed)
		{
			$response = $this->response();
			$response->httpCode(404);
			return $response;
		}

		$visitor = \XF::visitor();

		$styleId = $this->request()->filter('style_id', 'uint', 0);
		$styleVariation = $this->request()->filter('style_variation', 'str', '');
		$languageId = $this->request()->filter('language_id', 'uint', 0);

		$visitor->setReadOnly(false);
		$visitor->setAsSaved('style_id', $styleId);
		$visitor->setAsSaved('style_variation', $styleVariation);
		$visitor->setAsSaved('language_id', $languageId);
		$visitor->setReadOnly(true);

		$language = $this->language($languageId);
		if (!$language->isUsable($visitor))
		{
			$language = $this->language(0);
		}

		$language->setTimeZone($visitor->timezone);
		\XF::setLanguage($language);

		return $response;
	}

	public function preRender(AbstractReply $reply, $responseType)
	{
		$visitor = \XF::visitor();

		$styleId = $visitor->style_id;

		/** @var Style $style */
		$style = $this->container->create('style', $styleId);
		if ($style['style_id'] == $styleId)
		{
			if (!$style->isUsable($visitor))
			{
				$style = $this->container->create('style', 0);
			}
		}

		$this->templater()->setStyle($style);
		$this->iconRenderer()->setStyle($style);

		parent::preRender($reply, $responseType);
	}

	protected function renderPageHtml($content, array $params, AbstractReply $reply, AbstractRenderer $renderer)
	{
		$templateName = $params['template'] ?? 'EMBED_CONTAINER';
		if (!$templateName)
		{
			return $content;
		}

		$templater = $this->templater();

		if (!strpos($templateName, ':'))
		{
			$templateName = 'public:' . $templateName;
		}

		if ($reply instanceof View)
		{
			$params['view'] = $reply->getViewClass();
			$params['template'] = $reply->getTemplateName();
			$params['embeddedContent'] = $reply->getParam('content');
		}
		else if ($reply instanceof Error || $reply->getResponseCode() >= 400)
		{
			$params['template'] = 'error';
		}
		else if ($reply instanceof Message)
		{
			$params['template'] = 'message_page';
		}

		$params['content'] = $content;

		// $this->fire('app_pub_render_page', [$this, &$params, $reply, $renderer]);

		return $templater->renderTemplate($templateName, $params);
	}
}
