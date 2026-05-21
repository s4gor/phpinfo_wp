<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');

$notice = '';
$action = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['phpinfowp_safemode_start']) && check_admin_referer('phpinfowp_safemode_start_nonce')) {
        $disabled = isset($_POST['disable']) && is_array($_POST['disable'])
            ? array_map('sanitize_text_field', (array) $_POST['disable'])
            : [];
        $disable_theme = !empty($_POST['disable_theme']);
        $duration = (int) ($_POST['duration'] ?? Phpinfo_WP_Safemode::DEFAULT_DURATION);
        $result = Phpinfo_WP_Safemode::start($disabled, $disable_theme, $duration);
        $action = 'started';
        $start_result = $result;
        // Redirect so the new cookie/transient applies on the next request
        wp_safe_redirect(add_query_arg(['safemode' => 'started'], admin_url('admin.php?page=phpinfowp-safemode')));
        exit;
    }
    if (isset($_POST['phpinfowp_safemode_stop']) && check_admin_referer('phpinfowp_safemode_stop_nonce')) {
        Phpinfo_WP_Safemode::stop();
        wp_safe_redirect(add_query_arg(['safemode' => 'stopped'], admin_url('admin.php?page=phpinfowp-safemode')));
        exit;
    }
    if (isset($_POST['phpinfowp_safemode_remove_mu']) && check_admin_referer('phpinfowp_safemode_remove_mu_nonce')) {
        $ok = Phpinfo_WP_Safemode::uninstall_mu_plugin();
        $notice = $ok ? 'mu-plugin removed.' : 'Could not remove mu-plugin (check file permissions).';
    }
}

if (isset($_GET['safemode']) && $_GET['safemode'] === 'started') $notice = 'Troubleshooting Mode engaged — only your admin session sees plugins disabled. The site is normal for other visitors.';
if (isset($_GET['safemode']) && $_GET['safemode'] === 'stopped') $notice = 'Troubleshooting Mode ended. All plugins restored.';

$session     = Phpinfo_WP_Safemode::current_session();
$is_active   = $session !== null;
$mu_present  = Phpinfo_WP_Safemode::mu_plugin_installed();

if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
$all_plugins    = get_plugins();
$active_plugins = (array) get_option('active_plugins', []);
?>

