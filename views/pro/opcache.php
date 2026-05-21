<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) { require __DIR__ . '/upgrade.php'; return; }

$message  = '';
$msg_type = 'success';

if (isset($_POST['phpinfowp_opcache_reset']) && check_admin_referer('phpinfowp_opcache_nonce')) {
    $ok      = Phpinfo_WP_OPcache::reset();
    $message  = $ok ? 'OPcache cleared successfully.' : 'Could not clear OPcache — function not available.';
    $msg_type = $ok ? 'success' : 'error';
}

$s = Phpinfo_WP_OPcache::status();
?>

<div class="phpinfowp-pro-page">
    <h1>OPcache Dashboard <span class="phpinfowp-pro-badge">PRO</span></h1>

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <?php if (!Phpinfo_WP_OPcache::is_available()): ?>
        <div class="notice notice-warning inline">
            <p><strong>OPcache is not available</strong> on this server. Ask your host to enable the <code>opcache</code> PHP extension — it's free and can cut PHP CPU usage by 50–80%.</p>
        </div>
    <?php elseif (!$s || !$s['enabled']): ?>
        <div class="notice notice-warning inline">
            <p><strong>OPcache extension is installed but disabled.</strong> Enable it in <code>php.ini</code>: <code>opcache.enable=1</code></p>
        </div>
    <?php else: ?>

        <!-- Hit rate card -->
        <div class="phpinfowp-opcache-grid">
            <div class="phpinfowp-opcache-card phpinfowp-opcache-hitrate">
                <div class="phpinfowp-grade-circle <?php echo esc_attr(Phpinfo_WP_OPcache::hit_rate_class($s['hit_rate'] ?? 0)); ?>">
                    <?php echo $s['hit_rate'] !== null ? esc_html($s['hit_rate']) . '%' : 'N/A'; ?>
                </div>
                <div>
                    <h3 style="margin:0 0 4px">Hit Rate</h3>
                    <p style="margin:0;color:#666;font-size:13px">
                        <?php echo number_format($s['hits']); ?> hits /
                        <?php echo number_format($s['misses']); ?> misses
                    </p>
                    <?php if ($s['full']): ?>
                        <p style="color:#d63638;margin:6px 0 0;font-size:13px">
                            Cache is full — increase <code>opcache.memory_consumption</code>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="phpinfowp-opcache-card">
                <h3 style="margin-top:0">Memory Usage</h3>
                <div class="phpinfowp-progress-bar-wrap">
                    <div class="phpinfowp-progress-bar" style="width:<?php echo min(100, $s['memory_pct']); ?>%;background:<?php echo $s['memory_pct'] > 85 ? '#d63638' : '#777BB3'; ?>"></div>
                </div>
                <p style="margin:6px 0 0;font-size:13px;color:#444">
                    Used: <strong><?php echo esc_html(Phpinfo_WP_OPcache::format_bytes($s['memory_used'])); ?></strong>
                    / Total: <?php echo esc_html(Phpinfo_WP_OPcache::format_bytes($s['memory_total'])); ?>
                    &nbsp;&middot;&nbsp; Wasted: <?php echo esc_html(Phpinfo_WP_OPcache::format_bytes($s['memory_wasted'])); ?>
                    (<?php echo round($s['wasted_pct'], 1); ?>%)
                </p>
            </div>

            <div class="phpinfowp-opcache-card">
                <h3 style="margin-top:0">Cached Scripts</h3>
                <div class="phpinfowp-progress-bar-wrap">
                    <?php $script_pct = $s['max_scripts'] > 0 ? round($s['cached_scripts'] / $s['max_scripts'] * 100, 1) : 0; ?>
                    <div class="phpinfowp-progress-bar" style="width:<?php echo min(100, $script_pct); ?>%;background:<?php echo $script_pct > 90 ? '#d63638' : '#00a32a'; ?>"></div>
                </div>
                <p style="margin:6px 0 0;font-size:13px;color:#444">
                    <strong><?php echo number_format($s['cached_scripts']); ?></strong>
                    / <?php echo number_format($s['max_scripts']); ?> max
                    (<?php echo $script_pct; ?>%)
                </p>
                <?php if ($s['start_time']): ?>
                    <p style="margin:6px 0 0;font-size:12px;color:#666">
                        Running since: <?php echo esc_html(gmdate('Y-m-d H:i', $s['start_time'])); ?> UTC
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Key directives -->
        <h2 style="margin-top:28px">Key Directives</h2>
        <table class="wp-list-table widefat fixed striped" style="max-width:700px">
            <thead><tr><th>Directive</th><th>Value</th></tr></thead>
            <tbody>
            <?php
            $show = [
                'opcache.enable', 'opcache.memory_consumption', 'opcache.max_accelerated_files',
                'opcache.validate_timestamps', 'opcache.revalidate_freq', 'opcache.save_comments',
                'opcache.enable_cli', 'opcache.jit', 'opcache.jit_buffer_size',
            ];
            foreach ($show as $d):
                $val = $s['directives'][$d] ?? ini_get($d);
                if ($val === false || $val === null) continue;
            ?>
                <tr>
                    <td><code><?php echo esc_html($d); ?></code></td>
                    <td><?php echo esc_html((string)$val); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Reset button -->
        <form method="post" style="margin-top:20px" onsubmit="return confirm('Clear OPcache? PHP will recompile all files on next request.')">
            <?php wp_nonce_field('phpinfowp_opcache_nonce'); ?>
            <input type="hidden" name="phpinfowp_opcache_reset" value="1">
            <button type="submit" class="button button-secondary">Clear OPcache</button>
            <span style="margin-left:8px;font-size:12px;color:#666">Forces recompilation of all cached PHP files.</span>
        </form>

    <?php endif; ?>
</div>
