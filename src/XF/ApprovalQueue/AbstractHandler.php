<?php

namespace XF\ApprovalQueue;

use XF\Entity\ApprovalQueue;
use XF\Entity\User;
use XF\InputFiltererArray;
use XF\Mvc\Entity\Entity;
use XF\Repository\SpamRepository;
use XF\Util\Php;

use function call_user_func_array, func_get_args, is_array;

/**
 * @template T of Entity
 */
abstract class AbstractHandler
{
	/**
	 * @var InputFiltererArray
	 */
	protected $inputFilterer;

	protected $filterCache = [];

	/**
	 * @var string
	 */
	protected $contentType;

	/**
	 * @param string $contentType
	 */
	public function __construct($contentType)
	{
		$this->contentType = $contentType;
	}

	/**
	 * @param T $content
	 * @param string|\Stringable|null &$error
	 *
	 * @return bool
	 */
	public function canView(Entity $content, &$error = null)
	{
		if (!$this->canViewContent($content, $error))
		{
			return false;
		}

		if (!$this->canActionContent($content, $error))
		{
			return false;
		}

		return true;
	}

	/**
	 * @param T $content
	 * @param string|\Stringable|null &$error
	 *
	 * @return bool
	 */
	protected function canViewContent(Entity $content, &$error = null)
	{
		if (method_exists($content, 'canView'))
		{
			return $content->canView($error);
		}

		throw new \LogicException("Could not determine content viewability; please override");
	}

	/**
	 * @param T $content
	 * @param string|\Stringable|null &$error
	 *
	 * @return bool
	 */
	protected function canActionContent(Entity $content, &$error = null)
	{
		return true;
	}

	/**
	 * @return string
	 */
	public function getTemplateName()
	{
		return 'public:approval_item_' . $this->contentType;
	}

	public function getEmailTemplateName(): ?string
	{
		$template = 'approval_item_' . $this->contentType;

		$templater = \XF::app()->templater();

		if (!$templater->isKnownTemplate('email:' . $template))
		{
			return 'approval_item_create';
		}

		return $template;
	}

	/**
	 * @return array{
	 *     unapprovedItem: ApprovalQueue,
	 *     content: T|null,
	 *     spamDetails: array|null,
	 *     handler: static,
	 * }
	 */
	public function getTemplateData(ApprovalQueue $unapprovedItem)
	{
		return [
			'unapprovedItem' => $unapprovedItem,
			'content' => $unapprovedItem->Content,
			'spamDetails' => $unapprovedItem->SpamDetails,
			'handler' => $this,
		];
	}

	/**
	 * @return string
	 */
	public function render(ApprovalQueue $unapprovedItem)
	{
		$template = $this->getTemplateName();
		if (!$template)
		{
			return '';
		}

		return \XF::app()->templater()->renderTemplate($template, $this->getTemplateData($unapprovedItem));
	}

	/**
	 * @return list<string>
	 */
	public function getEntityWith()
	{
		return [];
	}

	/**
	 * @param int|list<int> $id
	 *
	 * @return T|\XF\Mvc\Entity\AbstractCollection<T>
	 */
	public function getContent($id)
	{
		return \XF::app()->findByContentType($this->contentType, $id, $this->getEntityWith());
	}

	/**
	 * @param int|list<int> $id
	 *
	 * @return array<int, string>
	 */
	public function getSpamDetails($id)
	{
		/** @var SpamRepository $spamRepo */
		$spamRepo = \XF::app()->repository(SpamRepository::class);

		$spamTriggerLogsFinder = $spamRepo->findSpamTriggerLogs()->forContent($this->contentType, $id);

		return $spamTriggerLogsFinder->fetch()->pluckNamed('details', 'content_id');
	}

	/**
	 * @return array<string, string|\Stringable>
	 */
	public function getDefaultActions()
	{
		return [
			'' => \XF::phrase('do_nothing'),
			'approve' => \XF::phrase('approve'),
			'delete' => \XF::phrase('delete'),
		];
	}

	/**
	 * @param string $action
	 * @param T $entity
	 */
	public function performAction($action, Entity $entity)
	{
		$args = func_get_args();
		unset($args[0]);

		$method = 'action' . ucfirst(Php::camelCase($action));
		if (!Php::validateCallbackPhrased($this, $method, $error))
		{
			throw new \LogicException($error);
		}

		return call_user_func_array([$this, $method], $args);
	}

	public function setInput(array $input)
	{
		$this->inputFilterer = new InputFiltererArray(
			\XF::app()->inputFilterer(),
			$input
		);
	}

	/**
	 * @param string $key
	 * @param int $id
	 *
	 * @return mixed
	 */
	protected function getInput($key, $id)
	{
		if (!isset($this->filterCache[$key]))
		{
			$this->filterCache[$key] = $this->inputFilterer->filter($key, 'array');
		}

		return !empty($this->filterCache[$key][$this->contentType][$id]) ? $this->filterCache[$key][$this->contentType][$id] : '';
	}

	/**
	 * @param T $entity
	 * @param string $field
	 * @param mixed|null $value
	 */
	protected function quickUpdate(Entity $entity, $field, $value = null)
	{
		$values = $field;

		if (!is_array($field))
		{
			$values = [$field => $value];
		}

		$entity->bulkSet($values);
		$entity->save();
	}

	protected function _spamCleanInternal(User $user)
	{
		if (!\XF::visitor()->canCleanSpam() || !$user->isPossibleSpammer())
		{
			return;
		}

		$cleanActions = \XF::options()->spamDefaultOptions;
		unset($cleanActions['check_ips']); // never check IPs in bulk

		$cleaner = \XF::app()->spam()->cleaner($user);

		if ($cleaner->isRecentlyCleaned())
		{
			return;
		}

		if ($cleanActions['ban_user'])
		{
			$cleaner->banUser();
		}

		$cleaner->cleanUpContent($cleanActions);
		$cleaner->finalize();
	}

	/**
	 * @return string|\Stringable
	 */
	public function getContentTypePhrase()
	{
		return \XF::app()->getContentTypePhrase($this->contentType);
	}
}
