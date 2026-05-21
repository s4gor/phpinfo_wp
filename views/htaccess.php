<?php
defined('ABSPATH') or die('Unauthorized Access');

$root_dir    = get_home_path();
$content_dir = WP_CONTENT_DIR;
$log_dir     = "$content_dir/logs/phpinfo-WP";
$log_file    = "$log_dir/log.txt";

if (!file_exists($log_dir)) wp_mkdir_p($log_dir);
if (!file_exists($log_file)) file_put_contents($log_file, '');

global $current_user;
$user        = $current_user->user_login;
$notice      = '';
$notice_type = 'success';
$writable    = is_writable($root_dir);

// Detect which method applies to this server
$sapi            = php_sapi_name();
$server_software = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
$is_litespeed    = str_contains($server_software, 'litespeed');

// apache2handler = Apache + mod_php → .htaccess php_value works
// Everything else (fpm-fcgi, litespeed, cgi, etc.) → .user.ini
$mode = ($sapi === 'apache2handler' && !$is_litespeed) ? 'htaccess' : 'userini';

$target_file  = $root_dir . ($mode === 'htaccess' ? '.htaccess' : '.user.ini');
$cache_file   = $root_dir . ($mode === 'htaccess' ? 'htaccess-phpinfo.txt' : 'userini-phpinfo.txt');
$user_ini_ttl = (int) ini_get('user_ini.cache_ttl') ?: 300;

if ($writable && isset($_POST['phpinfo_nonce']) && wp_verify_nonce($_POST['phpinfo_nonce'], 'phpinfo_nonce')) {

    if ($mode === 'htaccess') {

        if (isset($_POST['backup'])) {
            file_put_contents("$root_dir.htaccess.bak", '#BACKED UP by phpinfo() WP' . PHP_EOL . file_get_contents("$root_dir.htaccess"));
            $notice = 'Backup created: <code>.htaccess.bak</code>';
            file_put_contents($log_file, ".htaccess backed up on " . current_time('mysql') . " by {$user}<br />", FILE_APPEND);

        } elseif (isset($_POST['restore'])) {
            if (!file_exists("$root_dir.htaccess.bak")) {
                $notice      = 'No backup file found. Take a backup first.';
                $notice_type = 'error';
            } else {
                file_put_contents("$root_dir.htaccess", file_get_contents("$root_dir.htaccess.bak"));
                $notice = '.htaccess restored from backup.';
                file_put_contents($log_file, ".htaccess restored on " . current_time('mysql') . " by {$user}<br />", FILE_APPEND);
            }

        } elseif (isset($_POST['save'])) {
            $custom_raw = $_POST['htaccess'] ?? '';
            $php_lines  = '';
            foreach (explode("\n", $custom_raw) as $line) {
                $line = trim($line);
                if ($line !== '' && !str_starts_with($line, '#')) {
                    $php_lines .= 'php_value ' . $line . "\n";
                }
            }

            $current = file_exists("$root_dir.htaccess") ? file_get_contents("$root_dir.htaccess") : '';
            $base    = preg_replace('/\n?# BEGIN phpinfo-wp.*$/s', '', $current);
            $new     = rtrim($base) . "\n\n# BEGIN phpinfo-wp\n" . $php_lines . "# END phpinfo-wp\n";

            file_put_contents("$root_dir.htaccess", $new);

            $test = wp_remote_get(get_site_url(), ['timeout' => 8, 'sslverify' => false]);
            if (!is_wp_error($test) && wp_remote_retrieve_response_code($test) === 500) {
                file_put_contents("$root_dir.htaccess", $current);
                $notice      = 'Save aborted — site returned HTTP 500. Original .htaccess restored automatically.';
                $notice_type = 'error';
            } else {
                file_put_contents($cache_file, $custom_raw);
                $notice = '.htaccess saved successfully.';
                file_put_contents($log_file, ".htaccess edited on " . current_time('mysql') . " by {$user}<br />", FILE_APPEND);
            }
        }

    } else { // .user.ini mode

        if (isset($_POST['save'])) {
            $custom_raw = $_POST['htaccess'] ?? '';
            $ini_lines  = '';
            foreach (explode("\n", $custom_raw) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, ';')) continue;
                // Accept both "key value" and "key = value" formats
                if (str_contains($line, '=')) {
                    $ini_lines .= $line . "\n";
                } else {
                    [$k, $v]    = array_pad(explode(' ', $line, 2), 2, '');
                    $ini_lines .= trim($k) . ' = ' . trim($v) . "\n";
                }
            }

            $current = file_exists($target_file) ? file_get_contents($target_file) : '';
            $base    = preg_replace('/\n?; BEGIN phpinfo-wp.*?; END phpinfo-wp\n?/s', '', $current);
            $new     = rtrim($base) . "\n\n; BEGIN phpinfo-wp\n" . $ini_lines . "; END phpinfo-wp\n";

            file_put_contents($target_file, $new);
            file_put_contents($cache_file, $custom_raw);
            $notice = ".user.ini saved. Changes take effect within {$user_ini_ttl} seconds (PHP-FPM cache TTL).";
            file_put_contents($log_file, ".user.ini edited on " . current_time('mysql') . " by {$user}<br />", FILE_APPEND);
        }
    }
}

