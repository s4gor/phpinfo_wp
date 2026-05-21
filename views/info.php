<?php
defined('ABSPATH') or die('Unauthorized Access');

global $wpdb;

function phpinfowp_fmt_bytes(int $bytes): string {
    if ($bytes >= GB_IN_BYTES) return round($bytes / GB_IN_BYTES, 2) . ' GB';
    if ($bytes >= MB_IN_BYTES) return round($bytes / MB_IN_BYTES, 1) . ' MB';
    if ($bytes >= KB_IN_BYTES) return round($bytes / KB_IN_BYTES, 1) . ' KB';
    return $bytes . ' B';
}

function phpinfowp_dirsize_safe(string $path): string {
    if (!is_dir($path)) return '—';
    $size = get_dirsize($path);
    return $size ? phpinfowp_fmt_bytes((int)$size) : '—';
}

// EOL status for the PHP badge
$eol = Phpinfo_WP_EOL::status();
$eol_colors = ['ok' => '#00a32a', 'warning' => '#996800', 'eol' => '#d63638', 'unknown' => '#666'];
$eol_color  = $eol_colors[$eol['status']] ?? '#666';

// Memory
$mem_limit   = ini_get('memory_limit');
$mem_used    = memory_get_usage(true);
$mem_limit_b = wp_convert_hr_to_bytes($mem_limit);
$mem_pct     = $mem_limit_b > 0 ? round($mem_used / $mem_limit_b * 100) : 0;

// DB
$db_version  = $wpdb->get_var('SELECT VERSION()') ?? '—';
$db_size_raw = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = %s",
    DB_NAME
));
$db_size = $db_size_raw ? phpinfowp_fmt_bytes((int)$db_size_raw) : '—';

// cURL
$curl_ver = function_exists('curl_version') ? (curl_version()['version'] ?? '—') : 'not loaded';

// Server
$server_soft = $_SERVER['SERVER_SOFTWARE'] ?? '—';
?>

