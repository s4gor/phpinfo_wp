<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) {
    require __DIR__ . '/upgrade.php';
    return;
}

if (isset($_POST['phpinfowp_recheck']) && check_admin_referer('phpinfowp_sec_nonce')) {
    Phpinfo_WP_Security_Headers::bust_cache();
}

$audit = Phpinfo_WP_Security_Headers::get_cached();
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>Security Headers <span class="phpinfowp-pro-badge">PRO</span></h1>
            <p class="phpinfowp-page-subtitle">HTTP response header audit — graded against OWASP recommendations</p>
        </div>
        <form method="post">
            <?php wp_nonce_field('phpinfowp_sec_nonce'); ?>
            <input type="hidden" name="phpinfowp_recheck" value="1">
            <button type="submit" class="button button-secondary">Re-check headers</button>
        </form>
    </div>

    <?php if (isset($audit['error'])): ?>
        <div class="notice notice-error inline">
            <p>Could not fetch headers: <strong><?php echo esc_html($audit['error']); ?></strong></p>
        </div>
    <?php else: ?>

        <!-- Score card -->
        <div class="phpinfowp-score-card">
            <div class="phpinfowp-grade-circle grade-<?php echo esc_attr(strtolower(str_replace('+', 'plus', $audit['grade']))); ?>">
                <?php echo esc_html($audit['grade']); ?>
            </div>
            <div class="phpinfowp-score-card-body">
                <div class="phpinfowp-score-card-value"><?php echo esc_html($audit['score']); ?><span>/100</span></div>
                <div class="phpinfowp-score-card-meta">
                    <?php
                    $passed  = count(array_filter($audit['results'], fn($r) => $r['present']));
                    $total   = count($audit['results']);
                    $missing = $total - $passed;
                    ?>
                    <span style="color:#00a32a"><strong><?php echo $passed; ?></strong> headers set</span>
                    &nbsp;&middot;&nbsp;
                    <span style="color:<?php echo $missing ? '#d63638' : '#00a32a'; ?>"><strong><?php echo $missing; ?></strong> missing</span>
                    &nbsp;&middot;&nbsp;
                    <code style="font-size:11px"><?php echo esc_html($audit['url']); ?></code>
                    <?php if (!empty($audit['cached'])): ?>
                        &nbsp;&middot;&nbsp; <em style="color:#888">cached</em>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Header rows -->
        <div class="phpinfowp-sec-rows" style="margin-top:20px">
            <?php foreach ($audit['results'] as $row):
                $present = $row['present'];
                $border  = $present ? ($row['warning'] ? '#dba617' : '#00a32a') : '#d63638';
                $bg      = $present ? ($row['warning'] ? '#fffbf0' : '#f0faf2') : '#fff4f4';
            ?>
            <div class="phpinfowp-sec-row" style="border-left-color:<?php echo $border; ?>;background:<?php echo $bg; ?>">
                <div class="phpinfowp-sec-row-icon">
                    <?php if ($present): ?>
                        <span class="dashicons <?php echo $row['warning'] ? 'dashicons-warning' : 'dashicons-yes-alt'; ?>"
                              style="color:<?php echo $row['warning'] ? '#dba617' : '#00a32a'; ?>"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-dismiss" style="color:#d63638"></span>
                    <?php endif; ?>
                </div>
                <div class="phpinfowp-sec-row-body">
                    <div class="phpinfowp-sec-row-title">
                        <strong><?php echo esc_html($row['label']); ?></strong>
                        <span class="phpinfowp-sec-row-points" style="color:<?php echo $present ? '#00a32a' : '#d63638'; ?>">
                            <?php echo $present ? '+' . esc_html($row['points']) : '0'; ?> pts
                        </span>
                    </div>
                    <div class="phpinfowp-sec-row-desc"><?php echo esc_html($row['desc']); ?></div>
                    <?php if ($row['value']): ?>
                        <code class="phpinfowp-sec-row-value"><?php echo esc_html($row['value']); ?></code>
                    <?php else: ?>
                        <span class="phpinfowp-sec-row-missing">not set</span>
                    <?php endif; ?>
                    <?php if ($row['warning']): ?>
                        <div class="phpinfowp-sec-row-note" style="color:<?php echo $present ? '#996800' : '#d63638'; ?>">
                            <?php echo esc_html($row['warning']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <p style="margin-top:16px;font-size:13px;color:#666">
            <a href="https://owasp.org/www-project-secure-headers/" target="_blank">OWASP Secure Headers</a>
            &nbsp;&middot;&nbsp;
            <a href="<?php echo esc_url('https://securityheaders.com/?q=' . urlencode(get_site_url()) . '&followRedirects=on'); ?>" target="_blank">Verify on SecurityHeaders.com &rarr;</a>
        </p>

    <?php endif; ?>
</div>
