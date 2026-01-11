<?php

/**
 * Determine whether MSBD Logs debug mode is enabled.
 *
 * This checks the stored option `MSBD_LOGS_ENABLE_DEBUG_OPTION`.
 * - If the option value is `1`, debug *is enabled*, else debug *is disabled*.
 *
 * @since 1.0.0
 *
 * @return bool True if debug mode is enabled, false if disabled.
 */
function msbd_logs_is_debug()
{
    // Retrieve stored value (expected 0 or 1)
    $value = get_option(MSBD_LOGS_ENABLE_DEBUG_OPTION, 0);

    // Ensure numeric comparison (absint avoids negative or unexpected values)
    $value = absint($value);

    // Debug is ON only when the stored value is one (1)
    return $value === 1;
}

/**
 * Return absolute logs directory path. Ensures it exists and contains index.html.
 *
 * @return string|false Absolute path or false on failure.
 */
function msbd_logs_get_logs_dir()
{
    static $cached = null;

    if (null !== $cached) {
        return $cached;
    }

    $upload_dir = wp_upload_dir();

    if (empty($upload_dir['basedir'])) {
        $cached = false;
        return false;
    }

    $logs_dir = trailingslashit($upload_dir['basedir']) . 'logs';

    if (! is_dir($logs_dir)) {
        // Try making the directory (best-effort)
        if (! wp_mkdir_p($logs_dir)) {
            $cached = false;
            return false;
        }
    }

    // Add index.html for safety (silence)
    $index_file = trailingslashit($logs_dir) . 'index.html';
    if (! file_exists($index_file)) {
        @file_put_contents($index_file, '<!-- Silence is golden -->');
    }

    $cached = $logs_dir;
    return $logs_dir;
}

/**
 * Build new log filename (absolute path) for a given log type.
 *
 * @return string|false Absolute file path or false.
 */
function msbd_logs_new_file($log_type = 'debug')
{
    $log_type = strtolower(trim($log_type));

    if (! in_array($log_type, ['debug', 'attention'], true)) {
        $log_type = 'debug';
    }

    $logs_dir = msbd_logs_get_logs_dir();

    if (false === $logs_dir) {
        return false;
    }

    $filename    = sprintf('%s-%s.log', $log_type, gmdate('Ymd'));

    return trailingslashit($logs_dir) . $filename;
}


/**
 * Add admin menu item.
 */
add_action('admin_menu', 'msbd_logs_admin_menu');

function msbd_logs_admin_menu()
{
    add_menu_page(
        __('Monitor and Manage Logs', 'msbd-logs'),
        __('MSBD Logs', 'msbd-logs'),
        'manage_options',
        'msbd-logs',
        'msbd_logs_admin_page',
        'dashicons-list-view',
        69
    );
}

/**
 * Handle admin actions securely.
 */
add_action('admin_init', 'msbd_logs_handle_actions');

function msbd_logs_handle_actions()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $logs_dir = msbd_logs_get_logs_dir();
    if (!$logs_dir) {
        return;
    }

    /* -------------------------
     * Save debug setting (POST)
     * ------------------------- */
    if (
        isset($_POST['msbd_logs_debug_setting_submit'], $_POST['_msbd_logs_debug_nonce']) &&
        wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['_msbd_logs_debug_nonce'])),
            'msbd_logs_debug_setting'
        )
    ) {
        $is_debug = isset($_POST['msbd_logs_enable_debug']) ? 1 : 0;
        update_option(MSBD_LOGS_ENABLE_DEBUG_OPTION, $is_debug);
    }

    /* -------------------------
     * Delete log file (POST)
     * ------------------------- */
    if (
        isset($_POST['msbd_logs_action'], $_POST['file'], $_POST['_msbd_logs_delete_nonce']) &&
        $_POST['msbd_logs_action'] === 'delete'
    ) {
        $file = sanitize_file_name(wp_unslash($_POST['file']));

        if (!wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['_msbd_logs_delete_nonce'])),
            'msbd_logs_delete_' . $file
        )) {
            wp_die(__('Security check failed.', 'msbd-logs'));
        }

        $filepath = realpath(trailingslashit($logs_dir) . $file);
        $logs_dir_real = realpath($logs_dir);

        if (
            $filepath &&
            $logs_dir_real &&
            strpos($filepath, $logs_dir_real) === 0 &&
            is_file($filepath)
        ) {
            unlink($filepath);
        }

        wp_safe_redirect(
            remove_query_arg(array('msbd_logs_action', 'file'))
        );
        exit;
    }
}

