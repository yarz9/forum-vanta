<?php

namespace XF\Entity;

use XF\ConnectedAccount\Provider\AbstractProvider;
use XF\ConnectedAccount\ProviderData\AbstractProviderData;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\ConnectedAccountRepository;
use XF\Repository\IconRepository;

use function is_array;

/**
 * COLUMNS
 * @property string $provider_id
 * @property string $provider_class
 * @property int $display_order
 * @property array $options
 *
 * GETTERS
 * @property-read Phrase|string $title
 * @property-read Phrase|string $description
 * @property-read string|null $icon_class
 * @property-read string|null $icon_url
 * @property-read AbstractProvider|null $handler
 */
class ConnectedAccountProvider extends Entity
{
	public function isUsable()
	{
		$handler = $this->handler;
		if (!$handler)
		{
			return false;
		}
		return $handler->isUsable($this);
	}

	public function canBeTested()
	{
		$handler = $this->handler;
		if (!$handler)
		{
			return false;
		}
		return $handler->canBeTested();
	}

	/**
	 * @return Phrase|string
	 */
	public function getTitle()
	{
		if (!empty($this->options['display_title']))
		{
			return $this->options['display_title'];
		}

		$handler = $this->handler;
		return $handler ? $handler->getTitle() : '';
	}

	/**
	 * @return Phrase|string
	 */
	public function getDescription()
	{
		$handler = $this->handler;
		return $handler ? $handler->getDescription() : '';
	}

	public function getIconClass(): ?string
	{
		$handler = $this->handler;
		return $handler ? $handler->getIconClass() : null;
	}

	/**
	 * @return string|null
	 */
	public function getIconUrl()
	{
		$handler = $this->handler;
		return $handler ? $handler->getIconUrl() : null;
	}

	public function renderConfig()
	{
		$handler = $this->handler;
		return $handler ? $handler->renderConfig($this) : '';
	}


	public function renderAssociated(?User $user = null)
	{
		$user = $user ?: \XF::visitor();
		$handler = $this->handler;
		return $handler ? $handler->renderAssociated($this, $user) : '';
	}

	public function isValidForRegistration()
	{
		$handler = $this->handler;
		if (!$handler)
		{
			return false;
		}
		return $handler->isValidForRegistration();
	}

	public function isAssociated(User $user)
	{
		return isset($user->Profile->connected_accounts[$this->provider_id]);
	}

	/**
	 * @return AbstractProviderData|null
	 */
	public function getUserInfo($user = null)
	{
		$handler = $this->handler;
		if (!$handler)
		{
			return null;
		}
		$storageState = $handler->getStorageState($this, $user ?: \XF::visitor());
		return $handler->getProviderData($storageState);
	}

	/**
	 * @return AbstractProvider|null
	 */
	public function getHandler()
	{
		$class = \XF::stringToClass($this->provider_class, '%s\ConnectedAccount\%s');
		if (!class_exists($class))
		{
			return null;
		}

		$class = \XF::extendClass($class);
		return new $class($this->provider_id);
	}

	protected function verifyOptions(&$options)
	{
		if (!is_array($options))
		{
			$options = [];
		}
		if (!$options)
		{
			// this is deactivating
			return true;
		}

		$handler = $this->handler;
		if ($handler && !$handler->verifyConfig($options, $error))
		{
			$this->error($error, 'options');
			return false;
		}

		return true;
	}

	protected function _postSave()
	{
		$this->rebuildConnectedAccountProviderCount();

		$iconRepo = $this->repository(IconRepository::class);
		$iconRepo->enqueueUsageAnalyzer('connected_account');
	}

	protected function _postDelete()
	{
		$this->rebuildConnectedAccountProviderCount();
	}

	protected function rebuildConnectedAccountProviderCount()
	{
		\XF::runOnce('connectedAccountProviderCountRebuild', function ()
		{
			$this->getConnectedAccountRepo()->rebuildProviderCount();
		});
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_connected_account_provider';
		$structure->shortName = 'XF:ConnectedAccountProvider';
		$structure->primaryKey = 'provider_id';
		$structure->columns = [
			'provider_id' => ['type' => self::STR, 'maxLength' => 25, 'match' => self::MATCH_ALPHANUMERIC, 'required' => true],
			'provider_class' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
			'display_order' => ['type' => self::UINT, 'default' => 100],
			'options' => ['type' => self::JSON_ARRAY, 'default' => []],
		];
		$structure->getters = [
			'title' => false,
			'description' => false,
			'icon_class' => false,
			'icon_url' => false,
			'handler' => true,
		];
		$structure->relations = [];

		return $structure;
	}

	/**
	 * @return ConnectedAccountRepository
	 */
	protected function getConnectedAccountRepo()
	{
		return $this->repository(ConnectedAccountRepository::class);
	}
}
