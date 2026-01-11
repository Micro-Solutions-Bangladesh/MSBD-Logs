<?php

/**
 * Plugin Name: MSBD Logs
 * Plugin URI: https://microsolutionsbd.com/wp-plugin-msbd-logs/2026/
 * Description: Simple logging helper for plugin & theme developers with an admin UI for reviewing logs stored in wp-content/uploads/logs/.
 * Version: 1.0.1
 * Author: Micro Solutions BD
 * Author URI: https://microsolutionsbd.com/
 * Text Domain: msbd-logs
 * Domain Path: /languages
 *
 * License: GPLv2 or later
 *
 * Usage (anywhere in WP code):
 *   msbd_logs_create( 'Debug something' );
 *   msbd_logs_create( 'Unexpected issue detected', 'attention' );
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Define constants.
 */
define('MSBD_LOGS_VERSION', '1.0.1');
define('MSBD_LOGS_ENABLE_DEBUG_OPTION', 'msbd_logs_enable_debug');


require_once __DIR__ . '/includes/libs.php';
require_once __DIR__ . '/includes/functions.php';


/**
 * Plugin activation hook.
 */
register_activation_hook(__FILE__, 'msbd_logs_activate');

function msbd_logs_activate()
{
    // Save the first time of installation
    $installed = get_option('msbd_logs_installed_time');

    if (! $installed) {
        update_option('msbd_logs_installed_time', time());
    }

    // Ensure debug log setting exists
    if (false === get_option(MSBD_LOGS_ENABLE_DEBUG_OPTION, false)) {
        update_option(MSBD_LOGS_ENABLE_DEBUG_OPTION, 1);
    }

    // Ensure logs directory exists on activation.
    msbd_logs_get_logs_dir();

    return;
}

/**
 * Logs messages to /uploads/logs/ directory.
 *
 * @param string $message  The message to log.
 * @param string $logtype Optional. 'debug' (log only when MSBD_LOGS_ENABLE_DEBUG_OPTION is 1/true) or 'attention' (always log the message). Default 'debug'.
 *
 * @return bool true on success, false on failure.
 */
function msbd_logs_create(string $message, string $logtype = 'debug'): bool
{
    if ('debug' === $logtype && msbd_logs_is_debug() === false) {
        // Debug logging is disabled; do not log.
        return false;
    }

    $log_file = msbd_logs_new_file($logtype);

    if (! $log_file) {
        return false;
    }

    // Prepare message
    $timestamp = gmdate('Y-m-d H:i:s');
    $formatted = sprintf("[%s] %s%s", $timestamp, trim($message), PHP_EOL);

    // Ensure logs dir is writable; try to set perms as a best-effort fallback.
    $logs_dir = dirname($log_file);

    if (! is_writable($logs_dir)) {
        @chmod($logs_dir, 0755);
    }

    // Write atomically with exclusive lock
    $result = @file_put_contents($log_file, $formatted, FILE_APPEND | LOCK_EX);

    return (false !== $result);
}


/**
 * Add a footer link under the plugin description on the Plugins page.
 *
 * @param array  $links Existing plugin meta links.
 * @param string $file  Plugin file path.
 * @return array Modified meta links.
 */
add_filter('plugin_row_meta', function ($links, $file) {

    if (plugin_basename(__FILE__) === $file) {
        $links[] = sprintf(
            '<a href="%s"><strong>%s</strong></a>',
            esc_url(admin_url('admin.php?page=msbd-logs')),
            esc_html__('MSBD Logs', 'msbd-logs')
        );
    }

    return $links;
}, 10, 2);
