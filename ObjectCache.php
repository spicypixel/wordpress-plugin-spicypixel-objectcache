<?php

defined('SPOC_DIR') || define('SPOC_DIR', realpath(dirname(__FILE__)));
require_once(SPOC_DIR . '/MemoryObjectCache.php');
require_once(SPOC_DIR . '/Cache/RemoteCache.php');

class SpicyPixel_ObjectCache {
	// Time to wait to retry starting the server
	const SERVER_RETRY_DELAY = 60;
	
	/**
	 * The amount of times the cache data was already stored in the cache.
	 *
	 * @since 2.5.0
	 * @access private
	 * @var int
	 */
	var $cache_hits_total = 0;
	var $cache_hits_transient = 0;
	var $cache_hits_persistent = 0;

	/**
	 * Amount of times the cache did not have the request in cache
	 *
	 * @var int
	 * @access public
	 * @since 2.0.0
	 */
	var $cache_misses_total = 0;
	var $cache_misses_transient = 0;
	var $cache_misses_persistent = 0;
	
	var $cache_requests = 0;
	var $nonpersistent_cache_requests = 0;
	
	var $memoryCache;
	var $remoteCache;

    /**
     * Key cache
     *
     * @var array
     */
    var $key_cache = array();

	/**
	 * List of global groups
	 *
	 * @var array
	 * @access protected
	 * @since 3.0.0
	 */
	var $global_groups = array();

	/**
     * List of non-persistent groups
     *
     * @var array
     */
    var $nonpersistent_groups = array();

	/**
	 * Returns flattened cache key
	 *
	 * @param string $id
	 * @param string $group
	 * @return string
	 */
	function sp_get_cache_key($id, $group = 'default') {
		if (!$group) {
			$group = 'default';
		}

		$blog_id = sp_get_blog_id();
		$key_cache_id = $blog_id . $group . $id;

		if (isset($this->key_cache[$key_cache_id])) {
			$key = $this->key_cache[$key_cache_id];
		}
		else {
			$host = sp_get_host();

			if (in_array($group, $this->global_groups)) {
	        	$host_id = $host;
			}
			else {
				$host_id = sprintf('%s_%d', $host, $blog_id);
			}

			$key = sprintf('spoc_%s_object_%s', $host_id, md5($group . $id));

			$this->key_cache[$key_cache_id] = $key;
		}

		return $key;
	}
		
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
			
		$memoryResult = $this->memoryCache->add($key, $data, $group, $expire);
		if($memoryResult === false)
			return false; // already in the memory cache so skip hitting the network where it should already be too

		// If the group is non-persistent, don't hit the persistent cache
		if(in_array($group, $this->nonpersistent_groups)) {
			return $memoryResult;
		}

		if($this->validRemoteCache()) {
			try {
				return $this->remoteCache->add($this->sp_get_cache_key($key, $group), $data, $expire);
			}
			catch(Exception $ex) {
				$this->closeRemoteCache();
			}
		}
				
		return $memoryResult;
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
		
