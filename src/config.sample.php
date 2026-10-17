<?php

$config['db']['host'] = getenv('DB_HOST') ?: '127.0.0.1';
$config['db']['port'] = getenv('DB_PORT') ?: 3307;
$config['db']['username'] = getenv('DB_USER') ?: 'root';
$config['db']['password'] = getenv('DB_PASS') ?: '';
$config['db']['dbname'] = getenv('DB_NAME') ?: 'vanta_forum';
$config['db']['socket'] = getenv('DB_SOCKET') ?: null;

$config['fullUnicode'] = true;
$config['searchInnoDb'] = true;

$config['debug'] = getenv('XF_DEBUG') === 'true';
$config['development']['enabled'] = getenv('XF_DEV') === 'true';
