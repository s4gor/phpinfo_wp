<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) { require __DIR__ . '/upgrade.php'; return; }

$message  = '';
$msg_type = 'success';

if (isset($_POST['phpinfowp_log_action']) && check_admin_referer('phpinfowp_log_nonce')) {
    if ($_POST['phpinfowp_log_action'] === 'clear') {
        $path = Phpinfo_WP_Error_Log::find_path();
        if ($path && Phpinfo_WP_Error_Log::clear($path)) {
            $message = 'Log file cleared.';
        } else {
            $message  = 'Could not clear the log file (check file permissions).';
            $msg_type = 'error';
        }
    }
}

$path   = Phpinfo_WP_Error_Log::find_path();
$lines  = $path ? Phpinfo_WP_Error_Log::tail($path, 200) : [];
$size   = $path ? Phpinfo_WP_Error_Log::format_bytes(Phpinfo_WP_Error_Log::size($path)) : '0 B';
$search = sanitize_text_field($_GET['log_search'] ?? '');
?>

<div class="phpinfowp-pro-page">
    <h1>PHP Error Log <span class="phpinfowp-pro-badge">PRO</span></h1>

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <?php if (!$path): ?>
        <div class="notice notice-warning inline">
            <p>
                <strong>No error log found.</strong> To enable logging, add to <code>wp-config.php</code>:<br>
                <code>define('WP_DEBUG', true);<br>define('WP_DEBUG_LOG', true);<br>define('WP_DEBUG_DISPLAY', false);</code>
            </p>
        </div>
    <?php else: ?>

        <div class="phpinfowp-log-toolbar">
            <div class="phpinfowp-log-meta">
                <strong>File:</strong> <code><?php echo esc_html($path); ?></code>
                &nbsp;&middot;&nbsp; <strong>Size:</strong> <?php echo esc_html($size); ?>
                &nbsp;&middot;&nbsp; <strong>Showing:</strong> last <?php echo count($lines); ?> lines
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <input type="text" id="phpinfowp-log-search" placeholder="Filter lines..."
                       value="<?php echo esc_attr($search); ?>" class="regular-text"
                       oninput="phpinfowpFilterLog(this.value)">
                <form method="post" onsubmit="return confirm('Clear the entire log file? This cannot be undone.')">
                    <?php wp_nonce_field('phpinfowp_log_nonce'); ?>
                    <input type="hidden" name="phpinfowp_log_action" value="clear">
                    <button type="submit" class="button button-secondary">Clear Log</button>
                </form>
            </div>
        </div>

        <div id="phpinfowp-log-viewer">
            <?php if (empty($lines)): ?>
                <p style="padding:20px;color:#666;text-align:center">Log is empty — no errors recorded.</p>
            <?php else: ?>
                <?php foreach ($lines as $line): ?>
                    <div class="log-line <?php echo esc_attr(Phpinfo_WP_Error_Log::classify($line)); ?>" data-line="<?php echo esc_attr(strtolower($line)); ?>">
                        <?php echo esc_html($line); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <script>
        function phpinfowpFilterLog(q) {
            q = q.toLowerCase();
            document.querySelectorAll('#phpinfowp-log-viewer .log-line').forEach(function(el) {
                el.style.display = (!q || el.dataset.line.includes(q)) ? '' : 'none';
            });
        }
        <?php if ($search): ?>
        phpinfowpFilterLog(<?php echo json_encode($search); ?>);
        <?php endif; ?>
        </script>

    <?php endif; ?>
</div>
