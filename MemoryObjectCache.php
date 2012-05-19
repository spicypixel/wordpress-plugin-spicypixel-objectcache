<?php

/**
 * WordPress Object Cache
 *
 * The WordPress Object Cache is used to save on trips to the database. The
 * Object Cache stores all of the cache data to memory and makes the cache
 * contents available by using a key, which is used to name and later retrieve
 * the cache contents.
 *
 * The Object Cache can be replaced by other caching mechanisms by placing files
 * in the wp-content folder which is looked at in wp-settings. If that file
 * exists, then this file will not be included.
 *
 * @package WordPress
 * @subpackage Cache
 * @since 2.0
 */
class SpicyPixel_MemoryObjectCache {

	/**
	 * Holds the cached objects
	 *
	 * @var array
	 * @access private
	 * @since 2.0.0
	 */
	var $cache = array ();

	/**
	 * The amount of times the cache data was already stored in the cache.
	 *
	 * @since 2.5.0
	 * @access private
	 * @var int
	 */
	var $cache_hits = 0;

	/**
	 * Amount of times the cache did not have the request in cache
	 *
	 * @var int
	 * @access public
	 * @since 2.0.0
	 */
	var $cache_misses = 0;

	/**
	 * List of global groups
	 *
	 * @var array
	 * @access protected
	 * @since 3.0.0
	 */
	var $global_groups = array();

	/**
	 * Adds data to the cache if it doesn't already exist.
	 *
	 * @uses WP_Object_Cache::get Checks to see if the cache already has data.
	 * @uses WP_Object_Cache::set Sets the data after the checking the cache
	 *		contents existence.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $key What to call the contents in the cache
	 * @param mixed $data The contents to store in the cache
	 * @param string $group Where to group the cache contents
	 * @param int $expire When to expire the cache contents
	 * @return bool False if cache key and group already exist, true on success
	 */
	function add( $key, $data, $group = 'default', $expire = '' ) {
		if ( wp_suspend_cache_addition() )
			return false;

		if ( empty ($group) )
			$group = 'default';

		if (false !== $this->get($key, $group))
			return false;

		return $this->set($key, $data, $group, $expire);
	}

	/**
	 * Sets the list of global groups.
	 *
	 * @since 3.0.0
	 *
	 * @param array $groups List of groups that are global.
	 */
	function add_global_groups( $groups ) {
		$groups = (array) $groups;

		$this->global_groups = array_merge($this->global_groups, $groups);
		$this->global_groups = array_unique($this->global_groups);
	}

	/**
	 * Decrement numeric cache item's value
	 *
	 * @since 3.3.0
	 *
	 * @param int|string $key The cache key to increment
	 * @param int $offset The amount by which to decrement the item's value.  Default is 1.
	 * @param string $group The group the key is in.
	 * @return false|int False on failure, the item's new value on success.
	 */
	function decr( $key, $offset = 1, $group = 'default' ) {
		if ( ! isset( $this->cache[ $group ][ $key ] ) )
			return false;

		if ( ! is_numeric( $this->cache[ $group ][ $key ] ) )
			$this->cache[ $group ][ $key ] = 0;

		$offset = (int) $offset;

		$this->cache[ $group ][ $key ] -= $offset;

		if ( $this->cache[ $group ][ $key ] < 0 )
			$this->cache[ $group ][ $key ] = 0;

		return $this->cache[ $group ][ $key ];
	}

	/**
	 * Remove the contents of the cache key in the group
	 *
	 * If the cache key does not exist in the group and $force parameter is set
	 * to false, then nothing will happen. The $force parameter is set to false
	 * by default.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $key What the contents in the cache are called
	 * @param string $group Where the cache contents are grouped
	 * @param bool $force Optional. Whether to force the unsetting of the cache
	 *		key in the group
	 * @return bool False if the contents weren't deleted and true on success
	 */
	function delete($key, $group = 'default', $force = false) {
		if (empty ($group))
			$group = 'default';

		if (!$force && false === $this->get($key, $group))
			return false;

		unset ($this->cache[$group][$key]);
		return true;
	}

	/**
	 * Clears the object cache of all data
	 *
	 * @since 2.0.0
	 *
	 * @return bool Always returns true
	 */
	function flush() {
		$this->cache = array ();

		return true;
	}

