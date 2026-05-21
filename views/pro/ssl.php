<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) { require __DIR__ . '/upgrade.php'; return; }

$message  = '';
$msg_type = 'success';

if (isset($_POST['phpinfowp_ssl_action']) && check_admin_referer('phpinfowp_ssl_nonce')) {
    $action = sanitize_text_field($_POST['phpinfowp_ssl_action']);
    if ($action === 'recheck') {
        Phpinfo_WP_SSL::bust_cache();
        $message = 'Cache cleared — re-checking all certificates.';
    } elseif ($action === 'save_domains') {
        Phpinfo_WP_SSL::save_extra_domains($_POST['ssl_domains'] ?? '');
        $message = 'Domain list saved.';
    }
}

$results    = Phpinfo_WP_SSL::check_all();
$any_cached = array_filter($results, fn($r) => !empty($r['cached']));
?>

<div class="phpinfowp-pro-page">
    <h1>SSL Certificate Monitor <span class="phpinfowp-pro-badge">PRO</span></h1>

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <!-- Certificate cards -->
    <div class="phpinfowp-ssl-grid">
        <?php foreach ($results as $cert): ?>
            <?php if (!empty($cert['error'])): ?>
                <div class="phpinfowp-ssl-card phpinfowp-ssl-error">
                    <div class="phpinfowp-ssl-card-host"><?php echo esc_html($cert['host'] ?? '—'); ?></div>
                    <div class="phpinfowp-ssl-card-status" style="color:#d63638">ERROR</div>
                    <div class="phpinfowp-ssl-card-days" style="font-size:13px;color:#d63638">
                        <?php echo esc_html($cert['error']); ?>
                    </div>
                </div>
            <?php else:
                $color = Phpinfo_WP_SSL::status_color($cert['status']);
                $label = Phpinfo_WP_SSL::status_label($cert['status']);
            ?>
                <div class="phpinfowp-ssl-card" style="border-top-color:<?php echo $color; ?>">
                    <div class="phpinfowp-ssl-card-host">
                        <?php echo esc_html($cert['host']); ?>
                        <?php if (!empty($cert['cached'])): ?>
                            <span style="font-size:10px;color:#999;font-weight:400"> (cached)</span>
                        <?php endif; ?>
                    </div>
                    <div class="phpinfowp-ssl-card-days" style="color:<?php echo $color; ?>">
                        <?php if ($cert['days'] < 0): ?>
                            Expired <?php echo esc_html(abs($cert['days'])); ?> days ago
                        <?php else: ?>
                            <?php echo esc_html($cert['days']); ?> <span style="font-size:14px;font-weight:400">days left</span>
                        <?php endif; ?>
                    </div>
                    <div class="phpinfowp-ssl-card-status">
                        <span class="eol-badge eol-badge-<?php echo $cert['status'] === 'ok' ? 'ok' : ($cert['status'] === 'warning' ? 'warning' : 'eol'); ?>">
                            <?php echo $label; ?>
                        </span>
                    </div>
                    <table class="phpinfowp-ssl-card-meta">
                        <tr><td>Expires</td><td><?php echo esc_html($cert['expiry']); ?></td></tr>
                        <tr><td>Issued</td><td><?php echo esc_html($cert['issued']); ?></td></tr>
                        <tr><td>Issuer</td><td><?php echo esc_html($cert['issuer']); ?></td></tr>
                        <?php if (!empty($cert['cn']) && $cert['cn'] !== $cert['host']): ?>
                        <tr><td>CN</td><td><?php echo esc_html($cert['cn']); ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($cert['sans'])): ?>
                        <tr>
                            <td>SANs</td>
                            <td style="font-size:11px;color:#666">
                                <?php echo esc_html(implode(', ', array_slice($cert['sans'], 0, 6))); ?>
                                <?php if (count($cert['sans']) > 6): ?>
                                    <em>+<?php echo count($cert['sans']) - 6; ?> more</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div style="display:flex;gap:10px;margin-top:16px;align-items:center;flex-wrap:wrap">
        <form method="post">
            <?php wp_nonce_field('phpinfowp_ssl_nonce'); ?>
            <input type="hidden" name="phpinfowp_ssl_action" value="recheck">
            <button type="submit" class="button button-secondary">Re-check all certificates</button>
        </form>
        <?php if ($any_cached): ?>
            <span style="font-size:12px;color:#666">Results cached for 6 hours.</span>
        <?php endif; ?>
    </div>

    <!-- Extra domains -->
    <div style="margin-top:32px;max-width:520px">
        <h2 style="margin-bottom:8px">Monitor Additional Domains</h2>
        <p style="font-size:13px;color:#555;margin-bottom:12px">
            Add hostnames you want to monitor (one per line). Useful for agencies managing client sites.<br>
            Enter the domain only — no <code>https://</code> or path. Example: <code>client.com</code>
        </p>
        <form method="post">
            <?php wp_nonce_field('phpinfowp_ssl_nonce'); ?>
            <input type="hidden" name="phpinfowp_ssl_action" value="save_domains">
            <textarea name="ssl_domains" rows="5" class="large-text" style="font-family:monospace;font-size:13px"
                      placeholder="client-a.com&#10;client-b.com&#10;staging.mysite.com"><?php
                echo esc_textarea(implode("\n", Phpinfo_WP_SSL::get_extra_domains()));
            ?></textarea>
            <p class="submit"><input type="submit" class="button button-primary" value="Save Domains"></p>
        </form>
    </div>
</div>
