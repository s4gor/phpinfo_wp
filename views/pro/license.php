<?php
defined('ABSPATH') or die('Unauthorized Access');

$is_valid  = Phpinfo_WP_License::is_valid();
$is_locked = Phpinfo_WP_License::is_locked();
$key       = Phpinfo_WP_License::get_key();
$message   = '';
$msg_type  = 'success';

if (isset($_POST['phpinfowp_license_action']) && check_admin_referer('phpinfowp_license_nonce')) {
    $action = sanitize_text_field($_POST['phpinfowp_license_action']);

    if ($action === 'activate' && !empty($_POST['license_key'])) {
        $submitted = sanitize_text_field(trim($_POST['license_key']));
        $ok = Phpinfo_WP_License::activate($submitted);
        if ($ok) {
            $message  = 'License activated successfully. Pro features are now unlocked.';
            $is_valid = true;
            $key      = $submitted;
        } else {
            $message  = 'License key invalid, expired, or revoked. If you just purchased, allow a moment for activation to propagate, then try again. Contact <a href="mailto:support@exeebit.com">support@exeebit.com</a> if the problem persists.';
            $msg_type = 'error';
        }
    } elseif ($action === 'deactivate') {
        Phpinfo_WP_License::deactivate();
        $message  = 'License deactivated. Pro features are disabled.';
        $msg_type = 'info';
        $is_valid = false;
        $key      = '';
    }
}

