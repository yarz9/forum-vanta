<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\Webhook;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Repository\WebhookRepository;

class WebhookController extends AbstractController
{
	public function actionIndex(): AbstractReply
	{
		$webhookRepo = $this->getWebhookRepo();

		$webhooksFinder = $webhookRepo->findWebhooksForList();
		$webhooks = $webhooksFinder->fetch();

		$viewParams = [
			'webhooks' => $webhooks,
		];

		return $this->view('XF:Webhook\Listing', 'webhook_list', $viewParams);
	}

	protected function webhookAddEdit(Webhook $webhook): AbstractReply
	{
		$webhookRepo = $this->getWebhookRepo();

		$viewParams = [
			'webhook' => $webhook,
			'contentTypeHandlers' => $webhookRepo->getWebhookHandlers(),
		];
		return $this->view('XF:Widget\Edit', 'webhook_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params): AbstractReply
	{
		$webhook = $this->assertWebhookExists($params->webhook_id);
		return $this->webhookAddEdit($webhook);
	}

	public function actionAdd(): AbstractReply
	{
		/** @var Webhook $webhook */
		$webhook = $this->em()->create(Webhook::class);
		return $this->webhookAddEdit($webhook);
	}

	protected function webhookSaveProcess(Webhook $webhook): FormAction
	{
		$input = $this->filter([
			'title' => 'str',
			'description' => 'str',
			'url' => 'str',
			'secret' => 'str',
			'events' => 'array',
			'criteria' => 'array',
			'content_type' => 'str',
			'ssl_verify' => 'bool',
			'active' => 'bool',
		]);

		$sendMode = $this->filter('send_mode', 'array-str');
		foreach ($sendMode AS $contentType => $eventSendType)
		{
			if ($eventSendType === 'none')
			{
				unset($input['events'][$contentType]);
			}

			if ($eventSendType === 'all')
			{
				$input['events'][$contentType] = '*';
			}
		}

		$form = $this->formAction();

		$form->validate(function (FormAction $form) use ($input)
		{
			if (empty($input['events']))
			{
				$form->logError(\XF::phrase('please_select_all_applicable_events_that_should_trigger_this_webhook'), 'actions');
			}
		});

		$form->basicEntitySave($webhook, $input);

		return $form;
	}

	public function actionSave(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		if ($params->webhook_id)
		{
			$webhook = $this->assertWebhookExists($params->webhook_id);
		}
		else
		{
			$webhook = $this->em()->create(Webhook::class);
		}

		$this->webhookSaveProcess($webhook)->run();

		return $this->redirect($this->buildLink('webhooks'));
	}

	public function actionDelete(ParameterBag $params)
	{
		$webhook = $this->assertWebhookExists($params->webhook_id);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$webhook,
			$this->buildLink('webhooks/delete', $webhook),
			$this->buildLink('webhooks/edit', $webhook),
			$this->buildLink('webhooks'),
			$webhook->title
		);
	}

	public function actionToggle(): AbstractReply
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(Webhook::class);
	}

	/**
	 * @param      $id
	 * @param null $with
	 * @param null $phraseKey
	 *
	 * @return Webhook
	 * @throws Exception
	 */
	protected function assertWebhookExists($id, $with = null, $phraseKey = null): Webhook
	{
		return $this->assertRecordExists(Webhook::class, $id, $with, $phraseKey);
	}

	protected function getWebhookRepo(): WebhookRepository
	{
		return $this->repository(WebhookRepository::class);
	}
}
