<?php

namespace XF\Entity;

use XF\FeaturedContent\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Repository\FeaturedContentRepository;
use XF\Util\File;

/**
 * COLUMNS
 * @property int|null $featured_content_id
 * @property string $content_type
 * @property int $content_id
 * @property int $content_container_id
 * @property int $content_user_id
 * @property string $content_username
 * @property int $content_date
 * @property bool $content_visible
 * @property int $feature_user_id
 * @property int $feature_date
 * @property bool $auto_featured
 * @property bool $always_visible
 * @property string $title_
 * @property int $image_date
 * @property string $snippet_
 *
 * GETTERS
 * @property-read Entity|null $Content
 * @property string $title
 * @property-read string|null $image
 * @property string $snippet
 * @property-read string $content_link
 *
 * RELATIONS
 * @property-read User|null $ContentUser
 * @property-read User|null $FeatureUser
 */
class FeaturedContent extends Entity implements ViewableInterface
{
	public function canView(&$error = null): bool
	{
		$handler = $this->getHandler();
		$content = $this->Content;
		if (!$handler || !$content)
		{
			return false;
		}

		if ($this->always_visible)
		{
			return true;
		}

		return $handler->canViewContent($content, $error);
	}

	public function canViewContent(&$error = null): bool
	{
		$handler = $this->getHandler();
		$content = $this->Content;
		if (!$handler || !$content)
		{
			return false;
		}

		return $handler->canViewContent($content, $error);
	}

	public function isIgnored(): bool
	{
		return \XF::visitor()->isIgnoring($this->content_user_id);
	}

	public function isCustomized(): bool
	{
		return $this->title_ || $this->snippet_ || $this->image_date;
	}

	public function render(
		string $macro = 'article',
		int $snippetLength = 0
	): string
	{
		$handler = $this->getHandler();
		return $handler ? $handler->render($this, $macro, $snippetLength) : '';
	}

	public function getHandler(): ?AbstractHandler
	{
		return $this->getFeatureRepo()->getFeatureHandler($this->content_type);
	}

	public function getContent(): ?Entity
	{
		return $this->getContentForStyle('article');
	}

	public function setContent(?Entity $content)
	{
		$this->_getterCache['Content'] = $content;
	}

	public function getContentForStyle(string $style): ?Entity
	{
		$handler = $this->getHandler();
		return $handler
			? $handler->getContentForStyle($this->content_id, $style)
			: null;
	}

	public function getTitle(): string
	{
		if (!$this->title_)
		{
			$handler = $this->getHandler();
			if (!$handler)
			{
				return '';
			}

			$content = $this->Content;
			if (!$content)
			{
				return '';
			}

			return $handler->getContentTitle($content);
		}

		return $this->app()->stringFormatter()->censorText($this->title_);
	}

	public function getImage(?string $sizeCode = null): ?string
	{
		if (!$this->image_date)
		{
			$handler = $this->getHandler();
			if (!$handler)
			{
				return null;
			}

			$content = $this->Content;
			if (!$content)
			{
				return null;
			}

			return $handler->getContentImage($content, $sizeCode);
		}

		return $this->app()->applyExternalDataUrl(
			$this->getImagePath($sizeCode) . '?' . $this->image_date
		);
	}

	public function getAbstractedImagePath(?string $sizeCode = null): string
	{
		return 'data://' . $this->getImagePath($sizeCode);
	}

	protected function getImagePath(?string $sizeCode = null): string
	{
		return sprintf(
			'featured_content/%s/%d/%d.jpg',
			$this->content_type,
			floor($this->content_id / 1000),
			$this->content_id
		);
	}

	public function getSnippet(): string
	{
		if (!$this->snippet_)
		{
			$handler = $this->getHandler();
			if (!$handler)
			{
				return '';
			}

			$content = $this->Content;
			if (!$content)
			{
				return '';
			}

			return $handler->getContentSnippet($content);
		}

		return $this->app()->stringFormatter()->censorText($this->snippet_);
	}

	public function getContentLink(bool $canonical = false): string
	{
		$handler = $this->getHandler();
		if (!$handler)
		{
			return '';
		}

		$content = $this->Content;
		if (!$content)
		{
			return '';
		}

		return $handler->getContentLink($content, $canonical);
	}

	public function getStructuredData(): array
	{
		$handler = $this->getHandler();
		if (!$handler)
		{
			return [];
		}

		$content = $this->Content;
		if (!$content)
		{
			return [];
		}

		return $handler->getContentStructuredData($content);
	}

	protected function _postDelete()
	{
		File::deleteFromAbstractedPath($this->getAbstractedImagePath());
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_featured_content';
		$structure->shortName = 'XF:FeaturedContent';
		$structure->primaryKey = 'featured_content_id';
		$structure->columns = [
			'featured_content_id' => [
				'type' => self::UINT,
				'autoIncrement' => true,
				'nullable' => true,
			],
			'content_type' => [
				'type' => self::STR,
				'maxLength' => 25,
				'required' => true,
			],
			'content_id' => [
				'type' => self::UINT,
				'required' => true,
			],
			'content_container_id' => [
				'type' => self::UINT,
				'default' => 0,
			],
			'content_user_id' => [
				'type' => self::UINT,
				'default' => 0,
			],
			'content_username' => [
				'type' => self::STR,
				'maxLength' => 50,
				'default' => '',
			],
			'content_date' => [
				'type' => self::UINT,
				'default' => 0,
			],
			'content_visible' => [
				'type' => self::BOOL,
				'required' => true,
			],
			'feature_user_id' => [
				'type' => self::UINT,
				'required' => true,
			],
			'feature_date' => [
				'type' => self::UINT,
				'default' => \XF::$time,
			],
			'auto_featured' => [
				'type' => self::BOOL,
				'default' => false,
			],
			'always_visible' => [
				'type' => self::BOOL,
				'default' => false,
			],
			'title' => [
				'type' => self::STR,
				'default' => '',
				'maxLength' => 150,
			],
			'image_date' => [
				'type' => self::UINT,
				'default' => 0,
			],
			'snippet' => [
				'type' => self::STR,
				'default' => '',
				'maxLength' => 500,
			],
		];
		$structure->getters = [
			'Content' => true,
			'title' => true,
			'image' => true,
			'snippet' => true,
			'content_link' => true,
		];
		$structure->relations = [
			'ContentUser' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [['user_id', '=', '$content_user_id']],
				'primary' => true,
			],
			'FeatureUser' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [['user_id', '=', '$feature_user_id']],
				'primary' => true,
			],
		];
		$structure->defaultWith = ['ContentUser'];

		return $structure;
	}

	protected function getFeatureRepo(): FeaturedContentRepository
	{
		return $this->repository(FeaturedContentRepository::class);
	}
}
