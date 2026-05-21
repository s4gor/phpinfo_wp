<?php
/**
 * phpinfo() WP — uninstall cleanup.
 *
 * Fires once when the plugin is deleted via the WP admin (not on deactivate).
 * Remove the safemode mu-plugin we may have installed, clear our options and
 * transients, and tidy up legacy cache files.
 */
defined('WP_UNINSTALL_PLUGIN') or die();

// 1. Remove the troubleshooting-mode mu-plugin if present.
$mu_dir = defined('WPMU_PLUGIN_DIR') && WPMU_PLUGIN_DIR
    ? WPMU_PLUGIN_DIR
    : WP_CONTENT_DIR . '/mu-plugins';
$mu_file = $mu_dir . '/phpinfowp-safemode.php';
if (file_exists($mu_file)) @unlink($mu_file);

// 2. Drop our options.
$options = [
    'phpinfowp_compat_result',
    'phpinfowp_ssl_domains',
    'phpinfowp_last_unhealthy_ts',
    'phpinfowp_opcache_baseline_hit',
    'phpinfowp_install_ts',
];
foreach ($options as $opt) delete_option($opt);

// 3. Drop our transients (peak-memory daily keys, safemode session tokens,
//    SSL cache per host, security-headers cache).
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '\\_transient\\_phpinfowp\\_%'
        OR option_name LIKE '\\_transient\\_timeout\\_phpinfowp\\_%'"
);

// 4. Drop snapshots table (Pro feature).
$table = $wpdb->prefix . 'phpinfowp_snapshots';
$wpdb->query("DROP TABLE IF EXISTS {$table}");

// 5. Legacy cache files from older versions.
@unlink('../htaccess.txt');
@unlink('../htaccess-phpinfo.txt');
@unlink('../userini-phpinfo.txt');

// 6. Clear scheduled events.
wp_clear_scheduled_hook('phpinfowp_license_ping');
wp_clear_scheduled_hook('phpinfowp_weekly_maintenance');
