<?php

ignore_user_abort(true);
set_time_limit(0);

define('SPOC_SERVER', true);
defined('SPOC_DIR') || define('SPOC_DIR', realpath(dirname(__FILE__)));
require_once(SPOC_DIR . '/../../../wp-load.php');
require_once(SPOC_DIR . '/Define.php');
require_once(SPOC_DIR . "/Cache/CacheServer.php");

// Make sure the query time was passed
if(!isset($_GET['doing_start_oc_server']))  {
	throw new Exception("The query string doing_start_oc_server was not passed to ObjectCacheServer.php so the server cannot start.");
}
	
// Make sure this matches what WP knows about or don't honor the start request
$startTime = get_site_option('spicypixel.objectcache.serverstarttime');
if($startTime === false) {
	throw new Exception("The transient value spicypixel.objectcache.serverstarttime was not found in WordPress by ObjectCacheServer.php so the server cannot start.");
}
$queryStartTime = $_GET['doing_start_oc_server'];
if($startTime != $queryStartTime) {
	throw new Exception("The transient value in WordPress and the query string passed to ObjectCacheServer.php do not match so the server cannot start.");
}

$port = get_site_option('spicypixel.objectcache.server.defaultport', SpicyPixel_CacheServer::DEFAULT_PORT);

try
{
	$server = new SpicyPixel_CacheServer('127.0.0.1', $port);
	$server->listen();
}
catch(Exception $ex)
{
	sp_error($ex);
}

?>