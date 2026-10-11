<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Run composer install in symfony-doctrine-search-bundle before PHPUnit.');
}

require $autoload;
