<?php

// Database configuration from environment variables
$config['db']['host'] = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: '127.0.0.1');
$config['db']['port'] = getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: 3306);
$config['db']['username'] = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root');
$config['db']['password'] = getenv('DB_PASS') ?: (getenv('MYSQL_ROOT_PASSWORD') ?: '');
$config['db']['dbname'] = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'railway');
$config['db']['socket'] = getenv('DB_SOCKET') ?: null;

if (getenv('DATABASE_URL')) {
    $url = parse_url(getenv('DATABASE_URL'));
    $config['db']['host'] = $url['host'] ?? $config['db']['host'];
    $config['db']['port'] = $url['port'] ?? $config['db']['port'];
    $config['db']['username'] = $url['user'] ?? $config['db']['username'];
    $config['db']['password'] = $url['pass'] ?? $config['db']['password'];
    $config['db']['dbname'] = ltrim($url['path'] ?? '', '/') ?: $config['db']['dbname'];
}

if (getenv('MYSQL_URL')) {
    $url = parse_url(getenv('MYSQL_URL'));
    $config['db']['host'] = $url['host'] ?? $config['db']['host'];
    $config['db']['port'] = $url['port'] ?? $config['db']['port'];
    $config['db']['username'] = $url['user'] ?? $config['db']['username'];
    $config['db']['password'] = $url['pass'] ?? $config['db']['password'];
    $config['db']['dbname'] = ltrim($url['path'] ?? '', '/') ?: $config['db']['dbname'];
}

$config['fullUnicode'] = true;

$config['development']['enabled'] = true;

if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
