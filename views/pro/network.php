<?php
defined('ABSPATH') or die('Unauthorized Access');

$eol      = Phpinfo_WP_EOL::status();
$is_pro   = Phpinfo_WP_License::is_valid();
$sites    = Phpinfo_WP_Network::sites();
$db       = $is_pro ? Phpinfo_WP_DB_Health::server_info() : null;
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>phpinfo() WP — Network</h1>
            <p class="phpinfowp-page-subtitle">Server-level info applies to all sites in the network. Per-site stats appear below.</p>
        </div>
    </div>

    <!-- Shared server info -->
    <div class="phpinfowp-report-grid">
        <div class="phpinfowp-report-stat">
            <div class="phpinfowp-report-stat-label">PHP</div>
            <div class="phpinfowp-report-stat-value"><?php echo esc_html(PHP_VERSION); ?></div>
            <div class="phpinfowp-report-stat-sub">
                <?php if ($eol['status'] === 'eol'): ?>
                    <span style="color:#d63638">End of life</span>
                <?php elseif ($eol['status'] === 'warning'): ?>
                    <span style="color:#dba617">EOL in <?php echo (int)$eol['days']; ?> days</span>
                <?php else: ?>
                    <span style="color:#00a32a">Supported</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="phpinfowp-report-stat">
            <div class="phpinfowp-report-stat-label">WordPress</div>
            <div class="phpinfowp-report-stat-value"><?php echo esc_html(get_bloginfo('version')); ?></div>
            <div class="phpinfowp-report-stat-sub"><?php echo count($sites); ?> sites</div>
        </div>
        <div class="phpinfowp-report-stat">
            <div class="phpinfowp-report-stat-label">Memory</div>
            <div class="phpinfowp-report-stat-value"><?php echo esc_html(ini_get('memory_limit')); ?></div>
        </div>
        <?php if ($db): ?>
        <div class="phpinfowp-report-stat">
            <div class="phpinfowp-report-stat-label">Database</div>
            <div class="phpinfowp-report-stat-value"><?php echo esc_html($db['engine'] . ' ' . $db['version']); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <h2 class="phpinfowp-section-heading" style="margin-top:28px">Per-site health</h2>

    <?php if (!$is_pro): ?>
        <div class="notice notice-info inline"><p>Per-site autoload analysis requires a Pro license. <a href="<?php echo esc_url(network_admin_url('admin.php?page=phpinfowp-network')); ?>">Activate one</a>.</p></div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:60px">ID</th>
                <th>Site</th>
                <th>URL</th>
                <th style="width:160px">Autoload data</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sites as $site):
                $color = '#888';
                if ($site['autoload_status'] === 'warning') $color = '#dba617';
                if ($site['autoload_status'] === 'fail')    $color = '#d63638';
                if ($site['autoload_status'] === 'ok')      $color = '#00a32a';
            ?>
                <tr>
                    <td><?php echo (int)$site['blog_id']; ?></td>
                    <td><?php echo esc_html($site['name']); ?></td>
                    <td><a href="<?php echo esc_url($site['url']); ?>" target="_blank"><?php echo esc_html($site['url']); ?></a></td>
                    <td>
                        <?php if ($site['autoload'] !== null): ?>
                            <span style="color:<?php echo $color; ?>;font-weight:600">
                                <?php echo size_format((int)$site['autoload']); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#aaa">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
