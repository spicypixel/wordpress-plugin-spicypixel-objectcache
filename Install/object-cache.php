<?php

/**
 * Spicy Pixel Object Cache
 */

if (!defined('ABSPATH')) {
    die();
}

if (!defined('SPOC_DIR')) {
    define('SPOC_DIR', WP_CONTENT_DIR . '/plugins/spicypixel-objectcache');
}

if (!@is_dir(SPOC_DIR) || !file_exists(SPOC_DIR . '/Define.php')) {
    if (!defined('WP_ADMIN')) { // lets don't show error on front end
        require_once (ABSPATH . WPINC . '/cache.php'); // load the default object cache instead
    } else {
        @header('HTTP/1.1 503 Service Unavailable');
        die(sprintf('<strong>Spicy Pixel Object Cache Error:</strong> Some files appear to be missing or out of place. Please re-install plugin or remove <strong>%s</strong>.', __FILE__));
    }
} else {
	if(defined('SPOC_SERVER'))
	{
		require_once (ABSPATH . WPINC . '/cache.php'); // load the default object cache instead
		return;
	}
	
	require_once SPOC_DIR . '/Define.php';
	require_once SPOC_DIR . '/ObjectCache.php';

	/**
	 * Init cache
	 *
	 * @return void
	 */
	function wp_cache_init() {
		$GLOBALS['wp_object_cache'] = new SpicyPixel_ObjectCache();
	}

	/**
	 * Close cache
	 *
	 * @return boolean
	 */
	function wp_cache_close() {
		global $wp_object_cache;
					
		return $wp_object_cache->close();
	}
	
	/**
	 * Decrement numeric cache item's value
	 *
	 * @since 3.3.0
	 * @uses $wp_object_cache Object Cache Class
	 * @see WP_Object_Cache::decr()
	 *
	 * @param int|string $key The cache key to increment
	 * @param int $offset The amount by which to decrement the item's value.  Default is 1.
	 * @param string $group The group the key is in.
	 * @return false|int False on failure, the item's new value on success.
	 */
	function wp_cache_decr( $key, $offset = 1, $group = '' ) {
		global $wp_object_cache;

		return $wp_object_cache->decr( $key, $offset, $group );
	}

	/**
	 * Get from cache
	 *
	 * @param string $id
	 * @param string $group
	 * @return mixed
	 */
	function wp_cache_get($id, $group = 'default') {
	    global $wp_object_cache;

	    return $wp_object_cache->get($id, $group);
	}
	
	/**
	 * Increment numeric cache item's value
	 *
	 * @since 3.3.0
	 * @uses $wp_object_cache Object Cache Class
	 * @see WP_Object_Cache::incr()
	 *
	 * @param int|string $key The cache key to increment
	 * @param int $offset The amount by which to increment the item's value.  Default is 1.
	 * @param string $group The group the key is in.
	 * @return false|int False on failure, the item's new value on success.
	 */
	function wp_cache_incr( $key, $offset = 1, $group = '' ) {
		global $wp_object_cache;

		return $wp_object_cache->incr( $key, $offset, $group );
	}

    /**
     * Set cache
     *
     * @param string $id
     * @param mixed $data
     * @param string $group
     * @param integer $expire
     * @return boolean
     */
    function wp_cache_set($id, $data, $group = 'default', $expire = 0) {
        global $wp_object_cache;

        return $wp_object_cache->set($id, $data, $group, $expire);
    }

    /**
     * Delete from cache
     *
     * @param string $id
     * @param string $group
     * @return boolean
     */
    function wp_cache_delete($id, $group = 'default') {
        global $wp_object_cache;

        return $wp_object_cache->delete($id, $group);
    }

    /**
     * Add data to cache
     *
     * @param string $id
     * @param mixed $data
     * @param string $group
     * @param integer $expire
     * @return boolean
     */
    function wp_cache_add($id, $data, $group = 'default', $expire = 0) {
        global $wp_object_cache;

        return $wp_object_cache->add($id, $data, $group, $expire);
    }

    /**
     * Replace data in cache
     *
     * @param string $id
     * @param mixed $data
     * @param string $group
     * @param integer $expire
     * @return boolean
     */
    function wp_cache_replace($id, $data, $group = 'default', $expire = 0) {
        global $wp_object_cache;

        return $wp_object_cache->replace($id, $data, $group, $expire);
    }

    /**
     * Reset cache
     *
     * @return boolean
     */
    function wp_cache_reset() {
        global $wp_object_cache;

        return $wp_object_cache->reset();
    }

    /**
     * Flush cache
     *
     * @return boolean
     */
    function wp_cache_flush() {
        global $wp_object_cache;

        return $wp_object_cache->flush();
    }

    /**
     * Add global groups
     *
     * @param array $groups
     * @return void
     */
    function wp_cache_add_global_groups($groups) {
        global $wp_object_cache;

        $wp_object_cache->add_global_groups($groups);
    }

    /**
     * add non-persistent groups
     *
     * @param array $groups
     * @return void
     */
    function wp_cache_add_non_persistent_groups($groups) {
        global $wp_object_cache;

        $wp_object_cache->add_nonpersistent_groups($groups);
    }
}