/**
 * Admin page rendering.
 */
function msbd_logs_admin_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions', 'msbd-logs'));
    }

    $logs_dir = msbd_logs_get_logs_dir();
    if (!$logs_dir) {
        echo '<div class="notice notice-error"><p>' .
            esc_html__('Uploads/logs directory is not accessible.', 'msbd-logs') .
            '</p></div>';
        return;
    }

    $search = isset($_GET['s'])
        ? sanitize_text_field(wp_unslash($_GET['s']))
        : '';

    $files = msbd_logs_get_files_list($logs_dir, $search);

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('MSBD Logs', 'msbd-logs') . '</h1>';
    echo '<p class="description">' .
        esc_html__('View and manage log files stored in wp-content/uploads/logs/.', 'msbd-logs') .
        '</p>';

    /* -------------------------
     * Debug toggle
     * ------------------------- */
    $is_debug = (int) get_option(MSBD_LOGS_ENABLE_DEBUG_OPTION, 0);

    echo '<h2 style="margin-top:18px;">' . esc_html__('Log Types', 'msbd-logs') . '</h2>';
    echo '<form method="post" style="margin-bottom:18px;">';
    wp_nonce_field('msbd_logs_debug_setting', '_msbd_logs_debug_nonce');
    echo '<label style="display:inline-flex;align-items:center;">';
    echo '<input type="checkbox" name="msbd_logs_enable_debug" value="1" ' .
        checked(1, $is_debug, false) .
        ' style="margin-right:8px;" />';
    echo '<strong>' .
        esc_html__('Enable "debug" mode (all developer logs will be recorded)', 'msbd-logs') .
        '</strong>';
    echo '</label> ';
    echo '<input type="submit" name="msbd_logs_debug_setting_submit" class="button" value="' .
        esc_attr__('Save', 'msbd-logs') .
        '" style="margin-left:12px;" />';
    echo '<p class="description" style="margin-top:6px;color:#666;">' .
        esc_html__('When disabled, "debug" logs will not be recorded.', 'msbd-logs') .
        '</p>';
    echo '</form>';

    /* -------------------------
     * Filter form
     * ------------------------- */
    echo '<form method="get" style="margin-bottom:16px;">';
    echo '<input type="hidden" name="page" value="msbd-logs" />';
    echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="' .
        esc_attr__('Search filenames', 'msbd-logs') . '" />';
    echo ' <input type="submit" class="button" value="' .
        esc_attr__('Filter', 'msbd-logs') . '" />';
    echo ' <a class="button" href="' .
        esc_url(remove_query_arg(array('s'))) .
        '">' . esc_html__('Reset', 'msbd-logs') . '</a>';
    echo '</form>';

    if (empty($files)) {
        echo '<p>' . esc_html__('No log files found.', 'msbd-logs') . '</p>';
        echo '</div>';
        return;
    }

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Filename', 'msbd-logs') . '</th>';
    echo '<th style="width:120px;">' . esc_html__('Type', 'msbd-logs') . '</th>';
    echo '<th style="width:120px;">' . esc_html__('Size', 'msbd-logs') . '</th>';
    echo '<th style="width:160px;">' . esc_html__('Modified', 'msbd-logs') . '</th>';
    echo '<th style="width:200px;">' . esc_html__('Actions', 'msbd-logs') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($files as $file) {
        $basename = basename($file);
        $type = (strpos($basename, 'debug-') === 0) ? 'debug' : ((strpos($basename, 'attention-') === 0) ? 'attention' : 'unknown');

        echo '<tr>';
        echo '<td><strong>' . esc_html($basename) . '</strong></td>';
        echo '<td>' . esc_html($type) . '</td>';
        echo '<td>' . esc_html(size_format(filesize($file))) . '</td>';
        echo '<td>' . esc_html(date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            filemtime($file)
        )) . '</td>';
        echo '<td>';

        /* View link (read-only) */
        $view_url = add_query_arg(array(
            'page' => 'msbd-logs',
            'msbd_logs_view' => rawurlencode($basename),
        ), admin_url('admin.php'));

        echo '<a class="button button-small" href="' . esc_url($view_url) . '">' .
            esc_html__('View', 'msbd-logs') . '</a> ';

        /* Delete form (POST + nonce) */
        echo '<form method="post" style="display:inline;">';
        echo '<input type="hidden" name="msbd_logs_action" value="delete">';
        echo '<input type="hidden" name="file" value="' . esc_attr($basename) . '">';
        wp_nonce_field('msbd_logs_delete_' . $basename, '_msbd_logs_delete_nonce');
        echo '<button type="submit" class="button button-small" onclick="return confirm(\'' .
            esc_js(__('Are you sure you want to permanently delete this file?', 'msbd-logs')) .
            '\');">' . esc_html__('Delete', 'msbd-logs') . '</button>';
        echo '</form>';

        echo '</td></tr>';
    }

    echo '</tbody></table>';

    /* -------------------------
     * View file contents
     * ------------------------- */
    if (isset($_GET['msbd_logs_view'])) {
        $file = sanitize_file_name(wp_unslash($_GET['msbd_logs_view']));
        $filepath = realpath(trailingslashit($logs_dir) . $file);
        $logs_dir_real = realpath($logs_dir);

        if (
            $filepath &&
            $logs_dir_real &&
            strpos($filepath, $logs_dir_real) === 0 &&
            is_readable($filepath)
        ) {
            echo '<h2 style="margin-top:24px;">' .
                sprintf(esc_html__('Viewing: %s', 'msbd-logs'), esc_html($file)) .
                '</h2>';
            echo '<div style="background:#fff;border:1px solid #ddd;padding:12px;max-height:420px;overflow:auto;white-space:pre;font-family:monospace;">';
            echo esc_html(file_get_contents($filepath));
            echo '</div>';
        } else {
            echo '<div class="notice notice-error"><p>' .
                esc_html__('Unable to read the selected file.', 'msbd-logs') .
                '</p></div>';
        }
    }

    echo '</div>';
}


