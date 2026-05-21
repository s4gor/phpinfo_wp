<?php
defined('ABSPATH') or die('Unauthorized Access');

$is_pro = Phpinfo_WP_License::is_valid();

if (!$is_pro) {
    // Free tier — show grade + counts only, gate the details
    $summary = Phpinfo_WP_Config_Grader::summary();
    $score   = $summary['score'];
    $grade   = $summary['grade'];
    ?>
    <div class="phpinfowp-pro-page">

        <div class="phpinfowp-page-header">
            <div>
                <h1>Config Grader</h1>
                <p class="phpinfowp-page-subtitle">PHP configuration scored against WordPress best practices</p>
            </div>
        </div>

        <div class="phpinfowp-score-card" style="margin-bottom:28px">
            <div class="phpinfowp-grade-circle grade-<?php echo esc_attr(strtolower(str_replace('+', 'plus', $grade))); ?>">
                <?php echo esc_html($grade); ?>
            </div>
            <div class="phpinfowp-score-card-body">
                <div class="phpinfowp-score-card-value"><?php echo esc_html($score); ?><span>/100</span></div>
                <div class="phpinfowp-score-card-meta">
                    <?php if ($summary['fails']): ?>
                        <span style="color:#d63638"><strong><?php echo (int)$summary['fails']; ?></strong> failing</span> &nbsp;&middot;&nbsp;
                    <?php endif; ?>
                    <?php if ($summary['warns']): ?>
                        <span style="color:#dba617"><strong><?php echo (int)$summary['warns']; ?></strong> warnings</span> &nbsp;&middot;&nbsp;
                    <?php endif; ?>
                    <span style="color:#00a32a"><strong><?php echo (int)$summary['passes']; ?></strong> passing</span>
                    &nbsp;&middot;&nbsp; <?php echo (int)$summary['total']; ?> checks total
                </div>
            </div>
        </div>

        <div class="phpinfowp-grader-teaser">
            <div class="phpinfowp-grader-teaser-blur" aria-hidden="true">
                <div class="phpinfowp-teaser-row"><span class="dashicons dashicons-dismiss" style="color:#d63638"></span> <code>memory_limit</code> — Current: <code>128M</code> → Recommended: <code>256M or higher</code></div>
                <div class="phpinfowp-teaser-row"><span class="dashicons dashicons-warning" style="color:#dba617"></span> <code>max_input_vars</code> — Current: <code>1000</code> → Recommended: <code>3000 or more</code></div>
                <div class="phpinfowp-teaser-row"><span class="dashicons dashicons-dismiss" style="color:#d63638"></span> <code>display_errors</code> — Current: <code>On</code> → Recommended: <code>Off</code></div>
                <div class="phpinfowp-teaser-row"><span class="dashicons dashicons-dismiss" style="color:#d63638"></span> <code>opcache.enable</code> — Current: <code>Off</code> → Recommended: <code>On</code></div>
            </div>
            <div class="phpinfowp-grader-teaser-cta">
                <h3>See every failing directive and how to fix it</h3>
                <p>Pro unlocks the full breakdown across Performance, Security, OPcache, and Session categories — with the exact recommended values and one-line explanations.</p>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" class="phpinfowp-upgrade-cta">Unlock full details — Get Pro &rarr;</a>
            </div>
        </div>

    </div>
    <?php
    return;
}

