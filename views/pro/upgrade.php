<?php
defined('ABSPATH') or die('Unauthorized Access');
$buy_url     = 'https://exeebit.com/phpinfo-wp#pricing';
$license_url = admin_url('admin.php?page=phpinfowp-license');

$pillars = [
    [
        'icon'  => 'dashicons-shield-alt',
        'name'  => 'Safeguard',
        'tag'   => "Don't break your site.",
        'items' => ['Scheduled weekly compat scans', 'Config Snapshots & restore', 'Security Headers auditor', 'SSL Monitor (multi-domain)'],
    ],
    [
        'icon'  => 'dashicons-chart-bar',
        'name'  => 'Insight',
        'tag'   => 'Know what is wrong.',
        'items' => ['Full Config Grader', 'Database Health', 'OPcache Dashboard', 'Error Log Viewer', 'WP Cron Monitor', 'Mail Deliverability'],
    ],
    [
        'icon'  => 'dashicons-portfolio',
        'name'  => 'Deliver',
        'tag'   => 'Look pro to clients.',
        'items' => ['Audit Report PDF', 'Email Alerts', 'Weekly Digest', 'Slack / Discord webhooks', 'Multi-site dashboard'],
    ],
];
?>
<div class="phpinfowp-upgrade-gate">
    <div class="phpinfowp-upgrade-inner">

        <div class="phpinfowp-upgrade-badge">PRO</div>
        <h2 class="phpinfowp-upgrade-title">Upgrade to phpinfo() WP Pro</h2>
        <p class="phpinfowp-upgrade-subtitle">The WordPress server health audit you can hand to clients.</p>

        <div class="phpinfowp-upgrade-pillars">
            <?php foreach ($pillars as $p): ?>
            <div class="phpinfowp-upgrade-pillar">
                <div class="phpinfowp-upgrade-pillar-icon">
                    <span class="dashicons <?php echo esc_attr($p['icon']); ?>"></span>
                </div>
                <div class="phpinfowp-upgrade-pillar-name"><?php echo esc_html($p['name']); ?></div>
                <div class="phpinfowp-upgrade-pillar-tag"><?php echo esc_html($p['tag']); ?></div>
                <ul class="phpinfowp-upgrade-pillar-items">
                    <?php foreach ($p['items'] as $item): ?>
                        <li><?php echo esc_html($item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="phpinfowp-upgrade-plans-header">
            <h3 class="phpinfowp-upgrade-plans-title">Choose your plan</h3>
            <p class="phpinfowp-upgrade-plans-note">Every plan unlocks <strong>every feature above</strong> — the only difference is the number of sites and update window.</p>
        </div>

        <div class="phpinfowp-upgrade-pricing">
            <div class="phpinfowp-upgrade-price-tier">
                <div class="phpinfowp-upgrade-price-name">Single</div>
                <div class="phpinfowp-upgrade-price-value">$29<small>/yr</small></div>
                <div class="phpinfowp-upgrade-price-sub">1 site &middot; 1 year of updates</div>
            </div>
            <div class="phpinfowp-upgrade-price-tier is-featured">
                <div class="phpinfowp-upgrade-price-name">Unlimited</div>
                <div class="phpinfowp-upgrade-price-value">$69<small>/yr</small></div>
                <div class="phpinfowp-upgrade-price-sub">Unlimited sites &middot; 1 year of updates</div>
                <div class="phpinfowp-upgrade-price-best">Best value</div>
            </div>
            <div class="phpinfowp-upgrade-price-tier">
                <div class="phpinfowp-upgrade-price-name">Lifetime</div>
                <div class="phpinfowp-upgrade-price-value">$149<small>once</small></div>
                <div class="phpinfowp-upgrade-price-sub">Unlimited sites &middot; lifetime updates</div>
            </div>
        </div>

        <div class="phpinfowp-upgrade-actions">
            <a href="<?php echo esc_url($buy_url); ?>" target="_blank" class="phpinfowp-upgrade-cta">
                Get Pro &rarr;
            </a>
            <a href="<?php echo esc_url($license_url); ?>" class="phpinfowp-upgrade-secondary">
                I already have a license
            </a>
        </div>

        <p class="phpinfowp-upgrade-footer">
            14-day money-back guarantee &middot; Instant delivery &middot; Site-locked license
        </p>
    </div>
</div>