	/**
	 * Retrieves the cache contents, if it exists
	 *
	 * The contents will be first attempted to be retrieved by searching by the
	 * key in the cache group. If the cache is hit (success) then the contents
	 * are returned.
	 *
	 * On failure, the number of cache misses will be incremented.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $key What the contents in the cache are called
	 * @param string $group Where the cache contents are grouped
	 * @param string $force Whether to force a refetch rather than relying on the local cache (default is false)
	 * @return bool|mixed False on failure to retrieve contents or the cache
	 *		contents on success
	 */
	function get( $key, $group = 'default', $force = false) {
		if ( empty ($group) )
			$group = 'default';

		if ( isset ($this->cache[$group][$key]) ) {
			$this->cache_hits += 1;
			if ( is_object($this->cache[$group][$key]) )
				return clone $this->cache[$group][$key];
			else
				return $this->cache[$group][$key];
		}

		$this->cache_misses += 1;
		return false;
	}

	/**
	 * Increment numeric cache item's value
	 *
	 * @since 3.3.0
	 *
	 * @param int|string $key The cache key to increment
	 * @param int $offset The amount by which to increment the item's value.  Default is 1.
	 * @param string $group The group the key is in.
	 * @return false|int False on failure, the item's new value on success.
	 */
	function incr( $key, $offset = 1, $group = 'default' ) {
		if ( ! isset( $this->cache[ $group ][ $key ] ) )
			return false;

		if ( ! is_numeric( $this->cache[ $group ][ $key ] ) )
			$this->cache[ $group ][ $key ] = 0;

		$offset = (int) $offset;

		$this->cache[ $group ][ $key ] += $offset;

		if ( $this->cache[ $group ][ $key ] < 0 )
			$this->cache[ $group ][ $key ] = 0;

		return $this->cache[ $group ][ $key ];
	}

	/**
	 * Replace the contents in the cache, if contents already exist
	 *
	 * @since 2.0.0
	 * @see WP_Object_Cache::set()
	 *
	 * @param int|string $key What to call the contents in the cache
	 * @param mixed $data The contents to store in the cache
	 * @param string $group Where to group the cache contents
	 * @param int $expire When to expire the cache contents
	 * @return bool False if not exists, true if contents were replaced
	 */
	function replace($key, $data, $group = 'default', $expire = '') {
		if (empty ($group))
			$group = 'default';

		if ( false === $this->get($key, $group) )
			return false;

		return $this->set($key, $data, $group, $expire);
	}

	/**
	 * Reset keys
	 *
	 * @since 3.0.0
	 */
	function reset() {
		// Clear out non-global caches since the blog ID has changed.
		foreach ( array_keys($this->cache) as $group ) {
			if ( !in_array($group, $this->global_groups) )
				unset($this->cache[$group]);
		}
	}

	/**
	 * Sets the data contents into the cache
	 *
	 * The cache contents is grouped by the $group parameter followed by the
	 * $key. This allows for duplicate ids in unique groups. Therefore, naming of
	 * the group should be used with care and should follow normal function
	 * naming guidelines outside of core WordPress usage.
	 *
	 * The $expire parameter is not used, because the cache will automatically
	 * expire for each time a page is accessed and PHP finishes. The method is
	 * more for cache plugins which use files.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $key What to call the contents in the cache
	 * @param mixed $data The contents to store in the cache
	 * @param string $group Where to group the cache contents
	 * @param int $expire Not Used
	 * @return bool Always returns true
	 */
	function set($key, $data, $group = 'default', $expire = '') {
		if ( empty ($group) )
			$group = 'default';

		if ( NULL === $data )
			$data = '';

		if ( is_object($data) )
			$data = clone $data;

		$this->cache[$group][$key] = $data;
		return true;
	}

	/**
	 * Echoes the stats of the caching.
	 *
	 * Gives the cache hits, and cache misses. Also prints every cached group,
	 * key and the data.
	 *
	 * @since 2.0.0
	 */
	function stats() {
		echo "<p>";
		echo "<strong>Cache Hits:</strong> {$this->cache_hits}<br />";
		echo "<strong>Cache Misses:</strong> {$this->cache_misses}<br />";
		echo "</p>";
		echo '<ul>';
		foreach ($this->cache as $group => $cache) {
			echo "<li><strong>Group:</strong> $group - ( " . number_format( strlen( serialize( $cache ) ) / 1024, 2 ) . 'k )</li>';
		}
		echo '</ul>';
	}

	/**
	 * Sets up object properties; PHP 5 style constructor
	 *
	 * @since 2.0.8
	 * @return null|WP_Object_Cache If cache is disabled, returns null.
	 */
	function __construct() {
		/**
		 * @todo This should be moved to the PHP4 style constructor, PHP5
		 * already calls __destruct()
		 */
		register_shutdown_function(array(&$this, "__destruct"));
	}

	/**
	 * Will save the object cache before object is completely destroyed.
	 *
	 * Called upon object destruction, which should be when PHP ends.
	 *
	 * @since  2.0.8
	 *
	 * @return bool True value. Won't be used by PHP
	 */
	function __destruct() {
		return true;
	}
}
	
?>