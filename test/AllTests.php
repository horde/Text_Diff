<?php
use Horde\Tests\AllTests;

if (class_exists(AllTests::class)) {
    Horde\Test\AllTests::init(__FILE__)->run();
}