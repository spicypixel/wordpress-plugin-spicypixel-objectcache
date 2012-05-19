<?php

defined('SPOC_DIR') || define('SPOC_DIR', realpath(dirname(__FILE__) . '/..'));
require(SPOC_DIR . '/Cache/RemoteCache.php');
require(SPOC_DIR . '/Cache/MemoryCache.php');

function test()
{
	/*$c = new SpicyPixel_MemoryCache();
	$r1 = $c->get('k1');
	echo "r1 = $r1\n";
	assert($r1 === false);
	$r1 = $c->add('k1', $cv1 = 'cv1', 0);
	assert($r1 !== false);
	$r1 = $c->get('k1');
	echo "r1 = $r1\n";
	assert($r1 !== false);*/
	
	echo "Create cache\n";
	$cache = new SpicyPixel_RemoteCache();
	
	echo "Connect cache\n";	
	$cache->connect();
	$cache->shutdown();
	echo "Get value\n";
	$k1 = $cache->get('k1');
	echo "k1 = $k1\n";
	assert($k1 === false);
	
	echo "Get value\n";
	$k1 = $cache->get('k1');
	echo "k1 = $k1\n";
	assert($k1 === false);

	echo "Get value\n";
	$k1 = $cache->get('k1');
	echo "k1 = $k1\n";
	assert($k1 === false);
	
	//	return;
		
	echo "Set value\n";
	$cache->set('k1', $v1 = 'v1', 0);
	
	echo "Get value\n";
	$k1 = $cache->get('k1');
	echo "k1 = $k1\n";
	assert($k1 == 'v1');

	echo "Add value\n";
	$r = $cache->add('k1', $v1, 0);
	echo "R: $r";
	assert($r === false);
	$r = $cache->add('k2', $v1, 0);
	assert($r !== false);
	
	echo "Replace value\n";
	$r = $cache->replace('k2', $v2 = 'v2', 0);
	assert($r !== false);
	$k2 = $cache->get('k2');
	assert($k2 == 'v2');
	$r = $cache->replace('k3', $v3 = 'v3', 0);
	assert($r === false);
	
	echo "Delete value\n";
	$r = $cache->delete('k2');
	assert($r !== false);
	$r = $cache->delete('k2');
	assert($r !== false); // second delete does not throw

	echo "Flush\n";
	$r = $cache->flush();
	assert($r !== false);
		
	echo "Close\n";
	$cache->close();
}

test();

?>