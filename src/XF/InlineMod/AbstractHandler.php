<?php

namespace XF\InlineMod;

use XF\App;
use XF\Http\Request;
use XF\Http\Response;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Phrase;

/**
 * @template T of Entity
 */
abstract class AbstractHandler
{
	/**
	 * @var string
	 */
	protected $contentType;

	/**
	 * @var App
	 */
	protected $app;

	/**
	 * @var array<string, AbstractAction>
	 */
	protected $actions;

	/**
	 * @var string
	 */
	protected $baseCookie = 'inlinemod';

	/**
	 * @param string $contentType
	 */
	public function __construct($contentType, App $app)
	{
		$this->contentType = $contentType;
		$this->app = $app;

		$actions = $this->getPossibleActions();
		$app->fire(
			'inline_mod_actions',
			[$this, $app, &$actions],
			$contentType
		);
		$this->actions = $actions;
	}

	/**
	 * @return array<string, AbstractAction>
	 */
	public function getActions()
	{
		return $this->actions;
	}

	/**
	 * @return array<string, AbstractAction>
	 */
	abstract public function getPossibleActions();

	/**
	 * @return list<string>
	 */
	public function getEntityWith()
	{
		return [];
	}

	/**
	 * @param string $action
	 *
	 * @return AbstractAction|null
	 */
	public function getAction($action)
	{
		return $this->actions[$action] ?? null;
	}

	/**
	 * @param T $entity
	 * @param mixed $error
	 *
	 * @return bool
	 */
	public function canViewContent(Entity $entity, &$error = null)
	{
		if (method_exists($entity, 'canView'))
		{
			return $entity->canView($error);
		}

		throw new \LogicException(
			'Could not determine content viewability; please override'
		);
	}

	/**
	 * @return string
	 */
	public function getCookieName()
	{
		return $this->baseCookie . '_' . $this->contentType;
	}

	/**
	 * @return list<int>
	 */
	public function getCookieIds(Request $request)
	{
		$cookie = $request->getCookie($this->getCookieName());
		if ($cookie)
		{
			$ids = explode(',', $cookie);
			$ids = array_map('intval', $ids);
			$ids = array_unique($ids);
			return $ids;
		}
		else
		{
			return [];
		}
	}

	public function clearCookie(Response $response)
	{
		$response->setCookie($this->getCookieName(), false, 0, null, false);
	}

	/**
	 * @param list<int> $ids
	 */
	public function updateCookieIds(Response $response, array $ids)
	{
		$ids = array_map('intval', $ids);
		$ids = array_unique($ids);

		if (!$ids)
		{
			$this->clearCookie($response);
		}
		else
		{
			$response->setCookie(
				$this->getCookieName(),
				implode(',', $ids),
				0,
				null,
				false
			);
		}
	}

	/**
	 * @template TAction of \XF\InlineMod\AbstractAction
	 *
	 * @param class-string<TAction> $class
	 *
	 * @return TAction<T>
	 */
	public function getActionHandler($class)
	{
		$class = \XF::stringToClass($class, '%s\InlineMod\%s');
		$class = $this->app->extendClass($class);
		return new $class($this);
	}

	/**
	 * @param string|\Closure(): string $title
	 * @param string|\Closure(T, array, mixed): bool|true $canApply
	 * @param \Closure(T, array): void $apply
	 *
	 * @return SimpleAction<T>
	 */
	public function getSimpleActionHandler($title, $canApply, \Closure $apply)
	{
		return new SimpleAction($this, $title, $canApply, $apply);
	}

	/**
	 * @param list<int> $ids
	 *
	 * @return AbstractCollection<T>
	 */
	public function getEntities(array $ids)
	{
		return $this->app->findByContentType(
			$this->contentType,
			$ids,
			$this->getEntityWith()
		);
	}

	/**
	 * @return string
	 */
	public function getContentType()
	{
		return $this->contentType;
	}

	/**
	 * @return Phrase|string
	 */
	public function getSelectedTypeTitle()
	{
		return $this->app->getContentTypePhrase($this->contentType, true);
	}

	/**
	 * @return App
	 */
	public function app()
	{
		return $this->app;
	}
}
