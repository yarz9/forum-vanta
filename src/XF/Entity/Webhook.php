<?php

namespace XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\WebhookRepository;

use function count;

/**
 * COLUMNS
 * @property int $webhook_id
 * @property string $title
 * @property string $description
 * @property string $url
 * @property string $secret
 * @property string $content_type
 * @property bool $ssl_verify
 * @property array $events
 * @property array $criteria
 * @property bool $active
 * @property int $creation_date
 *
 * GETTERS
 * @property-read Phrase $formatted_events
 */
class Webhook extends Entity
{
	public function getFormattedEvents(): Phrase
	{
		$contentTypes = array_keys($this->events);
		$total = count($contentTypes);

		switch ($total)
		{
			case 1: $phrase = 'item1'; break;
			case 2: $phrase = 'item1_and_item2'; break;
			case 3: $phrase = 'item1_item2_and_1_other'; break;
			default: $phrase = 'item1_item2_and_x_others'; break;
		}

		$item1 = $item2 = '';

		if (isset($contentTypes[0]))
		{
			$item1 = \XF::phrase(\XF::app()->getContentTypePhraseName($contentTypes[0], true));
			if (isset($contentTypes[1]))
			{
				$item2 = \XF::phrase(\XF::app()->getContentTypePhraseName($contentTypes[1], true));
			}
		}

		$params = [
			'item1' => $item1,
			'item2' => $item2,
			'others' => \XF::language()->numberFormat($total - 2),
		];

		return \XF::phrase($phrase, $params);
	}

	protected function _postSave(): void
	{
		$this->rebuildWebhookCache();
	}

	protected function _postDelete(): void
	{
		$this->rebuildWebhookCache();
	}

	protected function rebuildWebhookCache(): void
	{
		\XF::runOnce('webhookCacheRebuild', function ()
		{
			$this->getWebhookRepo()->rebuildWebhookCache();
		});
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_webhook';
		$structure->shortName = 'XF:Webhook';
		$structure->primaryKey = 'webhook_id';
		$structure->columns = [
			'webhook_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'title' => ['type' => self::STR, 'maxLength' => 150,
				'required' => 'please_enter_valid_title',
			],
			'description' => ['type' => self::STR, 'maxLength' => 150, 'default' => ''],
			'url' => ['type' => self::STR, 'required' => true, 'match' => self::MATCH_URL],
			'secret' => ['type' => self::STR, 'default' => ''],
			'content_type' => ['type' => self::STR, 'default' => 'json',
				'allowedValues' => ['json', 'form_params'],
			],
			'ssl_verify' => ['type' => self::BOOL, 'default' => true],
			'events' => ['type' => self::JSON_ARRAY, 'default' => []],
			'criteria' => ['type' => self::JSON_ARRAY, 'default' => []],
			'active' => ['type' => self::BOOL, 'default' => true],
			'creation_date' => ['type' => self::UINT, 'default' => \XF::$time],
		];
		$structure->getters = [
			'formatted_events' => true,
		];

		return $structure;
	}

	protected function getWebhookRepo(): WebhookRepository
	{
		return $this->repository(WebhookRepository::class);
	}
}
