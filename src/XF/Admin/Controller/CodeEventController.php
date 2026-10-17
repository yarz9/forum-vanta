<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\DescLoaderPlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\CodeEvent;
use XF\Entity\CodeEventListener;
use XF\Finder\CodeEventListenerFinder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Repository\AddOnRepository;
use XF\Repository\CodeEventListenerRepository;
use XF\Repository\CodeEventRepository;

class CodeEventController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertDevelopmentMode();
	}

	public function actionIndex()
	{
		$viewParams = [
			'events' => $this->getEventRepo()->findEventsForList()->fetch(),
		];
		return $this->view('XF:CodeEvent\Listing', 'code_event_list', $viewParams);
	}

	protected function eventAddEdit(CodeEvent $event)
	{
		$viewParams = [
			'event' => $event,
		];
		return $this->view('XF:CodeEvent\Edit', 'code_event_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$event = $this->assertEventExists($params['event_id']);
		return $this->eventAddEdit($event);
	}

	public function actionAdd()
	{
		$event = $this->em()->create(CodeEvent::class);
		return $this->eventAddEdit($event);
	}

	protected function eventSaveProcess(CodeEvent $event)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'event_id' => 'str',
			'description' => 'str',
			'addon_id' => 'str',
		]);
		$form->basicEntitySave($event, $input);

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params['event_id'])
		{
			$event = $this->assertEventExists($params['event_id']);
		}
		else
		{
			$event = $this->em()->create(CodeEvent::class);
		}

		$this->eventSaveProcess($event)->run();

		return $this->redirect($this->buildLink('code-events') . $this->buildLinkHash($event->event_id));
	}

	public function actionDelete(ParameterBag $params)
	{
		$event = $this->assertEventExists($params->event_id);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$event,
			$this->buildLink('code-events/delete', $event),
			$this->buildLink('code-events/edit', $event),
			$this->buildLink('code-events'),
			$event->event_id
		);
	}

	public function actionGetDescription()
	{
		/** @var DescLoaderPlugin $plugin */
		$plugin = $this->plugin(DescLoaderPlugin::class);
		return $plugin->actionLoadDescription(CodeEvent::class);
	}

	public function actionListener()
	{
		$listeners = $this->getListenerRepo()
			->findListenersForList()
			->fetch();

		/** @var AddOnRepository $addOnRepo */
		$addOnRepo = $this->repository(AddOnRepository::class);
		$addOns = $addOnRepo->findAddOnsForList()->fetch();

		$viewParams = [
			'listeners' => $listeners->groupBy('addon_id'),
			'totalListeners' => $listeners->count(),
			'addOns' => $addOns,
		];
		return $this->view('XF:CodeEvent\Listener\Listing', 'code_event_listener_list', $viewParams);
	}

	protected function listenerAddEdit(CodeEventListener $listener)
	{
		$events = $this->getEventRepo()
			->findEventsForList()
			->fetch()
			->pluckNamed('event_id', 'event_id');

		$viewParams = [
			'listener' => $listener,
			'events' => $events,
		];
		return $this->view('XF:CodeEvent\Listener\Edit', 'code_event_listener_edit', $viewParams);
	}

	public function actionListenerEdit(ParameterBag $params)
	{
		$listener = $this->assertListenerExists($params['event_listener_id']);
		return $this->listenerAddEdit($listener);
	}

	public function actionListenerAdd()
	{
		$listener = $this->em()->create(CodeEventListener::class);
		return $this->listenerAddEdit($listener);
	}

	protected function listenerSaveProcess(CodeEventListener $listener)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'event_id' => 'str',
			'execute_order' => 'uint',
			'description' => 'str',
			'callback_class' => 'str',
			'callback_method' => 'str',
			'active' => 'bool',
			'addon_id' => 'str',
			'hint' => 'str',
		]);
		$form->basicEntitySave($listener, $input);

		return $form;
	}

	public function actionListenerSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params['event_listener_id'])
		{
			$listener = $this->assertListenerExists($params['event_listener_id']);
		}
		else
		{
			$listener = $this->em()->create(CodeEventListener::class);
		}

		$this->listenerSaveProcess($listener)->run();

		return $this->redirect($this->buildLink('code-events/listeners') . $this->buildLinkHash($listener->event_listener_id));
	}

	public function actionListenerDelete(ParameterBag $params)
	{
		$listener = $this->assertListenerExists($params['event_listener_id']);

		$contentTitle = $listener->event_id;
		if ($listener->AddOn)
		{
			$contentTitle .= sprintf(
				" %s%s %s%s",
				\XF::language()->parenthesis_open,
				\XF::phrase('add_on:'),
				$listener->AddOn->title,
				\XF::language()->parenthesis_close
			);
		}

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$listener,
			$this->buildLink('code-events/listeners/delete', $listener),
			$this->buildLink('code-events/listeners/edit', $listener),
			$this->buildLink('code-events/listeners'),
			$contentTitle
		);
	}

	public function actionListenerToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(CodeEventListenerFinder::class);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return CodeEvent
	 */
	protected function assertEventExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(CodeEvent::class, $id, $with, $phraseKey);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return CodeEventListener
	 */
	protected function assertListenerExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(CodeEventListener::class, $id, $with, $phraseKey);
	}

	/**
	 * @return CodeEventRepository
	 */
	protected function getEventRepo()
	{
		return $this->repository(CodeEventRepository::class);
	}

	/**
	 * @return CodeEventListenerRepository
	 */
	protected function getListenerRepo()
	{
		return $this->repository(CodeEventListenerRepository::class);
	}
}