$existing = file_exists($cache_file) ? file_get_contents($cache_file) : '';
$preview  = file_exists($target_file) ? file_get_contents($target_file) : '';

Phpinfo_wp::thankyou();
?>

<div class="phpinfowp-htaccess-page">

  <?php
  // Mode banner
  $mode_label  = $mode === 'htaccess' ? 'Apache + mod_php' : 'PHP-FPM / Nginx / LiteSpeed';
  $mode_color  = $mode === 'htaccess' ? '#d63638' : '#00a32a';
  $mode_file   = $mode === 'htaccess' ? '.htaccess' : '.user.ini';
  ?>
  <div class="phpinfowp-htaccess-banner" style="margin-bottom:20px;padding:12px 16px;background:#f6f7f7;border:1px solid #e0e0e0;border-left:4px solid <?php echo $mode_color; ?>;border-radius:4px">
    <span style="font-size:13px">
      <strong>Detected server:</strong> <?php echo esc_html($mode_label); ?> &nbsp;&middot;&nbsp;
      <strong>Writing to:</strong> <code><?php echo esc_html($target_file); ?></code>
      <?php if ($mode === 'userini'): ?>
        &nbsp;&middot;&nbsp; Changes apply within <strong><?php echo esc_html($user_ini_ttl); ?>s</strong> (PHP-FPM cache TTL)
      <?php endif; ?>
    </span>
  </div>

  <?php if (!$writable): ?>
    <div class="notice notice-error inline">
      <p><strong>Write permission denied.</strong> The directory <code><?php echo esc_html($root_dir); ?></code> is not writable.</p>
    </div>
  <?php elseif ($notice): ?>
    <div class="notice notice-<?php echo esc_attr($notice_type); ?> inline is-dismissible">
      <p><?php echo wp_kses($notice, ['code' => []]); ?></p>
    </div>
  <?php endif; ?>

  <div class="phpinfowp-htaccess-layout">

    <!-- Editor -->
    <div class="phpinfowp-htaccess-editor-col">
      <h2 style="margin-top:0">PHP Directives</h2>

      <?php if ($mode === 'htaccess'): ?>
        <p style="font-size:13px;color:#555;line-height:1.6;margin-bottom:16px">
          One directive per line — write the name and value only, without the <code>php_value</code> prefix.
          The plugin adds it and wraps your lines in a <code># BEGIN phpinfo-wp</code> block.<br>
          <strong>Auto-rollback:</strong> if the site returns HTTP 500 after saving, the original <code>.htaccess</code> is restored automatically.
        </p>
      <?php else: ?>
        <p style="font-size:13px;color:#555;line-height:1.6;margin-bottom:16px">
          One directive per line in standard <code>php.ini</code> format: <strong>directive = value</strong>.<br>
          You can also use the shorthand <strong>directive value</strong> — the plugin normalises it.<br>
          Your settings are placed in a <code>; BEGIN phpinfo-wp</code> block inside <code>.user.ini</code>.<br>
          <strong>Note:</strong> only <code>PHP_INI_USER</code> and <code>PHP_INI_PERDIR</code> directives can be set this way — same limitation as <code>.htaccess php_value</code>.
        </p>
      <?php endif; ?>

      <?php
      $placeholder = $mode === 'htaccess'
          ? "upload_max_filesize 64M\npost_max_size 64M\nmax_execution_time 120\nmax_input_vars 3000"
          : "upload_max_filesize = 64M\npost_max_size = 64M\nmax_execution_time = 120\nmax_input_vars = 3000";
      ?>

      <form method="post">
        <input type="hidden" name="phpinfo_nonce" value="<?php echo wp_create_nonce('phpinfo_nonce'); ?>">
        <textarea name="htaccess" id="htaccess-editor"
                  placeholder="<?php echo esc_attr($placeholder); ?>"
                  spellcheck="false"><?php echo esc_textarea($existing); ?></textarea>

        <div class="phpinfowp-htaccess-buttons">
          <button name="save" class="button button-primary">Save</button>
          <?php if ($mode === 'htaccess'): ?>
          <div style="display:flex;gap:8px">
            <button name="backup" class="button button-secondary"
                    onclick="return confirm('Create a .htaccess.bak backup file?')">Backup</button>
            <button name="restore" class="button button-secondary"
                    onclick="return confirm('Restore .htaccess from the last backup? Current changes will be lost.')">Restore from Backup</button>
          </div>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Live file preview -->
    <div class="phpinfowp-htaccess-preview-col">
      <h2 style="margin-top:0">Current <code><?php echo esc_html($mode_file); ?></code></h2>
      <?php if ($preview): ?>
        <pre class="phpinfowp-htaccess-preview"><?php echo esc_html($preview); ?></pre>
        <p style="font-size:11px;color:#888;margin-top:4px">Read-only. Path: <code><?php echo esc_html($target_file); ?></code></p>
      <?php else: ?>
        <p style="color:#666;font-style:italic">File does not exist yet — it will be created on first save.</p>
      <?php endif; ?>
    </div>

  </div>
</div>
