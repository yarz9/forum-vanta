<?php

namespace XF\ControllerPlugin;

use XF\Entity\QuotableInterface;
use XF\Mvc\Entity\Entity;

use function is_array, is_string, strlen;

class QuotePlugin extends AbstractPlugin
{
	public function actionQuote(Entity $content, $context, $messageKey = 'message')
	{
		$message = $this->prepareMessage($content, $this->filter('quoteHtml', 'str,no-clean'), $messageKey);
		[$quote, $quoteHtml] = $this->getQuote($content, $message, $context);

		$view = $this->view('XF:Quote\Quote');
		$view->setJsonParams([
			'content_id' => $content->getEntityId(),
			'content_type' => $content->getEntityContentType(),
			'quote' => $quote,
			'quoteHtml' => $quoteHtml,
		]);
		return $view;
	}

	public function actionMultiQuote($contentCollection, array $insertOrder, array $quotes, $context, $messageKey = 'message')
	{
		$output = [];

		foreach ($insertOrder AS $insert)
		{
			if (!isset($insert['id']) || !is_string($insert['id']))
			{
				continue;
			}

			[$messageId, $key] = explode('-', $insert['id'], 2);

			if (!isset($quotes[$messageId][$key]) || !isset($contentCollection[$messageId]))
			{
				continue;
			}

			$content = $contentCollection[$messageId];
			$quote = $quotes[$messageId][$key];

			if (is_string($quote))
			{
				$message = $quote;
			}
			else
			{
				$message = $this->prepareMessage($content, null, $messageKey);
			}

			if (!$message)
			{
				continue;
			}
			[$quote, $quoteHtml] = $this->getQuote($content, $message, $context);

			$output[] = ['quote' => $quote, 'quoteHtml' => $quoteHtml];
		}

		$view = $this->view('XF:Quote\MultiQuote');
		$view->setJsonParams($output);
		return $view;
	}

	public function prepareQuotes(array $quotes)
	{
		return array_filter(array_map(function ($quotes): array
		{
			if (!is_array($quotes))
			{
				return [];
			}

			foreach ($quotes AS $i => &$quote)
			{
				if ($quote !== true && !is_string($quote))
				{
					unset($quotes[$i]);
					continue;
				}

				if ($quote === true)
				{
					continue;
				}

				$quote = $this->prepareMessage(null, $quote);
			}

			return $quotes;
		}, $quotes));
	}

	public function prepareMessage($content = null, $html = null, $messageKey = 'message')
	{
		if ($html)
		{
			$message = $this->app->stringFormatter()->getBbCodeFromSelectionHtml($html);
			$message = $this->plugin(EditorPlugin::class)->convertToBbCode($message);
		}
		else
		{
			if ($content instanceof Entity)
			{
				$message = $content->{$messageKey};
			}
			else
			{
				$message = null;
			}
		}

		return $message;
	}

	public function getQuote(Entity $content, $message, $context)
	{
		if (!($content instanceof QuotableInterface))
		{
			throw new \LogicException("Entity $content is not an instanceof \XF\Entity\QuotableInterface");
		}

		$innerContent = $this->app->stringFormatter()->getBbCodeForQuote($message, $context);
		if (strlen($innerContent))
		{
			$quote = $content->getQuoteWrapper(
				$this->app->stringFormatter()->getBbCodeForQuote($message, $context)
			);
		}
		else
		{
			// don't show a blank quote
			$quote = '';
		}

		$quoteHtml = $this->app->bbCode()->render($quote, 'editorHtml', 'editor', $content);

		return [$quote, $quoteHtml];
	}
}
