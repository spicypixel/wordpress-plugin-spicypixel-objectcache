<?php

if (!defined('ABSPATH')) {
    die();
}

define('SPOC', true);
defined('SPOC_DIR') || define('SPOC_DIR', realpath(dirname(__FILE__)));
defined('WP_CONTENT_DIR') || define('WP_CONTENT_DIR', realpath(SPOC_DIR . '/../..'));

define('SPOC_FILE', 'spicypixel-objectcache/ObjectCachePlugin.php');

define('SPOC_INSTALL_DIR', SPOC_DIR . '/Install');
define('SPOC_INSTALL_OBJECT_CACHE', SPOC_INSTALL_DIR . '/object-cache.php');
define('SPOC_ADDIN_OBJECT_CACHE', WP_CONTENT_DIR . '/object-cache.php');

/**
 * Returns current blog ID
 *
 * @return integer
 */
function sp_get_blog_id() {
    return (isset($GLOBALS['blog_id']) ? (int) $GLOBALS['blog_id'] : 0);
}

/**
 * Returns true if it's WPMU
 *
 * @return boolean
 */
function sp_is_wpmu() {
    static $wpmu = null;

    if ($wpmu === null) {
        $wpmu = file_exists(ABSPATH . 'wpmu-settings.php');
    }

    return $wpmu;
}

/**
 * Returns true if WPMU uses vhosts
 *
 * @return boolean
 */
function sp_is_subdomain_install() {
    return ((defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL) || (defined('VHOST') && VHOST == 'yes'));
}

/**
 * Returns true if it's WP with enabled Network mode
 *
 * @return boolean
 */
function sp_is_multisite() {
    static $multisite = null;

    if ($multisite === null) {
        $multisite = ((defined('MULTISITE') && MULTISITE) || defined('SUNRISE') || sp_is_subdomain_install());
    }

    return $multisite;
}

/**
 * Returns if there is multisite mode
 *
 * @return boolean
 */
function sp_is_network() {
    return (sp_is_wpmu() || sp_is_multisite());
}

/**
 * Returns server hostname
 *
 * @return string
 */
function sp_get_host() {
    static $host = null;

    if ($host === null) {
        $host = (!empty($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['HTTP_HOST']);
    }

    return $host;
}

/**
 * Fatal error
 *
 * @param string $error
 * @return void
 */
function sp_error($error) {
    include SPOC_DIR . '/Error.php';
    exit();
}

?>