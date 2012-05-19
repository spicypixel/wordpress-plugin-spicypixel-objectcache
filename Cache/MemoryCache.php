<?php

// namespace SpicyPixel;
//
// PHP < 5.3 doesn't support namespaces, so underscore to the rescue.

defined('SPOC_DIR') || define('SPOC_DIR', realpath(dirname(__FILE__) . '/..'));
require_once(SPOC_DIR . '/Cache/Cache.php');

class SpicyPixel_MemoryCache implements SpicyPixel_Cache
{
	const CACHE_EXPIRE_MAX = 2592000;

	private $cache = array();
	
	public function __construct()
	{
	}
	
	/*
	This function adds data to the cache if the cache key doesn't 
	already exist. If it does exist, the data is not added and the 
	function returns false.
	*/
	public function add($key, &$data, $expire)
	{
		if(array_key_exists($key, $this->cache))
			return false;
			
		$this->set($key, $data, $expire);
		return true;
	}

	/*
	Adds data to the cache. If the cache key already exists, then 
	it will be overwritten; if not then it will be created.
	*/
	public function set($key, &$data, $expire)
	{
		$entry = array(
			'data' => $data,
			'expire' => min($expire, self::CACHE_EXPIRE_MAX),
			'lastModified' => time()
		);
				
		$this->cache[$key] = $entry;
	}

	/*
	Returns the value of the cached object. Returns false if the 
	cache key doesn't exist.
	*/
	public function get($key)
	{
		if(!array_key_exists($key, $this->cache))
			return false;
			
		$entry = $this->cache[$key];
		$expire = $entry['expire'];
		
		if($expire > 0) {
			$lastModified = $entry['lastModified'];
			$elapsed = time() - $lastModified;
			if($elapsed > $expire) {
				$this->delete($key);
				return false;
			}
		}
		
		$dataCopy = $entry['data'];
		return $dataCopy;
	}

	/*
	Clears data from the cache for the given key.	*/
	public function delete($key)
	{
		unset($this->cache[$key]);
	}

	/*
	Replaces the given cache if it exists, returns false otherwise. 
	This is similar to wp_cache_set() except the cache object is not 
	added if it doesn't already exist.
	*/
	public function replace($key, &$data, $expire)
	{
		if(!array_key_exists($key, $this->cache))
			return false;
			
		$this->set($key, $data, $expire);
		return true;
	}

	/*
	Clears all cached data.
	*/
	public function flush()
	{
		$this->cache = array();
	}
}

?>