<?php

use XF\Pub\App;

$dir = __DIR__;
require $dir . '/src/XF.php';

\XF::start($dir);
$app = \XF::setupApp(App::class);

$style = $app->style($app->request()->get('style_id') ?: $app->options()->defaultStyleId ?: 0);
$language = $app->language($app->request()->get('language_id') ?: 0);

/** @var \XF\WebManifestRenderer $renderer */
$renderer = $app->container('webManifestRenderer');
$response = $renderer->render($style, $language);
$response->send($app->request());