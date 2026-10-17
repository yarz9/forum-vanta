<?php

namespace XF\Oembed;

use XF\App;
use XF\Entity\Oembed;
use XF\Finder\OembedFinder;
use XF\Http\Request;
use XF\Http\Response;
use XF\Repository\OembedRepository;
use XF\Service\OembedService;

class Controller
{
	/**
	 * @var App
	 */
	protected $app;

	/**
	 * @var Request
	 */
	protected $request;

	protected $requestUri;
	protected $referrer;
	protected $eTag;

	public const ERROR_INVALID_URL = 1;
	public const ERROR_INVALID_HASH = 2;
	public const ERROR_INVALID_REFERRER = 3;
	public const ERROR_DISABLED = 4;
	public const ERROR_FAILED = 5;
	public const ERROR_INVALID_PROVIDER = 6;

	public function __construct(App $app, ?Request $request = null)
	{
		$this->app = $app;

		if (!$request)
		{
			$request = $app->request();
		}
		$this->request = $request;

		$this->requestUri = $request->getFullRequestUri();
		$this->referrer = $request->getReferrer();
		$this->eTag = $request->getServer('HTTP_IF_NONE_MATCH');
	}

	public function setReferrer($referrer)
	{
		$this->referrer = $referrer;
	}

	public function updateTitles()
	{
		$oEmbeds = $this->app->finder(OembedFinder::class);

		/** @var Oembed $oEmbed */
		foreach ($oEmbeds->fetch() AS $oEmbed)
		{
			if ($oEmbed->isValid())
			{
				$json = json_decode($this->app->fs()->read($oEmbed->getAbstractedJsonPath()), true);

				if (!empty($json['title']))
				{
					$oEmbed->title = $json['title'];
					$oEmbed->save();
				}
			}
		}
	}

	public function outputJson($mediaSiteId, $mediaId)
	{
		if ($this->validateOembedRequest($mediaSiteId, $mediaId, $error))
		{
			/** @var OembedService $oEmbedFetcher */
			$oEmbedFetcher = $this->app->service(OembedService::class);

			$oEmbed = $oEmbedFetcher->getOembed($mediaSiteId, $mediaId);
			if (!$oEmbed || !$oEmbed->isValid())
			{
				$oEmbed = null;
			}
		}
		else
		{
			$oEmbed = null;
		}

		if (!$oEmbed)
		{
			if (!$error)
			{
				$error = self::ERROR_FAILED;
			}

			/** @var OembedRepository $oEmbedRepo */
			$oEmbedRepo = $this->app->repository(OembedRepository::class);
			$oEmbed = $oEmbedRepo->getOembedFailure();
		}

		$response = $this->app->response();
		$this->applyResponseHeaders($response, $oEmbed, $error);

		if ($oEmbed->isFailure())
		{
			// send failure response
			$oEmbedRepo = $this->app->repository(OembedRepository::class);
			$body = $oEmbedRepo->getOembedFailureResponse($mediaSiteId, $mediaId, $error);
		}
		else
		{
			$stream = $this->app->fs()->readStream($oEmbed->getAbstractedJsonPath());
			$body = $response->responseStream($stream);
		}

		$response->body($body);

		return $response;
	}

	public function applyResponseHeaders(Response $response, Oembed $oEmbed, $error, $log = true)
	{
		if (!$error)
		{
			$response->header('X-Oembed-Retain-Scripts', $oEmbed->BbCodeMediaSite->oembed_retain_scripts ? '1' : '0');

			/** \XF\Repository\Oembed */
			$oEmbedRepo = $this->app->repository(OembedRepository::class);

			if ($log)
			{
				$oEmbedRepo->logOembedRequest($oEmbed);
				if ($this->referrer && $this->app->options()->oEmbedRequestReferrer['enabled'])
				{
					$oEmbedRepo->logOembedReferrer($oEmbed, $this->referrer);
				}
			}

			if ($this->eTag && $this->eTag === "\"{$oEmbed->fetch_date}\"")
			{
				$response->httpCode(304);
				$response->removeHeader('Last-Modified');
				return;
			}

			$response->header('ETag', '"' . $oEmbed->fetch_date . '"', true);
		}

		$response->contentType('application/json');

		$response->header('X-Content-Type-Options', 'nosniff');

		if ($error)
		{
			$response->header('X-OembedFetch-Error', $error);
		}
	}

	public function validateOembedRequest($mediaSiteId, $mediaId, &$error = null)
	{
		if (!$this->isValidReferrer())
		{
			$error = self::ERROR_INVALID_REFERRER;
			return false;
		}

		$registry = $this->app->registry();

		if (empty($registry['bbCodeMedia'][$mediaSiteId]) || empty($registry['bbCodeMedia'][$mediaSiteId]['oembed_enabled']))
		{
			$error = self::ERROR_INVALID_PROVIDER;
			return false;
		}

		return true;
	}

	protected function isValidReferrer()
	{
		if (!$this->referrer)
		{
			return true;
		}

		$referrerParts = @parse_url($this->referrer);
		if (!$referrerParts || empty($referrerParts['host']))
		{
			return true;
		}

		$requestParts = @parse_url($this->requestUri);
		if (!$requestParts || empty($requestParts['host']))
		{
			return true;
		}

		return ($requestParts['host'] === $referrerParts['host']);
	}
}
