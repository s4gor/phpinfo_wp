<?php
defined('ABSPATH') or die('Unauthorized Access');

$is_pro    = Phpinfo_WP_License::is_valid();
$eol       = Phpinfo_WP_EOL::status();
$grader    = Phpinfo_WP_Config_Grader::summary();
$bar       = Phpinfo_WP_Admin_Bar::status();

$grade        = $grader['grade'];
$score        = $grader['score'];
$crit         = $bar['crit_count'] ?? 0;
$warn         = $bar['warn_count'] ?? 0;
$overall      = $bar['overall'] ?? 'good';
$mem          = $bar['memory'] ?? ['peak_bytes' => 0, 'limit_label' => ini_get('memory_limit'), 'pct' => 0];

$overall_color = match ($overall) {
    'critical' => '#d63638',
    'warning'  => '#dba617',
    default    => '#00a32a',
};
$overall_label = match ($overall) {
    'critical' => 'Needs attention',
    'warning'  => 'Watch list',
    default    => 'Healthy',
};
?>

<div class="phpinfowp-pro-page phpinfowp-dashboard">

    <div class="phpinfowp-page-header">
        <div>
            <h1>Dashboard</h1>
            <p class="phpinfowp-page-subtitle">A live snapshot of your site's PHP health, configuration, and server posture</p>
        </div>
        <?php if ($is_pro): ?>
            <span style="color:#00a32a;font-weight:600">✓ Pro active</span>
        <?php endif; ?>
    </div>

    <!-- Top stat row: Grade · Overall · Memory · PHP version -->
    <div class="phpinfowp-dash-stats">
        <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-config-grader')); ?>" class="phpinfowp-dash-stat">
            <div class="phpinfowp-dash-stat-label">Config Grade</div>
            <div class="phpinfowp-dash-stat-value grade-<?php echo esc_attr(strtolower(str_replace('+', 'plus', $grade))); ?>">
                <?php echo esc_html($grade); ?>
            </div>
            <div class="phpinfowp-dash-stat-meta"><?php echo (int) $score; ?>/100</div>
        </a>

        <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-audit')); ?>" class="phpinfowp-dash-stat">
            <div class="phpinfowp-dash-stat-label">Overall</div>
            <div class="phpinfowp-dash-stat-value" style="color:<?php echo $overall_color; ?>;font-size:22px;line-height:1.2;padding-top:8px">
                <?php echo esc_html($overall_label); ?>
            </div>
            <div class="phpinfowp-dash-stat-meta">
                <?php if ($crit || $warn): ?>
                    <?php if ($crit): ?><strong style="color:#d63638"><?php echo $crit; ?> critical</strong><?php endif; ?>
                    <?php if ($crit && $warn) echo ' · '; ?>
                    <?php if ($warn): ?><strong style="color:#dba617"><?php echo $warn; ?> warning<?php echo $warn === 1 ? '' : 's'; ?></strong><?php endif; ?>
                <?php else: ?>
                    No active issues
                <?php endif; ?>
            </div>
        </a>

        <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-eol')); ?>" class="phpinfowp-dash-stat">
            <div class="phpinfowp-dash-stat-label">PHP Version</div>
            <div class="phpinfowp-dash-stat-value" style="font-size:22px;line-height:1.2;padding-top:8px">
                <?php echo esc_html(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION); ?>
            </div>
            <div class="phpinfowp-dash-stat-meta">
                <?php if ($eol['status'] === 'eol'): ?>
                    <strong style="color:#d63638">EOL <?php echo esc_html($eol['eol']); ?></strong>
                <?php elseif ($eol['status'] === 'warning'): ?>
                    <strong style="color:#dba617">EOL <?php echo (int) $eol['days']; ?>d</strong>
                <?php else: ?>
                    Supported<?php if (!empty($eol['eol'])): ?> until <?php echo esc_html($eol['eol']); ?><?php endif; ?>
                <?php endif; ?>
            </div>
        </a>

        <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-info')); ?>" class="phpinfowp-dash-stat">
            <div class="phpinfowp-dash-stat-label">Memory peak today</div>
            <div class="phpinfowp-dash-stat-value" style="font-size:22px;line-height:1.2;padding-top:8px">
                <?php
                if ($mem['peak_bytes'] > 0) {
                    echo esc_html(size_format($mem['peak_bytes']));
                } else {
                    echo esc_html(size_format(memory_get_usage(true)));
                }
                ?>
            </div>
            <div class="phpinfowp-dash-stat-meta">of <?php echo esc_html($mem['limit_label']); ?><?php if ($mem['pct'] > 0): ?> · <?php echo (int) $mem['pct']; ?>%<?php endif; ?></div>
        </a>
    </div>

    <!-- Active issues panel -->
    <?php if (!empty($bar['issues'])): ?>
    <div class="phpinfowp-dash-section">
        <h2 class="phpinfowp-dash-section-title">Active issues</h2>
        <div class="phpinfowp-dash-issues">
            <?php foreach ($bar['issues'] as $issue):
                $c = $issue['level'] === 'critical' ? '#d63638' : '#dba617';
            ?>
                <a href="<?php echo esc_url($issue['href']); ?>" class="phpinfowp-dash-issue">
                    <span class="phpinfowp-dash-issue-dot" style="background:<?php echo $c; ?>"></span>
                    <strong><?php echo esc_html($issue['label']); ?></strong>
                    <span class="phpinfowp-dash-issue-detail"><?php echo esc_html($issue['detail']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Feature shortcuts grid — the "storefront" -->
    <div class="phpinfowp-dash-section">
        <h2 class="phpinfowp-dash-section-title">Audit</h2>
        <div class="phpinfowp-dash-grid">
            <?php
            $audit_cards = [
                ['slug' => 'phpinfowp-config-grader',    'icon' => 'dashicons-chart-bar',      'title' => 'Config Grader',     'desc' => 'A–F score for your PHP config + one-click fixes',  'pro' => false],
                ['slug' => 'phpinfowp-eol',              'icon' => 'dashicons-calendar-alt',   'title' => 'PHP EOL Timeline',  'desc' => 'End-of-life dates for every PHP version',          'pro' => false],
                ['slug' => 'phpinfowp-compat',           'icon' => 'dashicons-search',         'title' => 'PHP Compatibility', 'desc' => 'Scan plugins/themes before a PHP upgrade',         'pro' => false],
                ['slug' => 'phpinfowp-security-headers', 'icon' => 'dashicons-shield',         'title' => 'Security Headers',  'desc' => 'CSP, HSTS, X-Frame-Options graded',                'pro' => true],
                ['slug' => 'phpinfowp-ssl',              'icon' => 'dashicons-lock',           'title' => 'SSL Monitor',       'desc' => 'Cert expiry tracking for your site + domains',     'pro' => true],
                ['slug' => 'phpinfowp-opcache',          'icon' => 'dashicons-performance',    'title' => 'OPcache',           'desc' => 'Hit rate, memory, cached scripts',                 'pro' => true],
                ['slug' => 'phpinfowp-db-health',        'icon' => 'dashicons-database',       'title' => 'Database Health',   'desc' => 'Engine version, EOL, size, autoload bloat',        'pro' => true],
            ];
            foreach ($audit_cards as $card):
                $locked = $card['pro'] && !$is_pro;
            ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $card['slug'])); ?>" class="phpinfowp-dash-card<?php echo $locked ? ' is-locked' : ''; ?>">
                <span class="dashicons <?php echo $card['icon']; ?>"></span>
                <div class="phpinfowp-dash-card-title">
                    <?php echo esc_html($card['title']); ?>
                    <?php if ($card['pro']): ?><span class="phpinfowp-dash-card-pro">PRO</span><?php endif; ?>
                </div>
                <div class="phpinfowp-dash-card-desc"><?php echo esc_html($card['desc']); ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="phpinfowp-dash-section">
        <h2 class="phpinfowp-dash-section-title">Tools</h2>
        <div class="phpinfowp-dash-grid">
            <?php
            $tool_cards = [
                ['slug' => 'phpinfowp-viewer',     'icon' => 'dashicons-text-page',    'title' => 'phpinfo() Viewer',  'desc' => 'Clean, searchable phpinfo output',                 'pro' => false],
                ['slug' => 'phpinfowp-htaccess',   'icon' => 'dashicons-edit',         'title' => 'PHP Config Editor', 'desc' => 'Set php.ini directives via .htaccess / .user.ini', 'pro' => false],
                ['slug' => 'phpinfowp-safemode',   'icon' => 'dashicons-shield-alt',   'title' => 'Troubleshooting',   'desc' => 'Per-user safe-mode that cannot break your site',   'pro' => false],
                ['slug' => 'phpinfowp-snapshots',  'icon' => 'dashicons-backup',       'title' => 'Config Snapshots',  'desc' => 'Weekly config snapshots with visual diffs',        'pro' => true],
                ['slug' => 'phpinfowp-cron',       'icon' => 'dashicons-clock',        'title' => 'WP-Cron Monitor',   'desc' => 'Overdue, orphan, and recently-run events',         'pro' => true],
                ['slug' => 'phpinfowp-error-log',  'icon' => 'dashicons-warning',      'title' => 'Error Log',         'desc' => 'Browse and filter the PHP error log',              'pro' => true],
                ['slug' => 'phpinfowp-mail',       'icon' => 'dashicons-email',        'title' => 'Mail Deliverability','desc' => 'Send test, SPF/DKIM lookup',                       'pro' => true],
            ];
            foreach ($tool_cards as $card):
                $locked = $card['pro'] && !$is_pro;
            ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $card['slug'])); ?>" class="phpinfowp-dash-card<?php echo $locked ? ' is-locked' : ''; ?>">
                <span class="dashicons <?php echo $card['icon']; ?>"></span>
                <div class="phpinfowp-dash-card-title">
                    <?php echo esc_html($card['title']); ?>
                    <?php if ($card['pro']): ?><span class="phpinfowp-dash-card-pro">PRO</span><?php endif; ?>
                </div>
                <div class="phpinfowp-dash-card-desc"><?php echo esc_html($card['desc']); ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="phpinfowp-dash-section">
        <h2 class="phpinfowp-dash-section-title">Reports</h2>
        <div class="phpinfowp-dash-grid">
            <?php
            $report_cards = [
                ['slug' => 'phpinfowp-log',    'icon' => 'dashicons-list-view',    'title' => 'Activity Log',  'desc' => 'Every config change tracked over time',         'pro' => false],
                ['slug' => 'phpinfowp-report', 'icon' => 'dashicons-media-document','title' => 'Audit Report', 'desc' => 'White-label single-page PDF for clients',       'pro' => true],
                ['slug' => 'phpinfowp-alerts', 'icon' => 'dashicons-bell',         'title' => 'Alerts',        'desc' => 'Email + Slack/Discord on PHP EOL, SSL, OPcache','pro' => true],
            ];
            foreach ($report_cards as $card):
                $locked = $card['pro'] && !$is_pro;
            ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $card['slug'])); ?>" class="phpinfowp-dash-card<?php echo $locked ? ' is-locked' : ''; ?>">
                <span class="dashicons <?php echo $card['icon']; ?>"></span>
                <div class="phpinfowp-dash-card-title">
                    <?php echo esc_html($card['title']); ?>
                    <?php if ($card['pro']): ?><span class="phpinfowp-dash-card-pro">PRO</span><?php endif; ?>
                </div>
                <div class="phpinfowp-dash-card-desc"><?php echo esc_html($card['desc']); ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

</div>