<div class="phpinfowp-info-page">

  <div class="phpinfowp-page-header">
    <div>
      <h1>Server Overview</h1>
      <p class="phpinfowp-page-subtitle">PHP, WordPress, server, and database environment at a glance</p>
    </div>
  </div>

  <div class="phpinfowp-info-grid">

    <!-- PHP -->
    <div class="phpinfowp-info-card" style="border-top:3px solid <?php echo $eol_color; ?>">
      <div class="phpinfowp-info-card-label">PHP Version</div>
      <div class="phpinfowp-info-card-value"><?php echo esc_html(PHP_VERSION); ?></div>
      <div class="phpinfowp-info-card-sub">
        <span style="display:inline-block;padding:2px 7px;border-radius:3px;font-size:10px;font-weight:700;letter-spacing:.4px;background:<?php echo $eol_color; ?>;color:#fff">
          <?php
          if ($eol['status'] === 'eol')     echo 'END OF LIFE';
          elseif ($eol['status'] === 'warning') echo 'EOL SOON';
          else echo 'SUPPORTED';
          ?>
        </span>
        <div style="margin-top:4px;color:<?php echo $eol_color; ?>">
          <?php
          if ($eol['status'] === 'eol') echo 'Upgrade immediately';
          elseif ($eol['status'] === 'warning') echo 'EOL in ' . $eol['days'] . 'd — ' . $eol['eol'];
          elseif ($eol['status'] === 'ok') echo 'Until ' . $eol['eol'];
          else echo 'EOL date unknown';
          ?>
        </div>
      </div>
    </div>

    <!-- WordPress -->
    <div class="phpinfowp-info-card" style="border-top:3px solid #2271b1">
      <div class="phpinfowp-info-card-label">WordPress</div>
      <div class="phpinfowp-info-card-value"><?php echo esc_html(get_bloginfo('version')); ?></div>
      <div class="phpinfowp-info-card-sub">
        <?php echo is_multisite() ? 'Multisite network' : 'Single site'; ?><br>
        <?php echo count(get_option('active_plugins')); ?> active plugins
      </div>
    </div>

    <!-- Memory -->
    <div class="phpinfowp-info-card" style="border-top:3px solid <?php echo $mem_pct > 85 ? '#d63638' : '#777BB3'; ?>">
      <div class="phpinfowp-info-card-label">Memory Usage</div>
      <div class="phpinfowp-info-card-value"><?php echo esc_html(phpinfowp_fmt_bytes($mem_used)); ?></div>
      <div class="phpinfowp-info-card-sub">
        of <?php echo esc_html($mem_limit); ?> limit (<?php echo $mem_pct; ?>%)
        <div class="phpinfowp-mini-bar-wrap">
          <div class="phpinfowp-mini-bar" style="width:<?php echo min(100,$mem_pct); ?>%;background:<?php echo $mem_pct > 85 ? '#d63638' : '#777BB3'; ?>"></div>
        </div>
      </div>
    </div>

    <!-- Database -->
    <div class="phpinfowp-info-card" style="border-top:3px solid #00a32a">
      <div class="phpinfowp-info-card-label">Database</div>
      <div class="phpinfowp-info-card-value" style="font-size:18px"><?php echo esc_html($db_version); ?></div>
      <div class="phpinfowp-info-card-sub">
        <?php echo esc_html(DB_NAME); ?><br>
        <?php echo esc_html($db_size); ?> total size
      </div>
    </div>

  </div>

  <h2 class="phpinfowp-section-heading">Environment Details</h2>
  <table class="wp-list-table widefat fixed striped phpinfowp-info-table">
    <tbody>

      <tr><th colspan="2" class="phpinfowp-info-section-head">WordPress</th></tr>
      <tr><td>Site URL</td><td><code><?php echo esc_html(get_site_url()); ?></code></td></tr>
      <tr><td>Home URL</td><td><code><?php echo esc_html(get_home_url()); ?></code></td></tr>
      <tr><td>WP Version</td><td><?php echo esc_html(get_bloginfo('version')); ?></td></tr>
      <tr><td>Active Theme</td><td><?php echo esc_html(wp_get_theme()->get('Name')); ?> <?php echo esc_html(wp_get_theme()->get('Version')); ?></td></tr>
      <tr><td>Active Plugins</td><td><?php echo count(get_option('active_plugins')); ?> of <?php echo count(get_plugins()); ?> installed</td></tr>
      <tr><td>Active Themes</td><td><?php echo count(wp_get_themes()); ?> installed</td></tr>
      <tr><td>Debug Mode</td>
          <td><?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                <span style="color:#d63638;font-weight:600">ON</span> — disable in production
              <?php else: ?>
                <span style="color:#00a32a">Off</span>
              <?php endif; ?></td></tr>
      <tr><td>Admin Email</td><td><?php echo esc_html(get_option('admin_email')); ?></td></tr>

      <tr><th colspan="2" class="phpinfowp-info-section-head">PHP</th></tr>
      <tr><td>PHP Version</td><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
      <tr><td>PHP SAPI</td><td><?php echo esc_html(PHP_SAPI); ?></td></tr>
      <tr><td>Memory Limit</td><td><?php echo esc_html(ini_get('memory_limit')); ?></td></tr>
      <tr><td>Max Execution Time</td><td><?php echo esc_html(ini_get('max_execution_time')); ?>s</td></tr>
      <tr><td>Upload Max Filesize</td><td><?php echo esc_html(ini_get('upload_max_filesize')); ?></td></tr>
      <tr><td>Post Max Size</td><td><?php echo esc_html(ini_get('post_max_size')); ?></td></tr>
      <tr><td>Max Input Vars</td><td><?php echo esc_html(ini_get('max_input_vars')); ?></td></tr>
      <tr><td>Display Errors</td><td><?php echo ini_get('display_errors') ? '<span style="color:#d63638">On</span>' : '<span style="color:#00a32a">Off</span>'; ?></td></tr>
      <tr><td>cURL Version</td><td><?php echo esc_html($curl_ver); ?></td></tr>
      <tr><td>Loaded Extensions</td><td><?php echo count(get_loaded_extensions()); ?></td></tr>

      <tr><th colspan="2" class="phpinfowp-info-section-head">Server</th></tr>
      <tr><td>Server Software</td><td><?php echo esc_html($server_soft); ?></td></tr>
      <tr><td>Server Name</td><td><?php echo esc_html($_SERVER['SERVER_NAME'] ?? '—'); ?></td></tr>
      <tr><td>Document Root</td><td><code><?php echo esc_html($_SERVER['DOCUMENT_ROOT'] ?? ABSPATH); ?></code></td></tr>
      <tr><td>Operating System</td><td><?php echo esc_html(PHP_OS_FAMILY . ' ' . php_uname('r')); ?></td></tr>
      <tr><td>Hostname</td><td><?php echo esc_html(gethostname() ?: '—'); ?></td></tr>

      <tr><th colspan="2" class="phpinfowp-info-section-head">Database</th></tr>
      <tr><td>MySQL Version</td><td><?php echo esc_html($db_version); ?></td></tr>
      <tr><td>Database Name</td><td><?php echo esc_html(DB_NAME); ?></td></tr>
      <tr><td>Database Host</td><td><?php echo esc_html(DB_HOST); ?></td></tr>
      <tr><td>Table Prefix</td><td><code><?php echo esc_html($wpdb->prefix); ?></code></td></tr>
      <tr><td>Database Size</td><td><?php echo esc_html($db_size); ?></td></tr>
      <tr><td>Charset</td><td><?php echo esc_html(DB_CHARSET); ?></td></tr>

      <tr><th colspan="2" class="phpinfowp-info-section-head">Disk</th></tr>
      <tr><td>Root Directory</td><td><?php echo esc_html(phpinfowp_dirsize_safe(ABSPATH)); ?></td></tr>
      <tr><td>Uploads Directory</td><td><?php echo esc_html(phpinfowp_dirsize_safe(WP_CONTENT_DIR . '/uploads')); ?></td></tr>

    </tbody>
  </table>
</div>
