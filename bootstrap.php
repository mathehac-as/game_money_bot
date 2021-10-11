<?php

$config = require_once __DIR__ . DIRECTORY_SEPARATOR.'config.php';
require __DIR__.DIRECTORY_SEPARATOR."vendor".DIRECTORY_SEPARATOR."lincanbin".DIRECTORY_SEPARATOR."php-pdo-mysql-class".DIRECTORY_SEPARATOR."src".DIRECTORY_SEPARATOR."PDO.class.php";

function autoload($classname) {
    $filename = __DIR__.DIRECTORY_SEPARATOR."classes".DIRECTORY_SEPARATOR. $classname .".php";
    require_once($filename);
}

spl_autoload_register("autoload");
require_once __DIR__ . '/vendor/autoload.php';