<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) { require __DIR__ . '/upgrade.php'; return; }

$message  = '';
$msg_type = 'success';

if (isset($_POST['phpinfowp_save_alerts']) && check_admin_referer('phpinfowp_alerts_nonce')) {
    Phpinfo_WP_Alerts::save_settings($_POST);
    $message = 'Alert settings saved.';
}

if (isset($_POST['phpinfowp_test_alert']) && check_admin_referer('phpinfowp_alerts_nonce')) {
    $to   = [get_option('admin_email')];
    $site = get_bloginfo('name') . ' (' . get_site_url() . ')';
    $sent = wp_mail($to, '[phpinfo() WP] Test alert', "This is a test alert from phpinfo() WP Pro.\nSite: {$site}");
    $message  = $sent ? 'Test email sent to ' . get_option('admin_email') : 'wp_mail() failed — check your mail configuration.';
    $msg_type = $sent ? 'success' : 'error';
}

if (isset($_POST['phpinfowp_test_webhook']) && check_admin_referer('phpinfowp_alerts_nonce')) {
    $ok = Phpinfo_WP_Alerts::send_webhook('Webhook test', 'This is a webhook test from phpinfo() WP Pro.');
    $message  = $ok ? 'Webhook payload delivered successfully.' : 'Webhook delivery failed — check the URL and provider settings.';
    $msg_type = $ok ? 'success' : 'error';
}

$s = Phpinfo_WP_Alerts::get_settings();
$next_cron = wp_next_scheduled('phpinfowp_weekly_maintenance');
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>Alerts <span class="phpinfowp-pro-badge">PRO</span></h1>
            <p class="phpinfowp-page-subtitle">Email + Slack/Discord notifications for EOL, config changes, OPcache, and SSL events</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible" style="margin:0 0 20px"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <form method="post" style="max-width:720px">
        <?php wp_nonce_field('phpinfowp_alerts_nonce'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">Enable Alerts</th>
                <td>
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?php checked($s['enabled']); ?>>
                        Send alerts when issues are detected
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="alert_emails">Email recipients</label></th>
                <td>
                    <textarea name="emails" id="alert_emails" rows="3" class="large-text"><?php echo esc_textarea($s['emails']); ?></textarea>
                    <p class="description">One email address per line (or comma-separated). Leave blank to disable email and use webhook only.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="webhook_url">Webhook URL</label></th>
                <td>
                    <input type="url" name="webhook_url" id="webhook_url" value="<?php echo esc_attr($s['webhook_url']); ?>"
                           class="large-text" placeholder="https://hooks.slack.com/services/... or https://discord.com/api/webhooks/...">
                    <p class="description" style="margin-top:6px">
                        <label style="margin-right:14px">
                            <input type="radio" name="webhook_type" value="slack" <?php checked($s['webhook_type'], 'slack'); ?>>
                            Slack
                        </label>
                        <label style="margin-right:14px">
                            <input type="radio" name="webhook_type" value="discord" <?php checked($s['webhook_type'], 'discord'); ?>>
                            Discord
                        </label>
                        <label>
                            <input type="radio" name="webhook_type" value="generic" <?php checked($s['webhook_type'], 'generic'); ?>>
                            Generic JSON (for Zapier, Make, etc.)
                        </label>
                    </p>
                    <p class="description">HTTPS only. Same alerts as email — delivered to your chat or automation.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Alert Conditions</th>
                <td>
                    <label style="display:block;margin-bottom:8px">
                        <input type="checkbox" name="eol_warning" value="1" <?php checked($s['eol_warning']); ?>>
                        <strong>PHP EOL approaching</strong> — alert when PHP is &lt; 90 days from EOL, or already past it
                    </label>
                    <label style="display:block;margin-bottom:8px">
                        <input type="checkbox" name="config_change" value="1" <?php checked($s['config_change']); ?>>
                        <strong>Config changes detected</strong> — alert when the weekly auto-snapshot detects php.ini changes
                    </label>
                    <label style="display:block;margin-bottom:8px">
                        <input type="checkbox" name="opcache_low" value="1" <?php checked($s['opcache_low']); ?>>
                        <strong>OPcache hit rate below</strong>
                        <input type="number" name="opcache_thresh" value="<?php echo esc_attr($s['opcache_thresh']); ?>"
                               min="0" max="100" style="width:60px;margin:0 4px"> %
                    </label>
                    <label style="display:block;margin-bottom:8px">
                        <input type="checkbox" name="ssl_expiry" value="1" <?php checked($s['ssl_expiry']); ?>>
                        <strong>SSL certificate expiring within</strong>
                        <input type="number" name="ssl_thresh" value="<?php echo esc_attr($s['ssl_thresh']); ?>"
                               min="1" max="365" style="width:65px;margin:0 4px"> days
                        — <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-ssl')); ?>">Manage monitored domains</a>
                    </label>
                    <label style="display:block;margin-bottom:8px">
                        <input type="checkbox" name="weekly_digest" value="1" <?php checked($s['weekly_digest']); ?>>
                        <strong>Weekly digest</strong> — summary every week: PHP, memory, config grade, OPcache, SSL, config changes
                    </label>
                </td>
            </tr>
        </table>

        <p class="submit" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <input type="submit" name="phpinfowp_save_alerts" class="button button-primary" value="Save Settings">
            <input type="submit" name="phpinfowp_test_alert"   class="button button-secondary" value="Send Test Email">
            <?php if ($s['webhook_url']): ?>
                <input type="submit" name="phpinfowp_test_webhook" class="button button-secondary" value="Test Webhook">
            <?php endif; ?>
        </p>
    </form>

    <div style="margin-top:24px;padding:16px 20px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:6px;max-width:720px">
        <strong>Alert schedule:</strong>
        Alerts are checked weekly via WP Cron (alongside the auto-snapshot).
        <?php if ($next_cron): ?>
            Next run: <strong><?php echo esc_html(gmdate('Y-m-d H:i', $next_cron)); ?> UTC</strong>
            (<?php echo esc_html(human_time_diff($next_cron)); ?> from now).
        <?php else: ?>
            <span style="color:#d63638">WP Cron event not scheduled — deactivate and reactivate the plugin to fix this.</span>
        <?php endif; ?>
    </div>
</div>