		$this->memoryCache->add_global_groups($groups);
	}
	
	/**
     * Add non-persistent groups
     *
     * @param array $groups
     * @return void
     */
    function add_nonpersistent_groups($groups) {
        if (!is_array($groups)) {
            $groups = (array) $groups;
        }

        $this->nonpersistent_groups = array_merge($this->nonpersistent_groups, $groups);
        $this->nonpersistent_groups = array_unique($this->nonpersistent_groups);
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
		$memoryResult = $this->memoryCache->decr($key, $offset, $group);
		
		// If the group is non-persistent, don't hit the persistent cache
		if(in_array($group, $this->nonpersistent_groups)) {
			return $memoryResult;
		}
		
		if(!$this->validRemoteCache())
			return $memoryResult;
		
		try {
			$remoteResult = $this->remoteCache->get(sp_get_cache_key($key, $group));
				
			if($remoteResult !== false) {
				if(!is_numeric($remoteResult))
					$remoteResult = 0;
				
				$offset = (int) $offset;
				$remoteResult -= $offset;
				if($remoteResult < 0)
					$remoteResult = 0;
					
				if($this->remoteCache->set(sp_get_cache_key($key, $group)))
					return $remoteResult;
			}
		}
		catch(Exception $ex) {
			$this->closeRemoteCache();
		}
		
		return $memoryResult;	
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
			
		$this->memoryCache->delete($key, $group, $force);
		
		// If the group is non-persistent, don't hit the persistent cache
		if(in_array($group, $this->nonpersistent_groups)) {
			return $memoryResult;
		}
		
		if($this->validRemoteCache()) {
			try {
				return $this->remoteCache->delete($this->sp_get_cache_key($key, $group));
			}
			catch(Exception $ex) {
				$this->closeRemoteCache();
			}
		}
		
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
		$this->memoryCache->flush();

		if($this->validRemoteCache()) {
			try {
				$this->remoteCache->flush();
			}
			catch(Exception $ex) {
				$this->closeRemoteCache();
			}
		}
		
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
			
		$this->cache_requests += 1;
		
		$memoryResult = $this->memoryCache->get($key, $group, $force);
		if($memoryResult === false) {
			$this->cache_misses_transient += 1;
		}
		else {
			$this->cache_hits_transient += 1;
			if(!$force) {
				$this->cache_hits_total += 1;
				return $memoryResult;
			}
		}
				
		// If the group is non-persistent, don't hit the persistent cache
		if(!in_array($group, $this->nonpersistent_groups)) {
			$remoteResult = false;
			if($this->validRemoteCache()) {
				try {
					$remoteResult = $this->remoteCache->get($this->sp_get_cache_key($key, $group));
					if($remoteResult === false) {
						$this->cache_misses_persistent += 1;
					}
					else {
						$this->cache_hits_persistent += 1;
						$this->cache_hits_total += 1;

						if (is_object($remoteResult)) {
							$remoteResult = wp_clone($remoteResult);
						}
						
						return $remoteResult;
					}
				}
				catch(Exception $ex) {
					$this->closeRemoteCache();
				}
			}
		} else {
			$this->nonpersistent_cache_requests += 1;
		}
		
		if($force && $memoryResult !== false) {
			$this->cache_hits_total += 1;
			return $memoryResult;
		}
		
		// There is a cache miss. 
		$this->cache_misses_total += 1;
		
		// WP has a bug right now.
		//   functions.php line 347
		//   ms-blogs.php line 340
		//
		// $notoptions = wp_cache_get( 'notoptions', 'options' );
		// if ( isset( $notoptions[$option] ) )
		//     return $default;
		//
		// The cache returns false but then the code tries to index
		// into an array. Most other places in the code use an
		// is_array($notoptions) guard.
		//
		// Work around for now.

		if($key == 'notoptions' && $group == 'options') {
			return array();
		}
				
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
		
		$memoryResult = $this->memoryCache->incr($key, $offset, $group);
		
		// If the group is non-persistent, don't hit the persistent cache
		if(in_array($group, $this->nonpersistent_groups)) {
			return $memoryResult;
		}
		
		if(!$this->validRemoteCache())
			return $memoryResult;
		
		try {
			$remoteResult = $this->remoteCache->get(sp_get_cache_key($key, $group));
				
			if($remoteResult !== false) {
				if(!is_numeric($remoteResult))
					$remoteResult = 0;
				
				$offset = (int) $offset;
				$remoteResult += $offset;
				if($remoteResult < 0)
					$remoteResult = 0;
					
				if($this->remoteCache->set(sp_get_cache_key($key, $group)))
					return $remoteResult;
			}
		}
		catch(Exception $ex) {
			$this->closeRemoteCache();
		}
		
		return $memoryResult;	
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

		$memoryResult = $this->memoryCache->replace($key, $data, $group, $expire);

		// If the group is non-persistent, don't hit the persistent cache
		if(in_array($group, $this->nonpersistent_groups)) {
			return $memoryResult;
		}

		if($this->validRemoteCache()) {
			try {
				return $this->remoteCache->replace($this->sp_get_cache_key($key, $group), $data, $expire);
			}
			catch(Exception $ex) {
				$this->closeRemoteCache();
			}
		}
				
		return $memoryResult;			
	}

	/**
	 * Reset keys
	 *
	 * @since 3.0.0
	 */
	function reset() {
		return $this->memoryCache->reset();
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

		$memoryResult = $this->memoryCache->set($key, $data, $group, $expire);

		// If the group is non-persistent, don't hit the persistent cache
		if(in_array($group, $this->nonpersistent_groups)) {
			return $memoryResult;
		}

		if($this->validRemoteCache()) {
			try {
				return $this->remoteCache->set($this->sp_get_cache_key($key, $group), $data, $expire);
			}
			catch(Exception $ex) {
				$this->closeRemoteCache();
			}
		}
				
		return $memoryResult;	
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
		echo "<p>\n";
		$pr = $this->cache_requests - $this->nonpersistent_cache_requests;
		echo "<strong>Cache Requests:</strong> {$this->cache_requests} total, {$pr} persistent, {$this->nonpersistent_cache_requests} non-persistent<br />\n";		echo "<strong>Final Cache Hits:</strong> {$this->cache_hits_total}<br />\n";
		echo "<strong>Final Cache Misses:</strong> {$this->cache_misses_total}<br />\n";
		echo "<strong>Transient Cache Hits:</strong> {$this->cache_hits_transient}<br />\n";
		echo "<strong>Transient Cache Misses:</strong> {$this->cache_misses_transient}<br />\n";
		if($this->cache_hits_persistent > 0 || $this->cache_misses_persistent > 0) {
			echo "<strong>Persistent Cache:</strong> active<br />\n";
			echo "<strong>Persistent Cache Hits:</strong> {$this->cache_hits_persistent}<br />\n";
			echo "<strong>Persistent Cache Misses:</strong> {$this->cache_misses_persistent}<br />\n";
		}
		else {
			echo "<strong>Persistent Cache:</strong> unavailable<br />\n";
		}
		echo "</p>\n";
		//echo '<ul>\n';
		/*foreach ($this->cache as $group => $cache) {
			echo "<li><strong>Group:</strong> $group - ( " . number_format( strlen( serialize( $cache ) ) / 1024, 2 ) . 'k )</li>';
		}*/
		//echo '</ul>\n';
	}
	
	function stats_comment() 
	{
		$pr = $this->cache_requests - $this->nonpersistent_cache_requests;
		echo "Cache Requests:\t\t {$this->cache_requests} total, {$pr} persistent, {$this->nonpersistent_cache_requests} non-persistent\n";
		echo "Final Cache Hits:\t {$this->cache_hits_total}\n";
		echo "Final Cache Misses:\t {$this->cache_misses_total}\n";
		echo "Transient Cache Hits:\t {$this->cache_hits_transient}\n";
		echo "Transient Cache Misses:\t {$this->cache_misses_transient}\n";
		if($this->cache_hits_persistent > 0 || $this->cache_misses_persistent > 0) {
			echo "Persistent Cache:\t [active]\n";
			echo "Persistent Cache Hits:\t {$this->cache_hits_persistent}\n";
			echo "Persistent Cache Misses: {$this->cache_misses_persistent}\n";
		}
		else {
			echo "Persistent Cache:\t [unavailable]\n";
		}
		//echo '<ul>\n';
		/*foreach ($this->cache as $group => $cache) {
			echo "<li><strong>Group:</strong> $group - ( " . number_format( strlen( serialize( $cache ) ) / 1024, 2 ) . 'k )</li>';
		}*/
		//echo '</ul>\n';
	}

	private $lastCreateRemoteCache = 0;
		
	// TODO: This needs to actually start the cache server
	// if it's not running in addition to connecting the client
	//
	// create lock, start server, connect
	// connect fails, remove lock file
	private function validRemoteCache()
	{		
		if(isset($this->remoteCache))
			return true;
		
		// Pages will be served in < 60 seconds so for all
		// practical purposes this prevents another attempt to
		// connect after the first attempt fails.
		//
		// That's fine. Next page hit will try again.
		if(time() - $this->lastCreateRemoteCache < 60) {
			//echo "Not trying again yet: " . (time() - (int)$this->lastCreateRemoteCache) . "\n<br/>";
			return false;
		}

		$this->lastCreateRemoteCache = time();

		try {
			$this->remoteCache = new SpicyPixel_RemoteCache();
			$this->remoteCache->connect();
		}
		catch(Exception $ex) {
			// If the remote cache couldn't connect, the server might not
			// be running.
			unset($this->remoteCache);
			add_action('shutdown', array(
				        &$this,
				        'startServer'
				    ));

			return false;
		}
				
		return true;
	}
	
	public function startServer()
	{
		$startTime = get_site_option('spicypixel.objectcache.serverstarttime');
		if(!empty($startTime)) {
			if(time() < ($startTime + self::SERVER_RETRY_DELAY)) {
				return false;
			}
		}
		
		$currentTime = time();
		
		if ( defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON ) {
			if ( !empty($_POST) || defined('DOING_AJAX') )
				return false;

			update_site_option('spicypixel.objectcache.serverstarttime', $currentTime);

			ob_start();
			wp_redirect( add_query_arg('doing_start_oc_server', $currentTime, stripslashes($_SERVER['REQUEST_URI'])) );
			echo ' ';

			// flush any buffers and send the headers
			while ( @ob_end_flush() );
			flush();

			WP_DEBUG ? include_once( SPOC_DIR . '/Cache/CacheServerDaemon.php' ) : @include_once( SPOC_DIR . '/Cache/CacheServerDaemon.php' );
			return true;
		}
		update_site_option('spicypixel.objectcache.serverstarttime', $currentTime);

		$cron_url = get_option( 'siteurl' ) . '/wp-content/plugins/spicypixel-objectcache/ObjectCacheServer.php?doing_start_oc_server=' . $currentTime;
		wp_remote_post( $cron_url, array('timeout' => 0.01, 'blocking' => false, 'sslverify' => apply_filters('https_local_ssl_verify', true)) );
		return true;
	}
	
	private function closeRemoteCache()
	{
		if(!isset($this->remoteCache))
			return;
			
		$this->remoteCache->close();
		unset($this->remoteCache);
	}

	/**
	 * Sets up object properties; PHP 5 style constructor
	 *
	 * @since 2.0.8
	 * @return null|WP_Object_Cache If cache is disabled, returns null.
	 */
	function __construct() {
		$this->memoryCache = new SpicyPixel_MemoryObjectCache();
		
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
		$this->closeRemoteCache();
		return true;
	}
	
	public function close() {
		$this->closeRemoteCache();
	}
}
	
?>