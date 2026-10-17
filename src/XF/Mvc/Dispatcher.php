<?php

namespace XF\Mvc;

use XF\App;
use XF\Http\Request;
use XF\Mvc\Renderer\AbstractRenderer;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Message;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\Reroute;
use XF\Mvc\Reply\View;
use XF\PrintableException;

use function get_class, is_string, strlen;

class Dispatcher
{
	/**
	 * @var App
	 */
	protected $app;

	/**
	 * @var Request
	 */
	protected $request;

	/**
	 * @var Router
	 */
	protected $router;

	protected $eventPrefix = 'dispatcher';

	/**
	 * @var AbstractReply
	 */
	protected $fallbackReply;

	public function __construct(App $app, ?Request $request = null)
	{
		$this->app = $app;
		$this->request = $request ?: $app->request();
	}

	public function run($routePath = null)
	{
		if ($routePath === null)
		{
			$routePath = $this->request->getRoutePath();
		}

		$match = $this->route($routePath);

		$earlyResponse = $this->beforeDispatch($match);
		if ($earlyResponse)
		{
			return $earlyResponse;
		}

		$reply = $this->dispatchLoop($match);

		$responseType = $reply->getResponseType() ?: $match->getResponseType();
		$response = $this->render($reply, $responseType);

		return $response;
	}

	public function route($routePath)
	{
		$match = $this->getRouter()->routeToController($routePath, $this->request);

		if (!($match instanceof RouteMatch) || !$match->getController())
		{
			$match = $this->app->getErrorRoute('DispatchError', [
				'code' => 'invalid_route',
				'match' => $match,
			]);
		}

		if (strlen($routePath) > 1 && substr($routePath, -1, 1) != '/')
		{
			// this is a route path that does not have a trailing slash which can be ambiguous
			// is it a controller action or is it a URL that contains a string param?
			// if it fails then we should retry the same route path with a trailing slash
			$match->setPathRetry("$routePath/");
		}

		return $match;
	}

	protected function beforeDispatch(RouteMatch $match)
	{
		$this->app->fire($this->eventPrefix . '_pre_dispatch', [$this, $match]);

		return $this->app->preDispatch($match);
	}

	public function dispatchLoop(RouteMatch $match)
	{
		$i = 1;
		$attemptErrorReroute = true;
		$originalMatch = $match;
		$reply = null;

		$this->app->fire($this->eventPrefix . '_match', [$this, &$match]);

		do
		{
			$controllerClass = $match->getController();
			$action = $match->getAction();
			$responseType = $match->getResponseType();
			$sectionContext = $match->getSectionContext();
			$params = $match->getParameterBag();
			$controller = null;

			try
			{
				$reply = $this->dispatchFromMatch($match, $controller, $reply);
			}
			catch (\Throwable $e)
			{
				$reply = $this->handleControllerError($e, $attemptErrorReroute, $controller, [
					'responseType' => $responseType,
					'sectionContext' => $sectionContext,
					'action' => $action,
					'params' => $params,
				]);
				$attemptErrorReroute = false;
			}
			catch (\Exception $e)
			{
				// this will only be hit in PHP 5.x
				$reply = $this->handleControllerError($e, $attemptErrorReroute, $controller, [
					'responseType' => $responseType,
					'sectionContext' => $sectionContext,
					'action' => $action,
					'params' => $params,
				]);
				$attemptErrorReroute = false;
			}

			if (!$reply instanceof AbstractReply)
			{
				$reply = new Reroute(
					$this->app->getErrorRoute('DispatchError', [
						'code' => 'no_reply',
						'controller' => $controllerClass,
						'action' => $action,
					], $responseType)
				);
				$reply->setSectionContext($sectionContext);
			}

			if (!($reply instanceof Reroute) && $attemptErrorReroute)
			{
				// if we might be debugging, move this up so that we can display an error instead of the page results.
				// not doing this can hide errors

				try
				{
					\XF::triggerRunOnce(true);
				}
				catch (\Throwable $e)
				{
					$attemptErrorReroute = false;

					$reply = new Reroute(
						$this->app->getErrorRoute('Exception', ['exception' => $e], $responseType)
					);
					$reply->setResponseType($responseType);
					$reply->setSectionContext($sectionContext);
				}
				catch (\Exception $e)
				{
					// this will only be hit in PHP 5.x
					$attemptErrorReroute = false;

					$reply = new Reroute(
						$this->app->getErrorRoute('Exception', ['exception' => $e], $responseType)
					);
					$reply->setResponseType($responseType);
					$reply->setSectionContext($sectionContext);
				}
			}

			if ($reply instanceof Reroute)
			{
				$match = $reply->getMatch();
				if (!$match->getResponseType())
				{
					$match->setResponseType($responseType);
				}
				if (!$match->getSectionContext())
				{
					$match->setSectionContext($sectionContext);
				}
			}
			else
			{
				break;
			}
		}
		while ($i++ < 10);

		if ($reply instanceof Reroute)
		{
			// rerouted too many times
			$reply = new Error(
				'An error occurred while the page was being generated. Please try again later.'
			);
			$reply->setResponseType($responseType);
			$reply->setSectionContext($sectionContext);
		}

		$this->app->postDispatch($reply, $match, $originalMatch);

		$this->app->fire($this->eventPrefix . '_post_dispatch', [$this, &$reply, $match, $originalMatch]);

		return $reply;
	}

