<?php

use XF\Cli\App;

if (php_sapi_name() !== 'cli')
{
	die('CLI only');
}

$root = __DIR__ . '/../../../../';
require $root . '/src/XF.php';

\XF::start($root);
$app = \XF::setupApp(App::class);

ini_set('display_errors', true);
chdir(__DIR__);

if (isset($_SERVER['argv'][1]))
{
	set_include_path(get_include_path() . PATH_SEPARATOR . $_SERVER['argv'][1]);
}

// Parser must come last
register_shutdown_function('parser_shutdown');

$_SERVER['argv'] = [basename(__FILE__), 'Parser.y'];
$_SERVER['argc'] = 2;

$me = new \PHP_ParserGenerator();
$me->main(); // this calls exit so need to hack around that with a shutdown function

function parser_shutdown()
{
	$prefixCode = "
namespace XF\Template\Compiler;
";

	$contents = file_get_contents('Parser.php');

	$contents = preg_replace('#^(<\?php)#i', '$1' . "\n$prefixCode", $contents);
	$contents = preg_replace('#(implements\s+)(ArrayAccess)#i', '$1\\\\$2', $contents);

	$contents = preg_replace('/function offsetExists\(\$offset\)/', 'function offsetExists($offset): bool', $contents);
	$contents = preg_replace('/function offsetGet\(\$offset\)/', "#[\ReturnTypeWillChange]\n    function offsetGet(\$offset)", $contents);
	$contents = preg_replace('/function offsetSet\(\$offset, \$value\)/', 'function offsetSet($offset, $value): void', $contents);
	$contents = preg_replace('/function offsetUnset\(\$offset\)/', 'function offsetUnset($offset): void', $contents);

	$contents = preg_replace('/^0#(.*)$/m', '#$1', $contents);

	$contents = preg_replace(
		'#@(\$this->yystack\[[^]]+\])->minor#',
		'$1->major',
		$contents
	);

	file_put_contents('Parser.php', $contents);

	echo 'Parser build complete.' . PHP_EOL;
}
