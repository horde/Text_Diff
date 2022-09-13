<?php

$candidates = [
    dirname(__FILE__, 2) . '/vendor/autoload.php',
    dirname(__FILE__, 4) . '/autoload.php',
];
// Cover root case and library case
foreach ($candidates as $candidate) {
    if (file_exists($candidate)) {
        echo $candidate;
        require_once $candidate;
        break;
    }
}
Horde\Test\Bootstrap::bootstrap(dirname(__FILE__));
