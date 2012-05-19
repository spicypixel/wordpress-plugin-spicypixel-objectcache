<?php

// namespace SpicyPixel;
//
// PHP < 5.3 doesn't support namespaces, so underscore to the rescue.

interface SpicyPixel_Cache
{
	/*
	This function adds data to the cache if the cache key doesn't 
	already exist. If it does exist, the data is not added and the 
	function returns false.
	*/
	public function add($key, &$data, $expire);

	/*
	Adds data to the cache. If the cache key already exists, then 
	it will be overwritten; if not then it will be created.
	*/
	public function set($key, &$data, $expire);

	/*
	Returns the value of the cached object. Returns false if the 
	cache key doesn't exist.
	*/
	public function get($key);

	/*
	Clears data from the cache for the given key.	*/
	public function delete($key);

	/*
	Replaces the given cache if it exists, returns false otherwise. 
	This is similar to wp_cache_set() except the cache object is not 
	added if it doesn't already exist.
	*/
	public function replace($key, &$data, $expire);

	/*
	Clears all cached data.
	*/
	public function flush();
}

?>