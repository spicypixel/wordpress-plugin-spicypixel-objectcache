Spicy Pixel Object Cache
------------------------
This package is an object cache designed to improve performance for PHP applications
like WordPress but only in very specific deployments. It caches objects in memory during 
page requests and communicates with a persistent memory cache that remains valid across 
multiple requests.

See: http://codex.wordpress.org/Class_Reference/WP_Object_Cache

Most production environments are better served by one of the following if available:
 - memcached: http://memcached.org/
 - Alternative PHP Cache (APC): http://php.net/manual/en/book.apc.php
 - WinCache: http://www.iis.net/download/wincacheforphp

In some ways, this package is like a simpler memcached but written entirely in PHP. 

WordPress Plugin
----------------
The WordPress plugin for this cache has a few practical limitations:
 - Requires sockets (though there is a partial named pipe implementation for POSIX)
 - Requires configurable CGI timeouts when auto-starting the server

For the WordPress plugin, the first time the cache is accessed it attempts to
connect to the server and start it if it is not running by using the same method
as the WordPress cron service. Because the server in these circumstances is started
via a web worker and not a system process it is subject to CGI timeout values.
Consider using system cron or other means to start the server in advance for better 
results.

To install the plugin, place the contents of this package in the
wp-content/plugins/spicypixel-objectcache folder and use the WordPress admin panel
to activate.