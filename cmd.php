<?php

use XF\Cli\Runner;

$phpVersion = phpversion();
if (version_compare($phpVersion, '7.2.0', '<'))
{
	die("PHP 7.2.0 or newer is required. $phpVersion does not meet this requirement. Please ask your host to upgrade PHP.");
}

$dir = __DIR__;
require $dir . '/src/XF.php';

\XF::start($dir);

$runner = new Runner();
$runner->run();
