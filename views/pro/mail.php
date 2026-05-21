<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) { require __DIR__ . '/upgrade.php'; return; }

$test_result = null;
if (isset($_POST['phpinfowp_mail_test']) && check_admin_referer('phpinfowp_mail_nonce')) {
    $to = sanitize_email($_POST['test_to'] ?? '');
    $test_result = Phpinfo_WP_Mail_Check::send_test($to);
}

$audit = Phpinfo_WP_Mail_Check::audit();

$row = function($title, $ok, $value, $warning = null) {
    $color = $ok ? '#00a32a' : '#d63638';
    $bg    = $ok ? '#f0faf2' : '#fff4f4';
    $dicon = $ok ? 'dashicons-yes-alt' : 'dashicons-dismiss';
    ?>
    <div class="phpinfowp-sec-row" style="border-left-color:<?php echo $color; ?>;background:<?php echo $bg; ?>">
        <div class="phpinfowp-sec-row-icon"><span class="dashicons <?php echo $dicon; ?>" style="color:<?php echo $color; ?>"></span></div>
        <div class="phpinfowp-sec-row-body">
            <div class="phpinfowp-sec-row-title"><strong><?php echo esc_html($title); ?></strong></div>
            <?php if ($value): ?>
                <code class="phpinfowp-sec-row-value"><?php echo esc_html($value); ?></code>
            <?php else: ?>
                <span class="phpinfowp-sec-row-missing">not found</span>
            <?php endif; ?>
            <?php if ($warning): ?>
                <div class="phpinfowp-sec-row-note" style="color:#996800"><?php echo esc_html($warning); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
};
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>Mail Deliverability <span class="phpinfowp-pro-badge">PRO</span></h1>
            <p class="phpinfowp-page-subtitle">SPF, DKIM, DMARC, and SMTP configuration audit</p>
        </div>
    </div>

    <?php if (!$audit): ?>
        <div class="notice notice-error inline"><p>Could not audit mail configuration.</p></div>
    <?php else: ?>

        <!-- Summary card -->
        <div class="phpinfowp-mail-summary">
            <div>
                <div class="phpinfowp-mail-summary-label">Sending from</div>
                <div class="phpinfowp-mail-summary-value"><?php echo esc_html($audit['from_email']); ?></div>
                <div class="phpinfowp-mail-summary-sub">Domain: <code><?php echo esc_html($audit['domain']); ?></code></div>
            </div>
            <div>
                <div class="phpinfowp-mail-summary-label">Mail method</div>
                <?php if ($audit['smtp_plugin']): ?>
                    <div class="phpinfowp-mail-summary-value" style="color:#00a32a"><?php echo esc_html($audit['smtp_plugin']); ?></div>
                    <div class="phpinfowp-mail-summary-sub">SMTP plugin active</div>
                <?php else: ?>
                    <div class="phpinfowp-mail-summary-value" style="color:#dba617">PHP mail()</div>
                    <div class="phpinfowp-mail-summary-sub">Consider an SMTP plugin for deliverability</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- DNS audit -->
        <h2 class="phpinfowp-section-heading" style="margin-top:24px">DNS Records</h2>
        <div class="phpinfowp-sec-rows">
            <?php $row('SPF (Sender Policy Framework)',  $audit['spf']['present'],  $audit['spf']['value'] ?? null,  $audit['spf']['warning'] ?? null); ?>
            <?php $row('DMARC',                          $audit['dmarc']['present'],$audit['dmarc']['value'] ?? null, $audit['dmarc']['warning'] ?? null); ?>

            <?php if ($audit['mx']): ?>
                <div class="phpinfowp-sec-row" style="border-left-color:#00a32a;background:#f0faf2">
                    <div class="phpinfowp-sec-row-icon"><span class="dashicons dashicons-yes-alt" style="color:#00a32a"></span></div>
                    <div class="phpinfowp-sec-row-body">
                        <div class="phpinfowp-sec-row-title"><strong>MX records</strong></div>
                        <?php foreach ($audit['mx'] as $mx): ?>
                            <code class="phpinfowp-sec-row-value"><?php echo (int)$mx['priority']; ?> &nbsp; <?php echo esc_html($mx['host']); ?></code><br>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="phpinfowp-sec-row" style="border-left-color:#d63638;background:#fff4f4">
                    <div class="phpinfowp-sec-row-icon"><span class="dashicons dashicons-dismiss" style="color:#d63638"></span></div>
                    <div class="phpinfowp-sec-row-body">
                        <div class="phpinfowp-sec-row-title"><strong>MX records</strong></div>
                        <span class="phpinfowp-sec-row-missing">no MX records found for <?php echo esc_html($audit['domain']); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="phpinfowp-sec-row" style="border-left-color:#888;background:#f9f9f9">
                <div class="phpinfowp-sec-row-icon"><span class="dashicons dashicons-info" style="color:#888"></span></div>
                <div class="phpinfowp-sec-row-body">
                    <div class="phpinfowp-sec-row-title"><strong>DKIM</strong></div>
                    <div class="phpinfowp-sec-row-note">DKIM uses a selector-specific subdomain (e.g. <code>default._domainkey.<?php echo esc_html($audit['domain']); ?></code>). Verify with your SMTP provider — selectors are not auto-detectable.</div>
                </div>
            </div>
        </div>

        <!-- Test email -->
        <h2 class="phpinfowp-section-heading" style="margin-top:32px">Send Test Email</h2>
        <form method="post" class="phpinfowp-mail-test-form">
            <?php wp_nonce_field('phpinfowp_mail_nonce'); ?>
            <input type="email" name="test_to" placeholder="recipient@example.com" required class="regular-text">
            <button type="submit" name="phpinfowp_mail_test" value="1" class="button button-primary">
                <span class="dashicons dashicons-email" style="vertical-align:middle"></span> Send test
            </button>
        </form>

        <?php if ($test_result): ?>
            <div class="notice notice-<?php echo $test_result['ok'] ? 'success' : 'error'; ?> inline" style="margin-top:14px">
                <?php if ($test_result['ok']): ?>
                    <p>Test email sent to <strong><?php echo esc_html($test_result['sent_to']); ?></strong> via <?php echo esc_html($test_result['method']); ?>. Check the inbox (and spam folder) within 60 seconds.</p>
                <?php else: ?>
                    <p>Could not send: <strong><?php echo esc_html($test_result['error'] ?? implode('; ', $test_result['errors'])); ?></strong></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
