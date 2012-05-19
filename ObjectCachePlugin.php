<?php
   /*
   Plugin Name: Spicy Pixel Object Cache
   Plugin URI: http://spicypixel.com/
   Description: A persistent memory cache.
   Version: 1.0.0
   Author: Spicy Pixel
   Author URI: http://spicypixel.com
   */

defined('SPOC_DIR') || define('SPOC_DIR', realpath(dirname(__FILE__)));
require_once(SPOC_DIR . '/Define.php');

class SpicyPixel_ObjectCache_Plugin {
	
    /**
     * Runs plugin
     */
    function run() {
	    register_activation_hook(SPOC_FILE, array(
	        &$this,
	        'activate'
	    ));

	    register_deactivation_hook(SPOC_FILE, array(
	        &$this,
	        'deactivate'
	    ));
	
		add_action('shutdown', array(
	        &$this,
	        'echo_stats_comment'
	    ));

        add_action('publish_phone', array(
            &$this,
            'on_change'
        ), 0);

        add_action('publish_post', array(
            &$this,
            'on_change'
        ), 0);

        add_action('edit_post', array(
            &$this,
            'on_change'
        ), 0);

        add_action('delete_post', array(
            &$this,
            'on_change'
        ), 0);

        add_action('comment_post', array(
            &$this,
            'on_change'
        ), 0);

        add_action('edit_comment', array(
            &$this,
            'on_change'
        ), 0);

        add_action('delete_comment', array(
            &$this,
            'on_change'
        ), 0);

        add_action('wp_set_comment_status', array(
            &$this,
            'on_change'
        ), 0);

        add_action('trackback_post', array(
            &$this,
            'on_change'
        ), 0);

        add_action('pingback_post', array(
            &$this,
            'on_change'
        ), 0);

        add_action('switch_theme', array(
            &$this,
            'on_change'
        ), 0);

        add_action('edit_user_profile_update', array(
            &$this,
            'on_change'
        ), 0);
    }

	function echo_stats_comment() {
		global $wp_object_cache;
		
		if(!isset($wp_object_cache))
			return;
		
		// TODO: Make this a toggle
		echo "\n<!-- Spicy Pixel Object Cache\n";
		if(method_exists($wp_object_cache, 'stats_comment'))
			$wp_object_cache->stats_comment();
		echo "-->\n";
	}

 	/**
     * Check if plugin is locked
     *
     * @return boolean
     */
    function locked() {
        static $locked = null;

        if ($locked === null) {
            if (sp_is_network() && function_exists('get_blog_list')) {
                global $blog_id;

                $blogs = get_blog_list();

                foreach ($blogs as $blog) {
                    if ($blog['blog_id'] != $blog_id) {
                        $active_plugins = get_blog_option($blog['blog_id'], 'active_plugins');

                        if (in_array(SPOC_FILE, $active_plugins)) {
                            $locked = true;
                            break;
                        }
                    }
                }
            } 
			else {
                $locked = false;
            }
        }

        return $locked;
    }

    /**
     * Activate plugin
     */
    function activate() {
        if (!$this->locked() && !@copy(SPOC_INSTALL_OBJECT_CACHE, SPOC_ADDIN_OBJECT_CACHE)) {
			sp_error("Unable to copy " . SPOC_INSTALL_OBJECT_CACHE . " to " . SPOC_ADDIN_OBJECT_CACHE);
        }
    }

    /**
     * Deactivate plugin
     */
    function deactivate() {
        if (!$this->locked()) {
            @unlink(SPOC_ADDIN_OBJECT_CACHE);
        }
    }

    /**
     * Change action
     */
    function on_change() {
		// TODO: flush cache
    }
}

$spoc_plugin = new SpicyPixel_ObjectCache_Plugin();
$spoc_plugin->run();

?>