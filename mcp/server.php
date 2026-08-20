#!/usr/bin/env php
<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$artisan = $basePath . DIRECTORY_SEPARATOR . 'artisan';
$extraArgs = array_slice($_SERVER['argv'] ?? [], 1);

$_SERVER['argv'] = array_merge([$artisan, 'sefar:mcp'], $extraArgs);
$_SERVER['argc'] = count($_SERVER['argv']);
$argv = $_SERVER['argv'];
$argc = $_SERVER['argc'];

require $artisan;
