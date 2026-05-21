<?php
defined('ABSPATH') or die('Unauthorized Access');

$is_pro   = Phpinfo_WP_License::is_valid();
$current  = Phpinfo_WP_EOL::status();
$timeline = Phpinfo_WP_EOL::timeline();

$status_labels = [
    'eol'     => ['label' => 'End of Life',    'class' => 'eol-status-eol'],
    'warning' => ['label' => 'EOL < 90 days',  'class' => 'eol-status-warning'],
    'ok'      => ['label' => 'Supported',       'class' => 'eol-status-ok'],
    'unknown' => ['label' => 'Unknown',         'class' => 'eol-status-unknown'],
];
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>PHP EOL Timeline</h1>
            <p class="phpinfowp-page-subtitle">End-of-life dates and support status for every PHP version</p>
        </div>
    </div>

    <?php
    $eol_cfg = [
        'eol'     => ['bg' => '#fff4f4', 'border' => '#d63638', 'badge_bg' => '#d63638', 'label' => 'END OF LIFE'],
        'warning' => ['bg' => '#fffbf0', 'border' => '#dba617', 'badge_bg' => '#dba617', 'label' => 'EXPIRING SOON'],
        'ok'      => ['bg' => '#f0faf2', 'border' => '#00a32a', 'badge_bg' => '#00a32a', 'label' => 'SUPPORTED'],
        'unknown' => ['bg' => '#f6f7f7', 'border' => '#888',    'badge_bg' => '#888',    'label' => 'UNKNOWN'],
    ];
    $cfg = $eol_cfg[$current['status']] ?? $eol_cfg['unknown'];
    ?>

    <!-- Current version hero card -->
    <div style="background:<?php echo $cfg['bg']; ?>;border:1px solid <?php echo $cfg['border']; ?>;border-left:5px solid <?php echo $cfg['border']; ?>;border-radius:8px;padding:24px 28px;display:flex;align-items:center;gap:28px;flex-wrap:wrap;margin-bottom:32px">
        <div style="text-align:center;flex-shrink:0">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#888;margin-bottom:4px">Running on</div>
            <div style="font-size:42px;font-weight:800;line-height:1;color:#1d2327">PHP <?php echo esc_html($current['minor']); ?></div>
            <div style="font-size:12px;color:#666;margin-top:4px"><?php echo esc_html(PHP_VERSION); ?></div>
        </div>
        <div style="flex:1;min-width:220px">
            <span style="display:inline-block;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:700;letter-spacing:.5px;background:<?php echo $cfg['badge_bg']; ?>;color:#fff;margin-bottom:10px">
                <?php echo esc_html($cfg['label']); ?>
            </span>
            <?php if ($current['status'] === 'eol'): ?>
                <p style="margin:0;font-size:14px">Reached end-of-life on <strong><?php echo esc_html($current['eol']); ?></strong>. No security patches are being issued. Contact your host and request a PHP upgrade to 8.2 or newer immediately.</p>
            <?php elseif ($current['status'] === 'warning'): ?>
                <p style="margin:0;font-size:14px">Reaches end-of-life on <strong><?php echo esc_html($current['eol']); ?></strong> — <strong><?php echo esc_html($current['days']); ?> days</strong> from now. Plan your PHP upgrade before that date.</p>
            <?php elseif ($current['status'] === 'ok'): ?>
                <p style="margin:0;font-size:14px">Actively supported until <strong><?php echo esc_html($current['eol']); ?></strong> — <strong><?php echo esc_html($current['days']); ?> days</strong> from now. You're good.</p>
            <?php else: ?>
                <p style="margin:0;font-size:14px">EOL date not found for PHP <?php echo esc_html($current['minor']); ?>. Check <a href="https://www.php.net/supported-versions.php" target="_blank">php.net/supported-versions</a>.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Version timeline -->
    <h2 class="phpinfowp-section-heading">Version Lifecycle</h2>
    <table class="wp-list-table widefat fixed striped phpinfowp-eol-table">
        <thead>
            <tr>
                <th style="width:140px">Version</th>
                <th style="width:130px">EOL Date</th>
                <th style="width:150px">Status</th>
                <th>Days</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_reverse($timeline) as $row): ?>
                <tr <?php if ($row['current']) echo 'style="background:#f3f0ff;font-weight:600"'; ?>>
                    <td>
                        PHP <?php echo esc_html($row['version']); ?>
                        <?php if ($row['current']): ?>
                            <span style="font-size:10px;font-weight:700;padding:1px 5px;border-radius:3px;background:#777BB3;color:#fff;margin-left:4px">YOU</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($row['eol']); ?></td>
                    <td>
                        <span class="eol-badge eol-badge-<?php echo esc_attr($row['status']); ?>">
                            <?php echo esc_html($status_labels[$row['status']]['label']); ?>
                        </span>
                    </td>
                    <td style="color:<?php echo $row['days'] < 0 ? '#d63638' : ($row['days'] < 90 ? '#dba617' : '#555'); ?>">
                        <?php if ($row['days'] < 0): ?>
                            <?php echo esc_html(abs($row['days'])); ?> days ago
                        <?php else: ?>
                            <?php echo esc_html($row['days']); ?> days
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="description" style="margin-top:10px">
        Source: <a href="https://www.php.net/supported-versions.php" target="_blank">php.net/supported-versions</a> — updated in each plugin release.
    </p>

    <?php if (!$is_pro && in_array($current['status'], ['warning', 'eol'], true)): ?>
        <div class="phpinfowp-eol-upsell">
            <div class="phpinfowp-eol-upsell-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="phpinfowp-eol-upsell-body">
                <h3>Plan your PHP <?php echo esc_html($current['minor']); ?> upgrade with confidence</h3>
                <p>
                    Before you upgrade, scan every plugin and theme for compatibility with your target PHP version.
                    Save snapshots, monitor changes, and email yourself a digest.
                </p>
                <ul class="phpinfowp-eol-upsell-list">
                    <li><strong>PHP Compatibility Scanner</strong> — find breaking changes across all plugins/themes</li>
                    <li><strong>Config Snapshots</strong> — diff php.ini before and after the upgrade</li>
                    <li><strong>Audit Report</strong> — single-page PDF for clients or your records</li>
                    <li><strong>Email Alerts</strong> — get warned 90/30/7 days before any PHP EOL</li>
                </ul>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" class="phpinfowp-upgrade-cta">
                    Upgrade safely — Get Pro &rarr;
                </a>
            </div>
        </div>
    <?php endif; ?>

</div>
