<?php

use XF\Oembed\Controller;
use XF\Pub\App;

$dir = __DIR__;
require $dir . '/src/XF.php';

\XF::start($dir);
$app = \XF::setupApp(App::class, [
	'preLoad' => ['bbCodeMedia'],
]);

/** @var Controller $oEmbedFetcher */
$oEmbedFetcher = $app->oembed()->controller();

$request = $app->request();
$input = $request->filter([
	'provider' => 'str',
	'id' => 'str',
]);

$showDebugOutput = (\XF::$debugMode && $request->get('_debug'));

if (!empty($input['provider']) && !empty($input['id']))
{
	$input['id'] = str_replace('{{_hash_}}', '#', $input['id']);

	$response = $oEmbedFetcher->outputJson($input['provider'], $input['id']);
	if ($showDebugOutput)
	{
		$response->contentType('text/html', 'utf-8');
		$response->body($app->debugger()->getDebugPageHtml($app));
	}
	$response->send($request);
}
else
{
	header('Content-type: text/plain; charset=utf-8', true, 400);
	echo "Unknown type";
}