$pillars = [
    [
        'icon'  => 'dashicons-shield-alt',
        'name'  => 'Safeguard',
        'tag'   => "Don't break your site.",
        'items' => [
            'PHP Compatibility Scanner — check every plugin & theme before upgrading',
            'Config Snapshots — automatic weekly backups of every php.ini directive',
            'Security Headers Auditor — score and fix your HTTP response headers',
            'SSL Certificate Monitor — track expiry for your site and extra domains',
        ],
    ],
    [
        'icon'  => 'dashicons-chart-bar',
        'name'  => 'Insight',
        'tag'   => 'Know what is wrong before clients call.',
        'items' => [
            'Config Grader — full breakdown of every failing directive with fixes',
            'Database Health — engine version, size, autoload bloat, slow tables',
            'OPcache Dashboard — hit rate, memory, scripts, one-click clear',
            'PHP Error Log Viewer — browse, search, clear from the dashboard',
            'WP Cron Monitor — overdue, orphan, and recently-run events',
            'Mail Deliverability — send-test, SPF/DKIM lookup',
        ],
    ],
    [
        'icon'  => 'dashicons-portfolio',
        'name'  => 'Deliver',
        'tag'   => 'Look professional to clients.',
        'items' => [
            'Audit Report — single-page health PDF you can print or hand to clients',
            'Email Alerts — EOL, config drift, SSL expiry, OPcache drops',
            'Weekly Digest — full health summary delivered to your inbox',
            'Slack / Discord / Webhook integration for real-time alerts',
            'Multi-site (Network) support — dashboard widget on each site',
        ],
    ],
];
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>License <span class="phpinfowp-pro-badge">PRO</span></h1>
            <p class="phpinfowp-page-subtitle">Activate your license to unlock Pro features on this site</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible" style="margin:0 0 20px">
            <p><?php echo wp_kses($message, ['strong' => [], 'a' => ['href' => []]]); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($is_locked): ?>
        <div class="notice notice-error inline" style="margin:0 0 20px">
            <p><strong>License locked.</strong> Your license could not be verified for 14+ days. Re-enter your key to unlock, or contact support at <a href="mailto:support@exeebit.com">support@exeebit.com</a>.</p>
        </div>
    <?php endif; ?>

    <!-- License status card -->
    <div class="phpinfowp-license-card <?php echo $is_valid ? 'is-active' : 'is-inactive'; ?>">
        <div class="phpinfowp-license-status">
            <?php if ($is_valid): ?>
                <span class="dashicons dashicons-yes-alt" style="color:#00a32a"></span>
                <div>
                    <div class="phpinfowp-license-status-title">Pro license is active</div>
                    <div class="phpinfowp-license-status-sub">All Pro features are unlocked on this site.</div>
                </div>
            <?php else: ?>
                <span class="dashicons dashicons-dismiss" style="color:#d63638"></span>
                <div>
                    <div class="phpinfowp-license-status-title">No active license</div>
                    <div class="phpinfowp-license-status-sub">Enter a valid license key to unlock Pro features.</div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($is_valid): ?>
            <?php $meta = Phpinfo_WP_License::payload(); ?>
            <div class="phpinfowp-license-key-display">
                <span class="phpinfowp-license-key-label">License key</span>
                <code><?php echo esc_html(substr($key, 0, 12) . str_repeat('•', 20)); ?></code>
            </div>
            <?php if ($meta): ?>
                <div class="phpinfowp-license-meta">
                    <div class="phpinfowp-license-meta-row">
                        <span class="phpinfowp-license-meta-label">Registered to</span>
                        <span class="phpinfowp-license-meta-value"><?php echo esc_html($meta['email']); ?></span>
                    </div>
                    <div class="phpinfowp-license-meta-row">
                        <span class="phpinfowp-license-meta-label">Expires</span>
                        <span class="phpinfowp-license-meta-value">
                            <?php echo esc_html($meta['expiry_human']); ?>
                            <?php if (!$meta['is_lifetime']): ?>
                                <span style="color:<?php echo $meta['days_left'] < 30 ? '#d63638' : ($meta['days_left'] < 90 ? '#dba617' : '#666'); ?>;font-size:12px;margin-left:6px">
                                    (<?php echo (int) $meta['days_left']; ?> days left)
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if (!empty($meta['iat'])): ?>
                    <div class="phpinfowp-license-meta-row">
                        <span class="phpinfowp-license-meta-label">Activated</span>
                        <span class="phpinfowp-license-meta-value"><?php echo esc_html(date_i18n(get_option('date_format'), $meta['iat'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <form method="post" style="margin-top:14px">
                <?php wp_nonce_field('phpinfowp_license_nonce'); ?>
                <input type="hidden" name="phpinfowp_license_action" value="deactivate">
                <button type="submit" class="button button-secondary" onclick="return confirm('Deactivate the license on this site?')">Deactivate License</button>
            </form>
        <?php else: ?>
            <form method="post" class="phpinfowp-license-form">
                <?php wp_nonce_field('phpinfowp_license_nonce'); ?>
                <input type="hidden" name="phpinfowp_license_action" value="activate">
                <label for="license_key" class="phpinfowp-license-input-label">Enter your license key</label>
                <div class="phpinfowp-license-input-row">
                    <input type="text" id="license_key" name="license_key"
                           placeholder="PIWP-xxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                           autocomplete="off" spellcheck="false"
                           value="<?php echo esc_attr($key); ?>">
                    <button type="submit" class="button button-primary">Activate</button>
                </div>
                <p class="description" style="margin-top:10px">
                    This site: <strong><?php echo esc_html(get_site_url()); ?></strong><br>
                    Don't have a license? <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank">Get phpinfo() WP Pro &rarr;</a>
                </p>
            </form>
        <?php endif; ?>
    </div>

    <!-- 3-pillar features -->
    <h2 class="phpinfowp-section-heading" style="margin-top:36px">What Pro unlocks</h2>
    <div class="phpinfowp-pillars">
        <?php foreach ($pillars as $p): ?>
            <div class="phpinfowp-pillar">
                <div class="phpinfowp-pillar-header">
                    <span class="dashicons <?php echo esc_attr($p['icon']); ?>"></span>
                    <div>
                        <div class="phpinfowp-pillar-name"><?php echo esc_html($p['name']); ?></div>
                        <div class="phpinfowp-pillar-tag"><?php echo esc_html($p['tag']); ?></div>
                    </div>
                </div>
                <ul class="phpinfowp-pillar-items">
                    <?php foreach ($p['items'] as $item): ?>
                        <li><?php echo esc_html($item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$is_valid): ?>
        <!-- Pricing -->
        <h2 class="phpinfowp-section-heading" style="margin-top:36px">Pricing</h2>
        <div class="phpinfowp-pricing">
            <div class="phpinfowp-pricing-tier">
                <div class="phpinfowp-pricing-name">Single Site</div>
                <div class="phpinfowp-pricing-price">$29<span>/year</span></div>
                <ul class="phpinfowp-pricing-list">
                    <li>All Pro features on 1 site</li>
                    <li>1 year of updates</li>
                    <li>Email support</li>
                </ul>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" class="button button-secondary">Get Single</a>
            </div>
            <div class="phpinfowp-pricing-tier is-featured">
                <div class="phpinfowp-pricing-flag">Most Popular</div>
                <div class="phpinfowp-pricing-name">Unlimited</div>
                <div class="phpinfowp-pricing-price">$69<span>/year</span></div>
                <ul class="phpinfowp-pricing-list">
                    <li>All Pro features on unlimited sites</li>
                    <li>1 year of updates</li>
                    <li>Priority email support</li>
                    <li>Multi-site (Network) support</li>
                </ul>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" class="button button-primary">Get Unlimited</a>
            </div>
            <div class="phpinfowp-pricing-tier">
                <div class="phpinfowp-pricing-flag is-warn">Founders &mdash; First 50</div>
                <div class="phpinfowp-pricing-name">Lifetime</div>
                <div class="phpinfowp-pricing-price">$149<span>once</span></div>
                <ul class="phpinfowp-pricing-list">
                    <li>All Pro features on unlimited sites</li>
                    <li>Lifetime updates</li>
                    <li>Priority email support, forever</li>
                </ul>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" class="button button-secondary">Get Lifetime</a>
            </div>
        </div>
        <p class="phpinfowp-pricing-foot">14-day money-back guarantee &middot; Instant license delivery &middot; Cancel anytime</p>
    <?php endif; ?>

</div>
