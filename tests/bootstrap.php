<?php

declare(strict_types=1);

// this_file: paragra-php/tests/bootstrap.php

$autoload = __DIR__ . '/../vendor/autoload.php';
if (! is_file($autoload)) {
    throw new RuntimeException('Composer autoload not found at ' . $autoload);
}

require_once $autoload;

if (class_exists(DG\BypassFinals::class)) {
    DG\BypassFinals::enable();
}