<div class="wrap phpinfowp-safemode">
    <h1 style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <span class="dashicons dashicons-shield-alt" style="font-size:28px;width:28px;height:28px;color:#777BB3"></span>
        Troubleshooting Mode
    </h1>
    <p style="margin:0 0 18px;color:#666;max-width:780px;font-size:14px">
        Safely disable plugins or revert to a default theme <strong>just for your own admin session</strong> to debug
        conflicts. Visitors see the site normally. Everything is reversible — no plugin is ever deactivated in the
        database. Session expires automatically; you can also exit any time.
    </p>

    <?php if ($notice): ?>
        <div class="notice notice-success is-dismissible" style="margin:0 0 18px"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <?php if ($is_active): ?>
        <?php
            $remaining = max(0, $session['expires'] - time());
            $mins      = (int) floor($remaining / 60);
            $secs      = $remaining % 60;
            $disabled  = (array) ($session['disabled_plugins'] ?? []);
        ?>
        <div style="background:linear-gradient(135deg,#fff4e5,#fff8e5);border:1px solid #dba617;border-radius:8px;padding:18px 22px;margin-bottom:22px">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px">
                <div>
                    <strong style="color:#9a6e00;font-size:15px">⚠ Troubleshooting Mode is ACTIVE</strong>
                    <p style="margin:6px 0 0;color:#5d4500;font-size:13px">
                        <?php echo count($disabled); ?> plugin<?php echo count($disabled) === 1 ? '' : 's'; ?> disabled for your session.
                        <?php if (!empty($session['disable_theme'])): ?>Default theme in use.<?php endif; ?>
                    </p>
                </div>
                <div style="text-align:right">
                    <div id="phpinfowp-safemode-countdown"
                         data-expires="<?php echo esc_attr($session['expires']); ?>"
                         style="font-size:22px;font-weight:700;color:#9a6e00;font-family:ui-monospace,monospace">
                        <?php printf('%d:%02d', $mins, $secs); ?>
                    </div>
                    <div style="font-size:11px;color:#9a6e00;text-transform:uppercase;letter-spacing:.5px">remaining</div>
                </div>
            </div>

            <form method="post" style="margin-top:14px">
                <?php wp_nonce_field('phpinfowp_safemode_stop_nonce'); ?>
                <input type="hidden" name="phpinfowp_safemode_stop" value="1">
                <button type="submit" class="button button-primary" style="background:#d63638;border-color:#d63638">
                    End Troubleshooting Mode &amp; restore everything
                </button>
                <a href="<?php echo esc_url(admin_url()); ?>" class="button" style="margin-left:8px">Browse admin with plugins disabled →</a>
            </form>
        </div>

        <h2 style="margin:24px 0 10px;font-size:16px">Plugins disabled in this session</h2>
        <table class="widefat striped" style="max-width:780px">
            <thead><tr><th>Plugin</th><th style="width:140px">Status in DB</th></tr></thead>
            <tbody>
                <?php foreach ($disabled as $slug): $info = $all_plugins[$slug] ?? null; ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($info['Name'] ?? $slug); ?></strong>
                            <div style="font-size:11px;color:#999"><?php echo esc_html($slug); ?></div>
                        </td>
                        <td><span style="color:#00a32a;font-weight:600">✓ Still active</span> <span style="color:#999;font-size:11px">(hidden from your view only)</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
        (function() {
            var el = document.getElementById('phpinfowp-safemode-countdown');
            if (!el) return;
            var expires = parseInt(el.dataset.expires, 10);
            function tick() {
                var remaining = Math.max(0, expires - Math.floor(Date.now() / 1000));
                var m = Math.floor(remaining / 60);
                var s = remaining % 60;
                el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
                if (remaining <= 0) {
                    el.textContent = 'expired';
                    setTimeout(function() { window.location.reload(); }, 1500);
                    return;
                }
                setTimeout(tick, 1000);
            }
            tick();
        })();
        </script>

    <?php else: ?>

        <div style="background:#f6f7f7;border:1px solid #e0e0e0;border-radius:8px;padding:18px 22px;margin-bottom:20px">
            <strong style="font-size:14px">How this differs from the official Health Check plugin</strong>
            <ul style="margin:8px 0 0;color:#444;font-size:13px;list-style:disc;padding-left:22px">
                <li>The actual <code>active_plugins</code> option is never modified — your site cannot be left broken.</li>
                <li>Cookie is bound to your user account, time-limited (1–4 hours), and expires automatically.</li>
                <li>Every page has an explicit <em>End</em> button. You cannot lock yourself out.</li>
                <li>Other visitors are unaffected — they see the live site with all plugins active.</li>
            </ul>
        </div>

        <?php if (!$mu_present): ?>
            <div class="notice notice-info inline" style="margin:0 0 18px;padding:12px 14px">
                <p style="margin:0">
                    <strong>Heads up:</strong> The optional <code>mu-plugin</code> isn't installed yet, so plugins
                    will be hidden from <em>admin pages and admin AJAX</em> only — not from front-end pages or cron.
                    Engaging Troubleshooting Mode will try to install it automatically; if your <code>wp-content/mu-plugins</code>
                    folder isn't writable you'll still get admin-only isolation.
                </p>
            </div>
        <?php else: ?>
            <p style="margin:0 0 16px;color:#666;font-size:12px">
                ✓ mu-plugin installed at <code><?php echo esc_html(str_replace(ABSPATH, '', defined('WPMU_PLUGIN_DIR') && WPMU_PLUGIN_DIR ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins') . '/' . Phpinfo_WP_Safemode::MU_FILE); ?></code>
                — full request-level isolation available.
                <form method="post" style="display:inline">
                    <?php wp_nonce_field('phpinfowp_safemode_remove_mu_nonce'); ?>
                    <input type="hidden" name="phpinfowp_safemode_remove_mu" value="1">
                    <button type="submit" class="button-link" style="color:#a00;margin-left:6px;font-size:12px"
                            onclick="return confirm('Remove the mu-plugin? Troubleshooting Mode will fall back to admin-only isolation.')">remove</button>
                </form>
            </p>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('phpinfowp_safemode_start_nonce'); ?>
            <input type="hidden" name="phpinfowp_safemode_start" value="1">

            <h2 style="margin:0 0 8px;font-size:16px">Pick what to disable</h2>

            <div style="margin:8px 0 16px">
                <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                    <input type="checkbox" id="phpinfowp-toggle-all" onchange="phpinfowpToggleAll(this.checked)">
                    Select all active plugins
                </label>
            </div>

            <table class="widefat striped" style="max-width:780px">
                <thead>
                    <tr>
                        <th style="width:36px"></th>
                        <th>Plugin</th>
                        <th style="width:120px">Version</th>
                        <th style="width:100px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_plugins as $slug => $info):
                        $is_active = in_array($slug, $active_plugins, true);
                    ?>
                        <tr style="<?php echo $is_active ? '' : 'opacity:.5'; ?>">
                            <td>
                                <input type="checkbox" name="disable[]" value="<?php echo esc_attr($slug); ?>"
                                       class="phpinfowp-disable-checkbox"
                                       <?php disabled(!$is_active); ?>>
                            </td>
                            <td>
                                <strong><?php echo esc_html($info['Name']); ?></strong>
                                <div style="font-size:11px;color:#999"><?php echo esc_html($slug); ?></div>
                            </td>
                            <td><?php echo esc_html($info['Version']); ?></td>
                            <td>
                                <?php if ($is_active): ?>
                                    <span style="color:#00a32a;font-weight:600">Active</span>
                                <?php else: ?>
                                    <span style="color:#999">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2 style="margin:24px 0 8px;font-size:16px">Theme</h2>
            <label style="display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="disable_theme" value="1">
                Also revert to the default WordPress theme (<?php echo esc_html(defined('WP_DEFAULT_THEME') && WP_DEFAULT_THEME ? WP_DEFAULT_THEME : 'twentytwentyfour'); ?>)
            </label>

            <h2 style="margin:24px 0 8px;font-size:16px">Duration</h2>
            <select name="duration" style="min-width:200px">
                <option value="900">15 minutes</option>
                <option value="1800">30 minutes</option>
                <option value="3600" selected>1 hour (recommended)</option>
                <option value="7200">2 hours</option>
                <option value="14400">4 hours (max)</option>
            </select>

            <p style="margin:24px 0 0">
                <button type="submit" class="button button-primary button-hero" id="phpinfowp-start-btn" disabled>
                    Engage Troubleshooting Mode
                </button>
                <span style="margin-left:12px;color:#999;font-size:12px">Select at least one plugin to enable the button</span>
            </p>
        </form>

        <script>
        function phpinfowpToggleAll(checked) {
            document.querySelectorAll('.phpinfowp-disable-checkbox:not(:disabled)').forEach(function(cb) {
                cb.checked = checked;
            });
            phpinfowpUpdateStartBtn();
        }
        function phpinfowpUpdateStartBtn() {
            var any = document.querySelectorAll('.phpinfowp-disable-checkbox:checked').length > 0;
            document.getElementById('phpinfowp-start-btn').disabled = !any;
        }
        document.querySelectorAll('.phpinfowp-disable-checkbox').forEach(function(cb) {
            cb.addEventListener('change', phpinfowpUpdateStartBtn);
        });
        </script>

    <?php endif; ?>
</div>