/**
 * Handle delete action (nonce protected).
 */
function msbd_logs_handle_delete_action()
{
    if (! is_admin() || ! current_user_can('manage_options')) {
        return;
    }

    if (
        ! isset($_GET['page'], $_GET['msbd_logs_action'], $_GET['file']) ||
        $_GET['page'] !== 'msbd-logs' ||
        $_GET['msbd_logs_action'] !== 'delete'
    ) {
        return;
    }

    $basename = sanitize_file_name(wp_unslash($_GET['file']));
    $nonce    = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';

    if (! wp_verify_nonce($nonce, 'msbd_logs_delete_' . $basename)) {
        wp_safe_redirect(
            add_query_arg(
                'msbd_logs_notice',
                'nonce_error',
                remove_query_arg(array('msbd_logs_action', '_wpnonce', 'file'))
            )
        );

        exit;
    }

    $logs_dir = msbd_logs_get_logs_dir();
    $status   = 'not_found';

    if ($logs_dir) {
        $filepath = trailingslashit($logs_dir) . $basename;

        if (is_file($filepath)) {
            if (is_writable($filepath) || @chmod($filepath, 0644)) {
                @unlink($filepath);
                $status = 'deleted';
            } else {
                $status = 'permission_error';
            }
        }
    }

    $redirect = add_query_arg(
        array(
            'msbd_logs_notice' => $status,
            'file'             => rawurlencode($basename),
        ),
        remove_query_arg(array('msbd_logs_action', '_wpnonce', 'file', 'msbd_logs_view'))
    );

    wp_safe_redirect($redirect);
    exit;
}

add_action('admin_init', 'msbd_logs_handle_delete_action');
