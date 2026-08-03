<?php

/**
 * Debug helpers. Unused in actions
 */

/**
 * Simple debug trace to wp-content/debug.log
 *
 * @usage _log( $var );
 */
if (! function_exists('_log')) {
    function _log($log)
    {
        if (! defined('WP_DEBUG_LOG') || ! WP_DEBUG_LOG) {
            return '';
        }

        $file = WP_CONTENT_DIR.'/debug.log';
        $maxSize = 5 * 1024 * 1024;
        if (is_file($file) && filesize($file) >= $maxSize) {
            $rotatedFile = $file.'.1';
            if (is_file($rotatedFile)) {
                unlink($rotatedFile);
            }
            rename($file, $rotatedFile);
        }

        ob_start();
        echo '['.date('d-M-Y h:i:s T').'] ';
        var_export($log);
        echo "\r\n";
        $data = ob_get_clean();
        file_put_contents($file, $data, FILE_APPEND | LOCK_EX);

        return $data;
    }
}

if (! function_exists('console_log')) {
    function console_log($data)
    {

        global $wp_query, $current_user;

        _log($data);

        if (is_bool($data)) {
            $data = (int) $data;
        }

        if (empty($data)) {
            $data = 'null';
        }

        if (! $wp_query) {
            return;
        }

        $wp_query->debug_log = $data;
        $wp_query->debug_showed = false;

        if (
            (defined('WP_DEBUG') && WP_DEBUG) &&
            ($current_user instanceof WP_User && $current_user->has_cap('manage_options'))
        ) {
            add_action('admin_head', 'show_in_console');
            add_action('admin_footer', 'show_in_console', 99);
            add_action('wp_head', 'show_in_console', 99);
            add_action('wp_footer', 'show_in_console');
        }
    }

    function show_in_console()
    {

        global $wp_query;

        if (! $wp_query->debug_showed) {

            if (! is_string($wp_query->debug_log)) {
                $wp_query->debug_log = json_encode($wp_query->debug_log, true);
            } else {
                $wp_query->debug_log = "'$wp_query->debug_log'";
            }

            echo '<script type="text/javascript" name="woo2iiko_debugger">console.log({debug: \'wp-addon\'}, '.$wp_query->debug_log.')</script>';
            $wp_query->debug_showed = true;

            echo '<hr><h5 style="color:red;">DEBUG INFO</h5>';
            echo '<pre style="color:white; background: #0a0a0a; padding: 20px;">'.$wp_query->debug_log.'</pre>';
        }
    }
}

if (! function_exists('dump')) {
    function dump(...$data)
    {
        ob_start();
        var_dump($data);
        $record = ob_get_clean();

        echo <<< EOT
            <pre>$record</pre>
EOT;
    }
}
