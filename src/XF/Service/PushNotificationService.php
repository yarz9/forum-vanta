<?php

namespace XF\Service;

use XF\App;
use XF\Entity\User;
use XF\Job\PushSend;
use XF\Language;
use XF\Mvc\Entity\Repository;
use XF\Repository\UserPushRepository;

use function count;

class PushNotificationService extends AbstractService
{
	public const GCM_URL = 'https://android.googleapis.com/gcm/send';

	/**
	 * @var User
	 */
	protected $receiver;

	/**
	 * @var Language
	 */
	protected $language;

	/**
	 * @var array
	 */
	protected $subscriptions;

	/**
	 * @var array
	 */
	protected $payloadData = [];

	public function __construct(App $app, User $receiver)
	{
		parent::__construct($app);
		$this->receiver = $receiver;
		$this->language = $app->userLanguage($receiver);
	}

	public function setNotificationContent($title, $body, $url = null): void
	{
		$this->payloadData['title'] = $title;
		$this->payloadData['body'] = $body;
		$this->payloadData['url'] = \XF::canonicalizeUrl($url);
	}

	public function setIconAndBadge($icon, $badge = null): void
	{
		$this->payloadData['icon'] = \XF::canonicalizeUrl($icon);

		if ($badge)
		{
			$this->payloadData['badge'] = \XF::canonicalizeUrl($badge);
		}
	}

	public function setDirection($direction): void
	{
		$this->payloadData['dir'] = $direction;
	}

	public function setNotificationTag($tag): void
	{
		$this->payloadData['tag'] = $tag;
	}

	public function setCustomPayloadData($name, $value): void
	{
		$this->payloadData[$name] = $value;
	}

	protected function applyPayloadDefaults(): array
	{
		$options = $this->app->options();
		$language = $this->language;

		$this->payloadData = array_replace([
			'title' => $language->phrase('notification_from_x', ['boardTitle' => $options->boardTitle])->render(),
			'body' => $language->phrase('you_have_new_notification_at_x', ['boardTitle' => $options->boardTitle])->render(),
			'url' => $options->boardUrl,
			'badge' => $this->getDefaultBadgeForVisitor(),
			'icon' => $this->getDefaultIconForVisitor(),
			'dir' => $language->isRtl() ? 'rtl' : 'ltr',
			'tag' => '',
			'tag_phrase' => $language->phrase('(plus_x_previous)')->render(), // {count} is calculated on client
			'total_unread' => $this->receiver->conversations_unread + $this->receiver->alerts_unviewed + 1, // updates to these counts happen *after* notification sent so send current counts + 1
		], $this->payloadData);

		return $this->payloadData;
	}

	public function isPushAvailable(): bool
	{
		$options = $this->app->options();

		return (
			$options->enablePush
			&& $options->pushKeysVAPID['publicKey']
			&& $options->pushKeysVAPID['privateKey']
			&& $this->isReceiverSubscribed()
		);
	}

	public function isReceiverSubscribed(): bool
	{
		$subscriptions = $this->getReceiverSubscriptions();
		return (bool) count($subscriptions);
	}

	protected function getReceiverSubscriptions(): array
	{
		if ($this->subscriptions === null)
		{
			$this->subscriptions = $this->getUserPushRepository()->getUserSubscriptions($this->receiver);
		}

		return $this->subscriptions;
	}

	public function sendNotifications(): void
	{
		if (!$this->isPushAvailable())
		{
			return;
		}

		$payload = $this->applyPayloadDefaults();

		$this->app->jobManager()->enqueue(PushSend::class, [
			'receiverUserId' => $this->receiver->user_id,
			'payload' => $payload,
		], false, 70);
	}

	protected function getDefaultBadgeForVisitor()
	{
		$style = $this->app->style($this->receiver->style_id);
		$badge = $style->getProperty('publicPushBadgeUrl', null);
		if ($badge)
		{
			$badge = \XF::canonicalizeUrl($badge);
		}

		return $badge;
	}

	protected function getDefaultIconForVisitor()
	{
		$style = $this->app->style($this->receiver->style_id);
		$icon = $style->getProperty('publicMetadataLogoUrl', null);
		if ($icon)
		{
			$icon = \XF::canonicalizeUrl($icon);
		}

		return $icon;
	}

	/**
	 * @return Repository|UserPushRepository
	 */
	protected function getUserPushRepository()
	{
		return $this->repository(UserPushRepository::class);
	}
}
