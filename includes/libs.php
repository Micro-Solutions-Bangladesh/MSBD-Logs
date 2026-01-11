<?php


add_action( 'admin_notices', 'msbd_logs_admin_notices' );

/**
 * Display admin notices based on URL parameters.
 */
function msbd_logs_admin_notices() {

    if ( empty( $_GET['msbd_logs_notice'] ) ) {
        return;
    }

    $notice = sanitize_key( $_GET['msbd_logs_notice'] );
    $file   = isset( $_GET['file'] ) ? sanitize_text_field( wp_unslash( $_GET['file'] ) ) : '';

    switch ( $notice ) {
        case 'deleted':
            echo '<div class="notice notice-success"><p>' .
                 sprintf( esc_html__( 'Deleted %s', 'msbd-logs' ), esc_html( $file ) ) .
                 '</p></div>';
            break;

        case 'not_found':
            echo '<div class="notice notice-warning"><p>' .
                 sprintf( esc_html__( 'File not found: %s', 'msbd-logs' ), esc_html( $file ) ) .
                 '</p></div>';
            break;

        case 'permission_error':
            echo '<div class="notice notice-error"><p>' .
                 esc_html__( 'Unable to delete the file (permission issue).', 'msbd-logs' ) .
                 '</p></div>';
            break;

        case 'nonce_error':
            echo '<div class="notice notice-error"><p>' .
                 esc_html__( 'Security check failed. File not deleted.', 'msbd-logs' ) .
                 '</p></div>';
            break;
    }
}


/**
 * Return absolute path of the latest log file for a given type.
 *
 * @param string $type 'debug'|'attention'
 * @return string|false
 */
function msbd_logs_latest_file($type = 'debug')
{
    $type = in_array($type, array('debug', 'attention'), true) ? $type : 'debug';
    $logs_dir = msbd_logs_get_logs_dir();

    if (! $logs_dir) {
        return false;
    }

    $pattern = sprintf('%s%s-*.log', trailingslashit($logs_dir), $type);
    $matches = glob($pattern);

    if (! $matches) {
        return false;
    }

    usort($matches, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    return $matches[0];
}



/**
 * Get an array of log files (absolute paths), optionally filtered by search.
 *
 * @param string $logs_dir Absolute path to logs directory.
 * @param string $search Optional search string in filename.
 * @return array
 */
function msbd_logs_get_files_list($logs_dir, $search = '')
{
    $pattern = trailingslashit($logs_dir) . '*.log';
    $all     = glob($pattern) ?: array();

    // filter
    $filtered = array_filter($all, function ($path) use ($search) {
        $name = basename($path);

        if ($search) {
            if (false === stripos($name, $search)) {
                return false;
            }
        }

        return true;
    });

    usort($filtered, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    return $filtered;
}