// Handle auto-fix actions before re-running the grader, so the page reflects the new state.
$fix_notice = '';
$fix_notice_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['phpinfowp_autofix']) && check_admin_referer('phpinfowp_autofix_nonce')) {
        $keys = isset($_POST['fix_keys']) ? (array) $_POST['fix_keys'] : [];
        $keys = array_map('sanitize_text_field', $keys);
        $res  = Phpinfo_WP_Config_Grader_Fixer::apply($keys);
        if ($res['ok']) {
            $fix_notice = 'Wrote ' . count($res['applied']) . ' directive(s) to <code>' . esc_html(basename($res['file'])) . '</code>: ' . esc_html(implode(', ', $res['applied'])) . '. <br><strong>PHP may take up to 5 minutes to pick up the new values</strong> (<code>.user.ini</code> cache). If any directive still shows the old value after 10 minutes, see the override warnings on this page.';
        } else {
            $fix_notice = $res['error'] ?? 'Auto-fix failed.';
            $fix_notice_type = 'error';
        }
    } elseif (isset($_POST['phpinfowp_autofix_revert']) && check_admin_referer('phpinfowp_autofix_revert_nonce')) {
        $res = Phpinfo_WP_Config_Grader_Fixer::revert_all();
        if ($res['ok']) {
            $fix_notice = $res['reverted'] ? 'Auto-fix block reverted.' : 'Nothing to revert.';
        } else {
            $fix_notice = $res['error'];
            $fix_notice_type = 'error';
        }
    }
}

$result     = Phpinfo_WP_Config_Grader::run();
$checks     = $result['checks'];
$score      = $result['score'];
$grade      = $result['grade'];
$categories = $result['categories'];

$status_icon = ['pass' => '✓', 'warn' => '⚠', 'fail' => '✗'];
$status_color = ['pass' => '#00a32a', 'warn' => '#996800', 'fail' => '#d63638'];

$target_info     = Phpinfo_WP_Config_Grader_Fixer::detect_target();
$target_writable = Phpinfo_WP_Config_Grader_Fixer::target_writable();
$overrides       = Phpinfo_WP_Config_Grader_Fixer::detect_overrides();
?>

<?php if ($fix_notice): ?>
    <div class="notice notice-<?php echo esc_attr($fix_notice_type); ?> is-dismissible" style="margin:0 0 16px"><p><?php echo $fix_notice; ?></p></div>
