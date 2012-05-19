<?php

defined('SPOC_DIR') || define('SPOC_DIR', realpath(dirname(__FILE__) . '/..'));
require(SPOC_DIR . '/Cache/CacheServer.php');

$server = new SpicyPixel_CacheServer();
$server->listen();

?>