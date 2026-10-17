<?php

use XF\EmbedResolver\App;

$dir = __DIR__;
require $dir . '/src/XF.php';

\XF::start($dir);

\XF::runApp(App::class);