<?php endif; ?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>Config Grader <span class="phpinfowp-pro-badge">PRO</span></h1>
            <p class="phpinfowp-page-subtitle">PHP configuration scored against WordPress, WooCommerce, and security best practices</p>
        </div>
    </div>

    <?php
    $failing = array_filter($checks, fn($c) => $c['status'] === 'fail');
    $warning = array_filter($checks, fn($c) => $c['status'] === 'warn');
    $passing = array_filter($checks, fn($c) => $c['status'] === 'pass');
    ?>

    <?php
        // Determine fixable failing/warning checks
        $fixable_keys = [];
        foreach ($checks as $c) {
            if ($c['status'] === 'pass') continue;
            if (Phpinfo_WP_Config_Grader_Fixer::can_fix($c['key'])) $fixable_keys[] = $c['key'];
        }
    ?>

    <?php if ($overrides): ?>
        <div style="background:#fff4f4;border:1px solid #f0c8c8;border-left:4px solid #d63638;border-radius:6px;padding:14px 18px;margin-bottom:20px">
            <strong style="color:#a00;font-size:14px">⚠️ <?php echo count($overrides); ?> directive<?php echo count($overrides) === 1 ? '' : 's'; ?> not taking effect — your host is overriding the auto-fix</strong>
            <p style="margin:6px 0 8px;color:#444;font-size:12.5px">
                We wrote these values to <code><?php echo esc_html(basename($target_info['file'])); ?></code>, but PHP is still reporting different values. This usually means a <code>.user.ini</code> in a parent directory, your hosting control panel's PHP options, or a php.ini lock is winning the override.
            </p>
            <table style="width:100%;border-collapse:collapse;font-size:12.5px">
                <thead>
                    <tr style="text-align:left;color:#777;border-bottom:1px solid #f0c8c8">
                        <th style="padding:4px 6px;font-weight:600">Directive</th>
                        <th style="padding:4px 6px;font-weight:600">We wrote</th>
                        <th style="padding:4px 6px;font-weight:600">PHP reports</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overrides as $o): ?>
                    <tr style="border-bottom:1px solid #fbe6e6">
                        <td style="padding:6px;font-family:monospace;color:#a00"><?php echo esc_html($o['key']); ?></td>
                        <td style="padding:6px;font-family:monospace;color:#00a32a"><?php echo esc_html($o['expected']); ?></td>
                        <td style="padding:6px;font-family:monospace;color:#d63638"><?php echo esc_html($o['actual'] === '' ? '(empty)' : $o['actual']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin:10px 0 0;color:#666;font-size:12px">
                <strong>What to do:</strong> set these directives via your hosting control panel's PHP options (cPanel "MultiPHP INI Editor", Hostinger "PHP Configuration", etc.), or ask host support to raise the limit. Then revert the auto-fix block to keep your config file clean.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($fixable_keys && $target_writable): ?>
        <div style="background:linear-gradient(135deg,#f3f7ff,#eaf4ff);border:1px solid #c8d8f5;border-radius:8px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
            <div>
                <strong style="font-size:14px;color:#1a3a72">⚡ One-click fix available for <?php echo count($fixable_keys); ?> directive<?php echo count($fixable_keys) === 1 ? '' : 's'; ?></strong>
                <p style="margin:4px 0 0;color:#3a567c;font-size:12px">
                    Writes recommended values to <code><?php echo esc_html(basename($target_info['file'])); ?></code> with automatic rollback if your site returns HTTP 500.
                </p>
            </div>
            <form method="post" style="margin:0">
                <?php wp_nonce_field('phpinfowp_autofix_nonce'); ?>
                <input type="hidden" name="phpinfowp_autofix" value="1">
                <?php foreach ($fixable_keys as $k): ?>
                    <input type="hidden" name="fix_keys[]" value="<?php echo esc_attr($k); ?>">
                <?php endforeach; ?>
                <button type="submit" class="button button-primary">Fix all <?php echo count($fixable_keys); ?> →</button>
            </form>
        </div>
    <?php elseif ($fixable_keys && !$target_writable): ?>
        <div class="notice notice-warning inline" style="margin:0 0 18px"><p>
            <strong>Auto-fix unavailable:</strong> Your site root or <code><?php echo esc_html(basename($target_info['file'])); ?></code> is not writable by PHP. Fix permissions, or apply the recommended values manually via the <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-htaccess')); ?>">PHP Config editor</a>.
        </p></div>
    <?php endif; ?>

    <!-- Score card -->
    <div class="phpinfowp-score-card" style="margin-bottom:28px">
        <div class="phpinfowp-grade-circle grade-<?php echo esc_attr(strtolower(str_replace('+', 'plus', $grade))); ?>">
            <?php echo esc_html($grade); ?>
        </div>
        <div class="phpinfowp-score-card-body">
            <div class="phpinfowp-score-card-value"><?php echo esc_html($score); ?><span>/100</span></div>
            <div class="phpinfowp-score-card-meta">
                <?php if ($failing): ?>
                    <span style="color:#d63638"><strong><?php echo count($failing); ?></strong> failing</span> &nbsp;&middot;&nbsp;
                <?php endif; ?>
                <?php if ($warning): ?>
                    <span style="color:#dba617"><strong><?php echo count($warning); ?></strong> warnings</span> &nbsp;&middot;&nbsp;
                <?php endif; ?>
                <span style="color:#00a32a"><strong><?php echo count($passing); ?></strong> passing</span>
                &nbsp;&middot;&nbsp; <?php echo count($checks); ?> checks total
            </div>
        </div>
    </div>

    <!-- Checks by category -->
    <?php foreach ($categories as $cat):
        $cat_checks = array_filter($checks, fn($c) => $c['category'] === $cat);
        $cat_fails  = count(array_filter($cat_checks, fn($c) => $c['status'] === 'fail'));
        $cat_warns  = count(array_filter($cat_checks, fn($c) => $c['status'] === 'warn'));
    ?>
        <div class="phpinfowp-grade-section">
            <h2 class="phpinfowp-grade-section-heading">
                <?php echo esc_html($cat); ?>
                <?php if ($cat_fails): ?>
                    <span class="phpinfowp-grade-section-badge" style="background:#d63638"><?php echo $cat_fails; ?> failing</span>
                <?php elseif ($cat_warns): ?>
                    <span class="phpinfowp-grade-section-badge" style="background:#dba617"><?php echo $cat_warns; ?> warnings</span>
                <?php else: ?>
                    <span class="phpinfowp-grade-section-badge" style="background:#00a32a">All good</span>
                <?php endif; ?>
            </h2>

            <div class="phpinfowp-grade-checks">
                <?php foreach ($cat_checks as $c):
                    $color  = $status_color[$c['status']];
                    $bg     = $c['status'] === 'fail' ? '#fff4f4' : ($c['status'] === 'warn' ? '#fffbf0' : '#f0faf2');
                    $dicon  = $c['status'] === 'fail' ? 'dashicons-dismiss' : ($c['status'] === 'warn' ? 'dashicons-warning' : 'dashicons-yes-alt');
                ?>
                <div class="phpinfowp-grade-check" style="border-left-color:<?php echo $color; ?>;background:<?php echo $bg; ?>">
                    <div class="phpinfowp-grade-check-icon">
                        <span class="dashicons <?php echo $dicon; ?>" style="color:<?php echo $color; ?>"></span>
                    </div>
                    <div class="phpinfowp-grade-check-body">
                        <div class="phpinfowp-grade-check-key">
                            <code><?php echo esc_html($c['key']); ?></code>
                            <?php if ($c['status'] !== 'pass' && Phpinfo_WP_Config_Grader_Fixer::can_fix($c['key'])): ?>
                                <form method="post" style="display:inline-block;margin-left:8px;vertical-align:middle">
                                    <?php wp_nonce_field('phpinfowp_autofix_nonce'); ?>
                                    <input type="hidden" name="phpinfowp_autofix" value="1">
                                    <input type="hidden" name="fix_keys[]" value="<?php echo esc_attr($c['key']); ?>">
                                    <button type="submit" class="button button-small">Fix this →</button>
                                </form>
                            <?php elseif ($c['status'] !== 'pass' && ($note = Phpinfo_WP_Config_Grader_Fixer::manual_note($c['key']))): ?>
                                <span style="margin-left:8px;font-size:11px;color:#888;font-style:italic">manual fix only · <?php echo esc_html($note); ?></span>
                            <?php endif; ?>
                            <?php if ($c['status'] !== 'pass' && Phpinfo_WP_AI_Explain::available()): ?>
                                <button type="button"
                                        class="button button-small phpinfowp-ai-explain"
                                        style="margin-left:6px;vertical-align:middle"
                                        data-ai-topic="config_issue"
                                        data-ai-context="<?php echo esc_attr($c['key']); ?>"
                                        data-ai-value="<?php echo esc_attr($c['value']); ?>">Explain with AI</button>
                            <?php endif; ?>
                        </div>
                        <div class="phpinfowp-grade-check-values">
                            <span>Current: <code style="color:<?php echo $color; ?>"><?php echo esc_html($c['value']); ?></code></span>
                            <span style="color:#888">&rarr;</span>
                            <span>Recommended: <code style="color:#00a32a"><?php echo esc_html($c['good']); ?></code></span>
                        </div>
                        <div class="phpinfowp-grade-check-note"><?php echo esc_html($c['note']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <p class="description" style="margin-top:16px">
        Some directives differ between production and development. Review context before changing values.
        Use the <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-htaccess')); ?>">PHP Config editor</a> to apply changes manually.
    </p>

    <?php
        $current_target = file_exists($target_info['file']) ? @file_get_contents($target_info['file']) : '';
        $has_autofix_block = is_string($current_target) && str_contains($current_target, Phpinfo_WP_Config_Grader_Fixer::MARK_BEGIN);
    ?>
    <?php if ($has_autofix_block): ?>
        <form method="post" style="margin-top:8px" onsubmit="return confirm('Revert the entire auto-fix block? This removes every value phpinfo() WP added — manual edits to your config file are untouched.')">
            <?php wp_nonce_field('phpinfowp_autofix_revert_nonce'); ?>
            <input type="hidden" name="phpinfowp_autofix_revert" value="1">
            <button type="submit" class="button-link" style="color:#a00;font-size:12px">Revert all auto-fix changes</button>
        </form>
    <?php endif; ?>
</div>
