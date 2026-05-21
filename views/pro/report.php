<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) { require __DIR__ . '/upgrade.php'; return; }

// Handle branding form save before building report
if (isset($_POST['phpinfowp_report_branding']) && check_admin_referer('phpinfowp_report_branding')) {
    Phpinfo_WP_Report::save_branding([
        'enabled'     => isset($_POST['branding_enabled']),
        'company'     => $_POST['branding_company']     ?? '',
        'tagline'     => $_POST['branding_tagline']     ?? '',
        'footer_note' => $_POST['branding_footer_note'] ?? '',
        'accent'      => $_POST['branding_accent']      ?? '#777BB3',
    ]);
    echo '<div class="notice notice-success inline" style="margin:0 0 20px"><p>Report branding saved.</p></div>';
}

$r = Phpinfo_WP_Report::build();
$b = $r['branding'];
$accent     = $b['enabled'] && $b['accent'] ? esc_attr($b['accent']) : '#777BB3';
$brand_name = $b['enabled'] && $b['company'] ? esc_html($b['company']) : 'phpinfo() WP';
$brand_tag  = $b['enabled'] && $b['tagline'] ? esc_html($b['tagline']) : 'Server Health Audit';
?>

<style>.phpinfowp-report .phpinfowp-report-brand,.phpinfowp-report .phpinfowp-report-h{color:<?php echo $accent; ?> !important}.phpinfowp-report .phpinfowp-report-cover{border-bottom-color:<?php echo $accent; ?> !important}</style>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header no-print">
        <div>
            <h1>Audit Report <span class="phpinfowp-pro-badge">PRO</span></h1>
            <p class="phpinfowp-page-subtitle">A single-page server health report — print to PDF or share with clients</p>
        </div>
        <div>
            <button type="button" class="button" onclick="document.getElementById('phpinfowp-report-branding').style.display='block';return false;">
                <span class="dashicons dashicons-admin-customizer" style="vertical-align:middle"></span> White-label
            </button>
            <button type="button" class="button button-primary" onclick="window.print()">
                <span class="dashicons dashicons-printer" style="vertical-align:middle"></span> Print / Save as PDF
            </button>
        </div>
    </div>

    <!-- White-label branding form (hidden by default) -->
    <div id="phpinfowp-report-branding" class="phpinfowp-report-branding-form no-print" style="display:<?php echo $b['enabled'] ? 'block' : 'none'; ?>">
        <form method="post">
            <?php wp_nonce_field('phpinfowp_report_branding'); ?>
            <input type="hidden" name="phpinfowp_report_branding" value="1">
            <h3>Report Branding</h3>
            <p class="description">Customize the report cover and footer with your own brand. Toggle off to restore the default look.</p>
            <table class="form-table">
                <tr>
                    <th><label for="branding_enabled">Enable white-label</label></th>
                    <td><label><input type="checkbox" id="branding_enabled" name="branding_enabled" value="1" <?php checked($b['enabled']); ?>> Use my branding instead of "phpinfo() WP"</label></td>
                </tr>
                <tr>
                    <th><label for="branding_company">Company name</label></th>
                    <td><input type="text" id="branding_company" name="branding_company" class="regular-text" value="<?php echo esc_attr($b['company']); ?>" placeholder="Acme Digital Studio"></td>
                </tr>
                <tr>
                    <th><label for="branding_tagline">Report title</label></th>
                    <td><input type="text" id="branding_tagline" name="branding_tagline" class="regular-text" value="<?php echo esc_attr($b['tagline']); ?>" placeholder="Quarterly Site Health Audit"></td>
                </tr>
                <tr>
                    <th><label for="branding_footer_note">Footer note</label></th>
                    <td><textarea id="branding_footer_note" name="branding_footer_note" class="regular-text" rows="2" placeholder="Prepared by Acme Digital Studio · support@acme.com"><?php echo esc_textarea($b['footer_note']); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="branding_accent">Accent color</label></th>
                    <td><input type="color" id="branding_accent" name="branding_accent" value="<?php echo esc_attr($b['accent']); ?>"></td>
                </tr>
            </table>
            <p><button type="submit" class="button button-primary">Save branding</button></p>
        </form>
    </div>

    <div class="phpinfowp-report">

        <!-- Cover -->
        <div class="phpinfowp-report-cover">
            <div class="phpinfowp-report-brand"><?php echo $brand_name; ?></div>
            <h2 class="phpinfowp-report-title"><?php echo $brand_tag; ?></h2>
            <div class="phpinfowp-report-site"><?php echo esc_html($r['site']); ?></div>
            <div class="phpinfowp-report-url"><?php echo esc_html($r['url']); ?></div>
            <div class="phpinfowp-report-date">Generated <?php echo esc_html(wp_date('F j, Y · H:i', $r['generated_at'])); ?></div>
        </div>

        <!-- Quick stats grid -->
        <h3 class="phpinfowp-report-h">Snapshot</h3>
        <div class="phpinfowp-report-grid">
            <div class="phpinfowp-report-stat">
                <div class="phpinfowp-report-stat-label">PHP version</div>
                <div class="phpinfowp-report-stat-value"><?php echo esc_html($r['php']); ?></div>
                <div class="phpinfowp-report-stat-sub">
                    <?php if ($r['eol']['status'] === 'eol'): ?>
                        <span style="color:#d63638">End of life</span>
                    <?php elseif ($r['eol']['status'] === 'warning'): ?>
                        <span style="color:#dba617">EOL in <?php echo (int)$r['eol']['days']; ?> days</span>
                    <?php else: ?>
                        <span style="color:#00a32a">Supported until <?php echo esc_html($r['eol']['eol']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="phpinfowp-report-stat">
                <div class="phpinfowp-report-stat-label">WordPress</div>
                <div class="phpinfowp-report-stat-value"><?php echo esc_html($r['wp']); ?></div>
            </div>
            <div class="phpinfowp-report-stat">
                <div class="phpinfowp-report-stat-label">Config Grade</div>
                <div class="phpinfowp-report-stat-value"><?php echo esc_html($r['grader']['grade']); ?> <span style="font-size:14px;color:#888"><?php echo (int)$r['grader']['score']; ?>/100</span></div>
            </div>
            <?php if (!isset($r['headers']['error'])): ?>
            <div class="phpinfowp-report-stat">
                <div class="phpinfowp-report-stat-label">Security Headers</div>
                <div class="phpinfowp-report-stat-value"><?php echo esc_html($r['headers']['grade']); ?> <span style="font-size:14px;color:#888"><?php echo (int)$r['headers']['score']; ?>/100</span></div>
            </div>
            <?php endif; ?>
            <?php if ($r['db']): ?>
            <div class="phpinfowp-report-stat">
                <div class="phpinfowp-report-stat-label">Database</div>
                <div class="phpinfowp-report-stat-value"><?php echo esc_html($r['db']['engine'] . ' ' . $r['db']['version']); ?></div>
                <div class="phpinfowp-report-stat-sub"><?php echo size_format($r['db_size']['total']); ?> total</div>
            </div>
            <?php endif; ?>
            <?php if ($r['opcache']): ?>
            <div class="phpinfowp-report-stat">
                <div class="phpinfowp-report-stat-label">OPcache</div>
                <div class="phpinfowp-report-stat-value"><?php echo $r['opcache']['hit_rate'] !== null ? esc_html($r['opcache']['hit_rate']) . '%' : '—'; ?></div>
                <div class="phpinfowp-report-stat-sub">hit rate</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Failing config checks -->
        <?php $failing = array_filter($r['grader']['checks'], fn($c) => $c['status'] === 'fail'); ?>
        <?php if ($failing): ?>
            <h3 class="phpinfowp-report-h">Config Issues (<?php echo count($failing); ?>)</h3>
            <table class="phpinfowp-report-table">
                <thead><tr><th>Directive</th><th>Current</th><th>Recommended</th></tr></thead>
                <tbody>
                    <?php foreach ($failing as $c): ?>
                        <tr>
                            <td><code><?php echo esc_html($c['key']); ?></code></td>
                            <td style="color:#d63638"><?php echo esc_html($c['value']); ?></td>
                            <td style="color:#00a32a"><?php echo esc_html($c['good']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Missing security headers -->
        <?php if (!isset($r['headers']['error'])):
            $missing = array_filter($r['headers']['results'], fn($h) => !$h['present']);
        ?>
            <?php if ($missing): ?>
                <h3 class="phpinfowp-report-h">Missing Security Headers (<?php echo count($missing); ?>)</h3>
                <ul class="phpinfowp-report-list">
                    <?php foreach ($missing as $h): ?>
                        <li><strong><?php echo esc_html($h['label']); ?></strong> &mdash; <?php echo esc_html($h['desc']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>

        <!-- SSL summary -->
        <?php if ($r['ssl']): ?>
            <h3 class="phpinfowp-report-h">SSL Certificates</h3>
            <table class="phpinfowp-report-table">
                <thead><tr><th>Host</th><th>Issuer</th><th>Expires</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($r['ssl'] as $c):
                        if (!empty($c['error'])) continue;
                    ?>
                        <tr>
                            <td><?php echo esc_html($c['host']); ?></td>
                            <td><?php echo esc_html($c['issuer']); ?></td>
                            <td><?php echo esc_html($c['expiry']); ?></td>
                            <td>
                                <?php if ($c['days'] < 0): ?>
                                    <span style="color:#d63638">Expired</span>
                                <?php elseif ($c['days'] < 30): ?>
                                    <span style="color:#dba617"><?php echo (int)$c['days']; ?> days</span>
                                <?php else: ?>
                                    <span style="color:#00a32a"><?php echo (int)$c['days']; ?> days</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- DB summary -->
        <?php if ($r['db']): ?>
            <h3 class="phpinfowp-report-h">Database</h3>
            <table class="phpinfowp-report-table">
                <tbody>
                    <tr><td>Engine</td><td><?php echo esc_html($r['db']['engine'] . ' ' . $r['db']['version']); ?></td></tr>
                    <tr><td>EOL</td><td><?php echo esc_html($r['db']['eol'] ?? '—'); ?></td></tr>
                    <tr><td>Total size</td><td><?php echo size_format($r['db_size']['total']); ?> (<?php echo (int)$r['db_size']['tables']; ?> tables)</td></tr>
                    <tr><td>Autoload data</td><td><?php echo size_format($r['autoload']['bytes']); ?> across <?php echo (int)$r['autoload']['count']; ?> options</td></tr>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Cron -->
        <?php if ($r['cron'] && ($r['cron']['overdue'] || $r['cron']['orphan'])): ?>
            <h3 class="phpinfowp-report-h">Cron Health</h3>
            <ul class="phpinfowp-report-list">
                <li><?php echo (int)$r['cron']['total']; ?> total scheduled events</li>
                <?php if ($r['cron']['overdue']): ?>
                    <li style="color:#d63638"><?php echo (int)$r['cron']['overdue']; ?> overdue</li>
                <?php endif; ?>
                <?php if ($r['cron']['orphan']): ?>
                    <li style="color:#dba617"><?php echo (int)$r['cron']['orphan']; ?> orphan hooks (no registered callback)</li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>

        <!-- Compat -->
        <?php if ($r['compat'] && !isset($r['compat']['error']) && !empty($r['compat']['total'])): ?>
            <h3 class="phpinfowp-report-h">PHP Compatibility (target PHP <?php echo esc_html($r['compat']['target']); ?>)</h3>
            <p><?php echo (int)$r['compat']['total']; ?> issue(s) across <?php echo (int)$r['compat']['with_issues']; ?> plugins/themes.</p>
        <?php endif; ?>

        <div class="phpinfowp-report-footer">
            <?php if ($b['enabled'] && $b['footer_note']): ?>
                <?php echo nl2br(esc_html($b['footer_note'])); ?>
            <?php else: ?>
                Report generated by <strong><?php echo $brand_name; ?></strong> &middot; <?php echo esc_html(get_site_url()); ?>
            <?php endif; ?>
        </div>

    </div>
</div>
