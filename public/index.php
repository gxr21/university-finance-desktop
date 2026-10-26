<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$kernel = new App\Kernel(dirname(__DIR__));
$kernel->handle()->send();
