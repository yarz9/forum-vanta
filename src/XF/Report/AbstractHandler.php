<?php

namespace XF\Report;

use XF\Entity\Report;
use XF\Mvc\Entity\Entity;

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
	 * @param string $contentType
	 */
	public function __construct($contentType)
	{
		$this->contentType = $contentType;
	}

	/**
	 * @return bool
	 */
	public function canView(Report $report)
	{
		if (!$this->canViewContent($report))
		{
			return false;
		}

		if (!$this->canActionContent($report))
		{
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	protected function canViewContent(Report $report)
	{
		return true;
	}

	/**
	 * @return bool
	 */
	protected function canActionContent(Report $report)
	{
		return true;
	}

	/**
	 * @param T $content
	 */
	abstract public function setupReportEntityContent(Report $report, Entity $content);

	/**
	 * @return string|\Stringable
	 */
	abstract public function getContentTitle(Report $report);

	/**
	 * @return string
	 */
	abstract public function getContentMessage(Report $report);

	/**
	 * @return string
	 */
	public function getContentLink(Report $report)
	{
		return '';
	}

	/**
	 * @param string $message
	 *
	 * @return array{
	 *     link: string,
	 *     title: string|\Stringable,
	 *     userLink: string|null,
	 *     username: string|\Stringable,
	 *     reporterLink: string,
	 *     reporter: string,
	 *     reportReason: string,
	 *     message: string,
	 *     extraDetails: string,
	 * }
	 */
	public function getContentForThreadReport(Report $report, $message)
	{
		$visitor = \XF::visitor();

		return [
			'link' => $this->getContentLink($report),
			'title' => $this->getContentTitle($report),
			'userLink' => $report->User ? \XF::app()->router('public')->buildLink('canonical:members', $report->User) : null,
			'username' => $report->User ? $report->User->username : \XF::phrase('guest'),
			'reporterLink' => \XF::app()->router('public')->buildLink('canonical:members', $visitor),
			'reporter' => $visitor->username,
			'reportReason' => $message,
			'message' => $this->getContentMessage($report),
			'extraDetails' => '',
		];
	}

	/**
	 * @return string
	 */
	public function getTemplateName()
	{
		return 'public:report_content_' . $this->contentType;
	}

	/**
	 * @return array{
	 *     report: Report,
	 *     content: T|null,
	 * }
	 */
	public function getTemplateData(Report $report)
	{
		return [
			'report' => $report,
			'content' => $report->Content,
		];
	}

	/**
	 * @return string
	 */
	public function render(Report $report)
	{
		$template = $this->getTemplateName();
		if (!$template)
		{
			return '';
		}
		return \XF::app()->templater()->renderTemplate($template, $this->getTemplateData($report));
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
	 * @return list<string>
	 */
	public static function getWebhookEvents(): array
	{
		return ['report'];
	}
}