	protected function handleControllerError($e, $attemptErrorReroute, $controller, array $state = [])
	{
		/** @var \Throwable $e */

		$state = array_replace([
			'responseType' => null,
			'sectionContext' => '',
			'action' => '',
			'params' => null,
		], $state);

		if ($attemptErrorReroute)
		{
			\XF::logException($e, true); // rollback as don't know the state

			$reply = new Reroute(
				$this->app->getErrorRoute('Exception', ['exception' => $e], $state['responseType'])
			);
		}
		else
		{
			$reply = new Error(
				'An error occurred while the page was being generated. Please try again later.'
			);
		}

		$reply->setResponseType($state['responseType']);
		$reply->setSectionContext($state['sectionContext']);

		if ($controller instanceof Controller)
		{
			$controller->applyReplyChanges($state['action'], $state['params'] ?: new ParameterBag(), $reply);
		}

		return $reply;
	}

	public function dispatchFromMatch(RouteMatch $match, &$controller = null, ?AbstractReply $previousReply = null)
	{
		return $this->dispatchClass(
			$match->getController(),
			$match->getAction(),
			$match,
			$controller,
			$previousReply
		);
	}

	public function dispatchClass(
		$controllerClass,
		$action,
		RouteMatch $match,
		&$controller = null,
		?AbstractReply $previousReply = null
	)
	{
		$params = $match->getParameterBag();
		if (!$params)
		{
			$params = new ParameterBag();
		}

		$responseType = $match->getResponseType();

		if (!$controllerClass)
		{
			return new Reroute(
				$this->app->getErrorRoute('DispatchError', [
					'code' => 'no_controller',
					'controller' => $controllerClass,
					'action' => is_string($action) ? $action : null,
					'match' => $match,
				], $responseType)
			);
		}

		$controller = $this->app->controller($controllerClass, $this->request);
		if (!$controller)
		{
			return new Reroute(
				$this->app->getErrorRoute('DispatchError', [
					'code' => 'invalid_controller',
					'controller' => $controllerClass,
					'action' => is_string($action) ? $action : null,
					'match' => $match,
				], $responseType)
			);
		}

		$controller->setupFromMatch($match);
		if ($previousReply)
		{
			$controller->setupFromReply($previousReply);
		}

		if ($action instanceof \Closure)
		{
			$action = $action($controller, $responseType, $params);
		}
		else
		{
			$action = preg_replace('#[^a-z0-9]#i', ' ', $action);
			$action = str_replace(' ', '', ucwords($action));
		}

		$method = 'action' . $action;
		if (!is_callable([$controller, $method]))
		{
			$reply = new Reroute(
				$this->app->getErrorRoute('DispatchError', [
					'code' => 'invalid_action',
					'controller' => $controllerClass,
					'action' => $action,
					'match' => $match,
				], $responseType)
			);

			// the original route path failed to resolve to a valid controller action
			// but we have a path to retry - typically the same path but with a trailing slash
			// if the retry path results in a 404 we will fallback to this original invalid_action reply
			$retryPath = $match->getPathRetry();
			if ($retryPath)
			{
				$retryMatch = $this->route($retryPath);
				if ($retryMatch)
				{
					$this->fallbackReply = $reply;
					$reply = new Reroute($retryMatch);
				}
			}

			return $reply;
		}

		try
		{
			$controller->preDispatch($action, $params);
			$reply = $controller->$method($params);

			// this looks like a retry that failed (404) and we have a fallback reply
			// so return that original reply in order to maintain the expected reply
			if ($reply instanceof AbstractReply
				&& $reply->getResponseCode() == 404
				&& $this->fallbackReply
			)
			{
				$reply = $this->fallbackReply;
				$this->fallbackReply = null;
			}
		}
		catch (PrintableException $e)
		{
			$reply = new Error($e->getMessages());
		}
		catch (Reply\Exception $e)
		{
			$reply = $e->getReply();
		}

		if (!$reply)
		{
			$reply = new Reroute(
				$this->app->getErrorRoute('DispatchError', [
					'code' => 'no_reply',
					'controller' => $controllerClass,
					'action' => $action,
				], $responseType)
			);
		}

		$controller->postDispatch($action, $params, $reply);

		$reply->setControllerClass($controllerClass);
		$reply->setAction($action);

		return $reply;
	}

	public function render(AbstractReply $reply, $responseType)
	{
		$this->app->fire($this->eventPrefix . '_pre_render', [$this, $reply, $responseType]);

		$this->app->preRender($reply, $responseType);

		$renderer = $this->app->renderer($responseType);
		$this->setupRenderer($renderer, $reply);

		$content = $this->renderReply($renderer, $reply);

		$content = $this->app->renderPage($content, $reply, $renderer);
		$content = $renderer->postFilter($content, $reply);

		$response = $renderer->getResponse();

		$this->app->fire($this->eventPrefix . '_post_render', [$this, &$content, $reply, $renderer, $response]);

		$response->body($content);

		return $response;
	}

	protected function setupRenderer(AbstractRenderer $renderer, AbstractReply $reply)
	{
		$renderer->setReply($reply);

		$renderer->getResponse()->header('Last-Modified', gmdate('D, d M Y H:i:s', \XF::$time) . ' GMT');
		$renderer->getResponse()->setHeaders($reply->getResponseHeaders());
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
			$url = $this->request->convertToAbsoluteUri($reply->getUrl());
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

	/**
	 * @return Request
	 */
	public function getRequest()
	{
		return $this->request;
	}

	/**
	 * @return Router
	 */
	public function getRouter()
	{
		if (!$this->router)
		{
			$this->router = $this->app->router();
		}

		return $this->router;
	}

	/**
	 * @param Router $router
	 */
	public function setRouter(Router $router)
	{
		$this->router = $router;
	}
}
