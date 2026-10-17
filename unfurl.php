<?php

use XF\Pub\App;

$dir = __DIR__;
require $dir . '/src/XF.php';

\XF::start($dir);
$app = \XF::setupApp(App::class);

$request = $app->request();
$response = $app->response();

$input = $request->filter([
	'result_ids' => 'array',
]);

/** @var \XF\Repository\UnfurlRepository $unfurlRepo */
$unfurlRepo = $app->repository(\XF\Repository\UnfurlRepository::class);

$resultIds = $input['result_ids'];
if (!$resultIds)
{
	$response->body(json_encode(['success' => false]));
	$response->send($request);
	exit;
}

$results = $unfurlRepo->finder(\XF\Finder\UnfurlResultFinder::class)
	->whereIds($resultIds)
	->fetch();

$response->header('Content-Type', 'text/event-stream');
$response->header('Cache-Control', 'no-cache');
$response->header('Connection', 'keep-alive');

foreach ($results AS $result)
{
	if ($result->pending)
	{
		$body = json_encode([
			'result_id' => $result->result_id,
			'success' => false,
			'url' => $result->url,
		]);
		echo $body . "\n";
		ob_flush();
		flush();
		continue;
	}

	if ($result->error_count)
	{
		$body = json_encode([
			'result_id' => $result->result_id,
			'success' => false,
			'url' => $result->url,
		]);
		echo $body . "\n";
		ob_flush();
		flush();
		continue;
	}

	$html = $app->templater()->renderUnfurl($result, []);

	$body = json_encode([
		'result_id' => $result->result_id,
		'success' => true,
		'html' => $html,
	]);

	echo $body . "\n";
	ob_flush();
	flush();
}

flush();
$response->send($request);