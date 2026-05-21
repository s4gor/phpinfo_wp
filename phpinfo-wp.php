<?php
/*
Plugin Name: phpinfo() WP
Plugin URI:  https://exeebit.com/phpinfo-wp
Description: WordPress server health audit — PHP EOL timeline, config grader, security headers, SSL monitor, OPcache, error log, audit reports for clients. Free phpinfo viewer & .htaccess editor included.
Version:     7.0.2
Author:      Exeebit
Author URI:  https://exeebit.com
License:     GPLv3
*/

defined('ABSPATH') or die('Unauthorized Access');

define('PHPINFOWP_VERSION', '7.0.2');
define('PHPINFOWP_DIR',     plugin_dir_path(__FILE__));
define('PHPINFOWP_URL',     plugin_dir_url(__FILE__));

require_once PHPINFOWP_DIR . 'includes/class-license.php';
require_once PHPINFOWP_DIR . 'includes/class-eol.php';
require_once PHPINFOWP_DIR . 'includes/class-security-headers.php';
require_once PHPINFOWP_DIR . 'includes/class-snapshots.php';
require_once PHPINFOWP_DIR . 'includes/class-error-log.php';
require_once PHPINFOWP_DIR . 'includes/class-opcache.php';
require_once PHPINFOWP_DIR . 'includes/class-config-grader.php';
require_once PHPINFOWP_DIR . 'includes/class-alerts.php';
require_once PHPINFOWP_DIR . 'includes/class-ssl.php';
require_once PHPINFOWP_DIR . 'includes/class-site-health.php';
require_once PHPINFOWP_DIR . 'includes/class-compat.php';
require_once PHPINFOWP_DIR . 'includes/class-db-health.php';
require_once PHPINFOWP_DIR . 'includes/class-cron-monitor.php';
require_once PHPINFOWP_DIR . 'includes/class-mail-check.php';
require_once PHPINFOWP_DIR . 'includes/class-report.php';
require_once PHPINFOWP_DIR . 'includes/class-network.php';
require_once PHPINFOWP_DIR . 'includes/class-cli.php';
require_once PHPINFOWP_DIR . 'includes/class-admin-bar.php';
require_once PHPINFOWP_DIR . 'includes/class-admin-nav.php';
require_once PHPINFOWP_DIR . 'includes/class-safemode.php';
require_once PHPINFOWP_DIR . 'includes/class-config-grader-fixer.php';
require_once PHPINFOWP_DIR . 'includes/class-abilities.php';
require_once PHPINFOWP_DIR . 'includes/class-ai-explain.php';
require_once PHPINFOWP_DIR . 'includes/class-upgrade-notice.php';

if (!class_exists('Phpinfo_wp')):

class Phpinfo_wp {

    public function register(): void {
        add_action('admin_menu',                  [$this, 'add_admin_pages']);
        add_action('admin_bar_menu',              [$this, 'admin_bar_indicator'], 100);
        add_action('admin_enqueue_scripts',       [$this, 'enqueue']);
        add_action('admin_notices',               [$this, 'eol_admin_notice']);
        add_action('wp_dashboard_setup',          [$this, 'register_dashboard_widget']);
        add_action('phpinfowp_license_ping',      ['Phpinfo_WP_License', 'cron_ping']);
        add_action('phpinfowp_weekly_maintenance',['Phpinfo_WP_Alerts', 'cron_weekly']);
        add_filter('clean_url',                   [$this, 'script_async'], 11, 1);
        add_filter('plugin_row_meta',             [$this, 'meta'], 10, 2);
        add_filter('plugin_action_links',         [$this, 'action_links'], 10, 2);
        add_filter('admin_body_class',            [$this, 'admin_body_class']);
        // When viewing a leaf page (e.g. Config Grader), highlight its group
        // (Audit) in the WP submenu — the actual leaf <li> is CSS-hidden, so
        // without this nothing in the submenu appears active.
        add_filter('submenu_file',                [$this, 'fix_submenu_file']);
        // Inject Elementor-style flyout panels into the WP admin sidebar so
        // hovering Audit/Tools/Reports reveals all their leaf pages. Loaded
        // on every admin page because users hover from anywhere in admin.
        add_action('admin_footer',                [$this, 'render_sidebar_flyouts']);
        // Topbar (logo + page title + CTA) above every plugin page.
        add_action('in_admin_header',             [$this, 'render_topbar']);
        Phpinfo_WP_Site_Health::register();
        Phpinfo_WP_Network::register();
        Phpinfo_WP_Safemode::register();
        Phpinfo_WP_Compat::register_update_warnings();
        Phpinfo_WP_Abilities::register();
        Phpinfo_WP_AI_Explain::register();
        Phpinfo_WP_Upgrade_Notice::register();

        // Auto-render the tab bar above every grouped page (priority 5 so
        // it sits at the top of the .wrap before any other admin notices).
        add_action('admin_notices', ['Phpinfo_WP_Admin_Nav', 'auto_render'], 5);

        if (is_admin()) {
            register_shutdown_function(['Phpinfo_WP_Admin_Bar', 'record_memory_peak']);
        }
    }

    public function add_admin_pages(): void {
        // Top-level: opens the Dashboard storefront. The old top-level slug
        // 'phpinfo-wp' kept its identity for SEO + backward compatibility,
        // but now lands on Dashboard instead of phpinfo() output.
        add_menu_page(
            'phpinfo() WP', 'phpinfo() WP', 'manage_options',
            'phpinfo-wp', [$this, 'view_dashboard'],
            self::menu_icon(), 99
        );

        // The 5 VISIBLE sidebar items. Everything else is registered below
        // as a hidden submenu so direct ?page=X links still resolve.
        add_submenu_page('phpinfo-wp', 'Dashboard', 'Dashboard',
            'manage_options', 'phpinfo-wp', [$this, 'view_dashboard']);

        add_submenu_page('phpinfo-wp', 'Audit', 'Audit',
            'manage_options', 'phpinfowp-audit', [$this, 'view_audit_group']);

        add_submenu_page('phpinfo-wp', 'Tools', 'Tools',
            'manage_options', 'phpinfowp-tools', [$this, 'view_tools_group']);

        add_submenu_page('phpinfo-wp', 'Reports', 'Reports',
            'manage_options', 'phpinfowp-reports', [$this, 'view_reports_group']);

        add_submenu_page('phpinfo-wp', 'License', 'License',
            'manage_options', 'phpinfowp-license', [$this, 'view_license']);

        // Hidden registrations — these slugs work as direct URLs (admin bar,
        // dashboard widget, admin notices) but never appear in the sidebar.
        // Passing null as the parent is the WP-canonical way to register a
        // hidden admin page (supported since WP 5.3): the page is registered
        // in $_registered_pages and the action hook is bound, but it isn't
        // appended to $submenu so it never clutters the sidebar.
        $hidden = [
            // Audit group
            ['phpinfowp-config-grader',     'Config Grader',     'view_config_grader'],
            ['phpinfowp-eol',               'PHP EOL',           'view_eol'],
            ['phpinfowp-compat',            'PHP Compatibility', 'view_compat'],
            ['phpinfowp-security-headers',  'Security Headers',  'view_security_headers'],
            ['phpinfowp-ssl',               'SSL Monitor',       'view_ssl'],
            ['phpinfowp-opcache',           'OPcache',           'view_opcache'],
            ['phpinfowp-db-health',         'Database Health',   'view_db_health'],
            // Tools group
            ['phpinfowp-htaccess',          'PHP Config',        'view_htaccess'],
            ['phpinfowp-safemode',          'Troubleshooting',   'view_safemode'],
            ['phpinfowp-viewer',            'phpinfo() Viewer',  'view_phpinfo'],
            ['phpinfowp-extensions',        'Extensions',        'view_extensions'],
            ['phpinfowp-info',              'Basic Info',        'view_info'],
            ['phpinfowp-snapshots',         'Config Snapshots',  'view_snapshots'],
            ['phpinfowp-cron',              'WP Cron Monitor',   'view_cron'],
            ['phpinfowp-mail',              'Mail',              'view_mail'],
            ['phpinfowp-error-log',         'Error Log',         'view_error_log'],
            // Reports group
            ['phpinfowp-log',               'Activity Log',      'view_log'],
            ['phpinfowp-report',            'Audit Report',      'view_report'],
            ['phpinfowp-alerts',            'Alerts',            'view_alerts'],
        ];
        // Register every leaf page as a real child of phpinfo-wp so WP
        // natively highlights the toplevel + its submenu on every leaf
        // (parent_file/submenu_file filters were unreliable here). We hide
        // the extra <li>s from the visible sidebar via JS in render_sidebar_flyouts
        // — only the 5 grouped items above stay visible.
        foreach ($hidden as [$slug, $title, $cb]) {
            add_submenu_page('phpinfo-wp', $title, $title, 'manage_options', $slug, [$this, $cb]);
        }
    }

    // --- Top-level + group routers ---
    public function view_dashboard(): void     { require PHPINFOWP_DIR . 'views/dashboard.php'; }
    public function view_audit_group(): void   { Phpinfo_WP_Admin_Nav::render_group('audit'); }
    public function view_tools_group(): void   { Phpinfo_WP_Admin_Nav::render_group('tools'); }
    public function view_reports_group(): void { Phpinfo_WP_Admin_Nav::render_group('reports'); }

    // --- Free views ---
    public function view_phpinfo(): void  { self::thankyou(); require PHPINFOWP_DIR . 'views/phpinfo.php'; }
    public function view_htaccess(): void { require PHPINFOWP_DIR . 'views/htaccess.php'; }
    public function view_extensions(): void { require PHPINFOWP_DIR . 'views/extension.php'; }
    public function view_info(): void     { require PHPINFOWP_DIR . 'views/info.php'; }
    public function view_log(): void      { require PHPINFOWP_DIR . 'views/log.php'; }
    public function view_safemode(): void { require PHPINFOWP_DIR . 'views/safemode.php'; }

    // --- Pro views ---
    public function view_eol(): void             { require PHPINFOWP_DIR . 'views/pro/eol.php'; }
    public function view_security_headers(): void{ require PHPINFOWP_DIR . 'views/pro/security-headers.php'; }
    public function view_snapshots(): void       { require PHPINFOWP_DIR . 'views/pro/snapshots.php'; }
    public function view_error_log(): void       { require PHPINFOWP_DIR . 'views/pro/error-log.php'; }
    public function view_opcache(): void         { require PHPINFOWP_DIR . 'views/pro/opcache.php'; }
    public function view_config_grader(): void   { require PHPINFOWP_DIR . 'views/pro/config-grader.php'; }
    public function view_alerts(): void          { require PHPINFOWP_DIR . 'views/pro/alerts.php'; }
    public function view_ssl(): void             { require PHPINFOWP_DIR . 'views/pro/ssl.php'; }
    public function view_compat(): void          { require PHPINFOWP_DIR . 'views/pro/compat.php'; }
    public function view_db_health(): void       { require PHPINFOWP_DIR . 'views/pro/db-health.php'; }
    public function view_cron(): void            { require PHPINFOWP_DIR . 'views/pro/cron.php'; }
    public function view_mail(): void            { require PHPINFOWP_DIR . 'views/pro/mail.php'; }
    public function view_report(): void          { require PHPINFOWP_DIR . 'views/pro/report.php'; }
    public function view_license(): void         { require PHPINFOWP_DIR . 'views/pro/license.php'; }

    public function admin_bar_indicator(WP_Admin_Bar $bar): void {
        Phpinfo_WP_Admin_Bar::render($bar);
    }

    public function register_dashboard_widget(): void {
        wp_add_dashboard_widget(
            'phpinfowp_health',
            'PHP Health — phpinfo() WP',
            [$this, 'render_dashboard_widget']
        );
    }

    public function render_dashboard_widget(): void {
        $eol    = Phpinfo_WP_EOL::status();
        $is_pro = Phpinfo_WP_License::is_valid();

        $eol_color = match ($eol['status']) {
            'eol'     => '#d63638',
            'warning' => '#dba617',
            default   => '#00a32a',
        };
        $eol_label = match ($eol['status']) {
            'eol'     => 'END OF LIFE',
            'warning' => 'EXPIRING SOON',
            default   => 'SUPPORTED',
        };

        $mem_used_raw  = memory_get_usage(true);
        $mem_limit_raw = self::_parse_bytes(ini_get('memory_limit'));
        $mem_used_fmt  = size_format($mem_used_raw);
        $mem_limit_fmt = ini_get('memory_limit');
        $mem_pct       = $mem_limit_raw > 0 ? min(100, round($mem_used_raw / $mem_limit_raw * 100)) : 0;
        $mem_bar_color = $mem_pct > 85 ? '#d63638' : ($mem_pct > 65 ? '#dba617' : '#777BB3');
        ?>
        <div style="font-size:13px;line-height:1.6">

            <!-- PHP version + EOL -->
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0f0f0">
                <div>
                    <span style="font-size:20px;font-weight:700;color:#1d2327">PHP <?php echo esc_html(PHP_VERSION); ?></span>
                    <div style="font-size:11px;color:#666;margin-top:1px">EOL: <?php echo esc_html($eol['eol']); ?>
                        <?php if ($eol['status'] !== 'eol'): ?>
                            · <?php echo esc_html($eol['days']); ?> days
                        <?php endif; ?>
                    </div>
                </div>
                <span style="font-size:10px;font-weight:700;letter-spacing:.5px;padding:3px 8px;border-radius:3px;background:<?php echo $eol_color; ?>;color:#fff">
                    <?php echo esc_html($eol_label); ?>
                </span>
            </div>

            <!-- Memory -->
            <div style="padding:10px 0;border-bottom:1px solid #f0f0f0">
                <div style="display:flex;justify-content:space-between;margin-bottom:5px">
                    <span style="color:#555">Memory</span>
                    <span style="color:#333;font-weight:500"><?php echo esc_html($mem_used_fmt); ?> / <?php echo esc_html($mem_limit_fmt); ?></span>
                </div>
                <div style="background:#e0e0e0;border-radius:3px;height:6px;overflow:hidden">
                    <div style="width:<?php echo $mem_pct; ?>%;height:6px;background:<?php echo $mem_bar_color; ?>;border-radius:3px;transition:width .3s"></div>
                </div>
            </div>

            <?php
            // Config grade — shown for everyone; free shows summary, Pro adds OPcache + SSL detail
            $grade_summary = $is_pro
                ? Phpinfo_WP_Config_Grader::run()
                : Phpinfo_WP_Config_Grader::summary();
            $grade_score = $grade_summary['score'] ?? 0;
            $grade_letter = $grade_summary['grade'] ?? 'F';
            $grade_color = match(true) {
                $grade_score >= 85 => '#00a32a',
                $grade_score >= 60 => '#dba617',
                default            => '#d63638',
            };
            ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0f0f0">
                <span style="color:#555">Config Grade</span>
                <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-config-grader')); ?>" style="text-decoration:none;font-weight:700;font-size:16px;color:<?php echo $grade_color; ?>">
                    <?php echo esc_html($grade_letter); ?> <span style="font-size:11px;font-weight:400;color:#666"><?php echo (int)$grade_score; ?>/100</span>
                </a>
            </div>

            <?php if ($is_pro): ?>

                <!-- OPcache -->
                <?php if (Phpinfo_WP_OPcache::is_available()):
                    $oc = Phpinfo_WP_OPcache::status();
                    if ($oc && $oc['enabled'] && $oc['hit_rate'] !== null): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0f0f0">
                    <span style="color:#555">OPcache</span>
                    <span style="font-weight:600;color:<?php echo $oc['hit_rate'] >= 90 ? '#00a32a' : ($oc['hit_rate'] >= 70 ? '#dba617' : '#d63638'); ?>">
                        <?php echo esc_html($oc['hit_rate']); ?>% hit rate
                    </span>
                </div>
                    <?php endif; endif; ?>

                <!-- SSL -->
                <?php
                $ssl_results = Phpinfo_WP_SSL::check_all();
                $ssl_worst   = null;
                foreach ($ssl_results as $r) {
                    if (!empty($r['error'])) continue;
                    if ($ssl_worst === null || $r['days'] < $ssl_worst['days']) $ssl_worst = $r;
                }
                if ($ssl_worst): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0f0f0">
                    <span style="color:#555">SSL <span style="font-size:11px;color:#999">(<?php echo esc_html($ssl_worst['host']); ?>)</span></span>
                    <span style="font-weight:600;color:<?php echo esc_html(Phpinfo_WP_SSL::status_color($ssl_worst['status'])); ?>">
                        <?php echo $ssl_worst['days'] < 0
                            ? 'EXPIRED'
                            : esc_html($ssl_worst['days']) . ' days'; ?>
                    </span>
                </div>
                <?php endif; ?>

            <?php else:
                // Data-driven upsell — speak to what's actually broken on THIS site
                $upsell_lines = [];
                if ($eol['status'] === 'eol') {
                    $upsell_lines[] = sprintf('PHP %s reached end-of-life on %s. Pro scans every plugin for compatibility before you upgrade.', esc_html($eol['minor']), esc_html($eol['eol']));
                } elseif ($eol['status'] === 'warning') {
                    $upsell_lines[] = sprintf('PHP %s reaches EOL in %d days. Pro scans every plugin for compatibility before you upgrade.', esc_html($eol['minor']), (int)$eol['days']);
                }
                $fails = (int)($grade_summary['fails'] ?? 0);
                if ($fails > 0) {
                    $upsell_lines[] = sprintf('%d failing config check%s. Pro shows exactly what to change and where.', $fails, $fails === 1 ? '' : 's');
                }
                if (!$upsell_lines) {
                    $upsell_lines[] = 'Track SSL expiry, scan security headers, monitor OPcache, and email yourself a weekly digest.';
                }
            ?>
                <div style="padding:12px 0 8px 0;border-bottom:1px solid #f0f0f0">
                    <?php foreach ($upsell_lines as $line): ?>
                        <div style="font-size:12px;color:#555;margin-bottom:6px;line-height:1.5">▸ <?php echo $line; ?></div>
                    <?php endforeach; ?>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" style="display:inline-block;margin-top:6px;color:#777BB3;font-weight:600;text-decoration:none">
                        Upgrade to Pro &rarr;
                    </a>
                </div>

            <?php endif; ?>

            <!-- Footer link -->
            <div style="padding-top:10px;display:flex;gap:12px;font-size:12px;flex-wrap:wrap">
                <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-viewer')); ?>">Full phpinfo →</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-eol')); ?>">PHP EOL</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-config-grader')); ?>">Config Grader</a>
                <?php if ($is_pro): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-report')); ?>">Audit Report</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-alerts')); ?>">Alerts</a>
                <?php endif; ?>
            </div>

        </div>
        <?php
    }

    // Custom SVG menu icon: phpinfo() — bold parens with center dot.
    // WP applies admin color scheme via CSS mask, so a single-color SVG is correct here.
    public static function menu_icon(): string {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="black">'
             . '<path d="M7.4 2.6 C 3.4 6.2 3.4 13.8 7.4 17.4 L 9 16 C 5.8 12.8 5.8 7.2 9 4 Z"/>'
             . '<path d="M12.6 2.6 C 16.6 6.2 16.6 13.8 12.6 17.4 L 11 16 C 14.2 12.8 14.2 7.2 11 4 Z"/>'
             . '<circle cx="10" cy="10" r="1.6"/>'
             . '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private static function _parse_bytes(string $val): int {
        $val  = trim($val);
        $last = strtolower(substr($val, -1));
        $num  = (int) $val;
        return match ($last) {
            'g' => $num * 1073741824,
            'm' => $num * 1048576,
            'k' => $num * 1024,
            default => $num,
        };
    }

    // EOL notice — free feature, only on plugin admin pages
    public function eol_admin_notice(): void {
        $page = sanitize_key($_GET['page'] ?? '');
        if (!str_starts_with($page, 'phpinfo')) return;
        Phpinfo_WP_EOL::admin_notice();
    }

    public function enqueue(): void {
        $page = sanitize_key($_GET['page'] ?? '');
        if (!str_starts_with($page, 'phpinfo')) return;
        // Use filemtime as the cache key so any CSS/JS edit auto-busts browser
        // caches without bumping PHPINFOWP_VERSION. Falls back to plugin version.
        $css_path = PHPINFOWP_DIR . 'css/style.css';
        $js_path  = PHPINFOWP_DIR . 'js/scripts.js';
        $css_ver  = file_exists($css_path) ? (string) filemtime($css_path) : PHPINFOWP_VERSION;
        $js_ver   = file_exists($js_path)  ? (string) filemtime($js_path)  : PHPINFOWP_VERSION;
        wp_enqueue_style('phpinfowp', PHPINFOWP_URL . 'css/style.css', [], $css_ver);
        wp_enqueue_script('phpinfowp', PHPINFOWP_URL . 'js/scripts.js#async', [], $js_ver, true);

        if (Phpinfo_WP_AI_Explain::available()) {
            $ai_path = PHPINFOWP_DIR . 'js/ai-explain.js';
            $ai_ver  = file_exists($ai_path) ? (string) filemtime($ai_path) : PHPINFOWP_VERSION;
            wp_enqueue_script('phpinfowp-ai-explain', PHPINFOWP_URL . 'js/ai-explain.js', [], $ai_ver, true);
            wp_localize_script('phpinfowp-ai-explain', 'phpinfowpAI', Phpinfo_WP_AI_Explain::js_config());
        }
    }

    public function script_async(string $url): string {
        if (!str_contains($url, '#async')) return $url;
        return str_replace('#async', '', $url) . "' async='async";
    }

    public function meta(array $links, string $file): array {
        if (!str_contains($file, 'phpinfo-wp/phpinfo-wp.php')) return $links;
        if (Phpinfo_WP_License::is_valid()) {
            $links[] = '<span style="color:#00a32a;font-weight:600">&#10003; Pro Active</span>';
        } else {
            $links[] = '<a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" style="color:#777BB3;font-weight:600">Upgrade to Pro</a>';
        }
        return $links;
    }

    public function action_links(array $links, string $plugin_file): array {
        if (plugin_basename(__FILE__) !== $plugin_file) return $links;
        // Order: Dashboard | …WP defaults (Deactivate)… | Get Pro
        array_unshift($links, '<a href="' . admin_url('admin.php?page=phpinfo-wp') . '">Dashboard</a>');
        if (!Phpinfo_WP_License::is_valid()) {
            $links[] = '<a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" style="color:#777BB3;font-weight:600">Get Pro</a>';
        }
        return $links;
    }

    // Inject hover-flyout panels into the WP admin sidebar for the three
    // grouped items (Audit / Tools / Reports). The toplevel <li> is found
    // by id (#toplevel_page_phpinfo-wp); each group's submenu <li> is found
    // by its admin.php?page= slug. CSS handles the actual hover reveal.
    public function render_sidebar_flyouts(): void {
        $groups = Phpinfo_WP_Admin_Nav::groups();
        $is_pro = Phpinfo_WP_License::is_valid();

        $data = [];
        foreach ($groups as $key => $group) {
            $items = [];
            foreach ($group['tabs'] as $slug => $tab) {
                $items[] = [
                    'url'   => admin_url('admin.php?page=' . $slug),
                    'label' => $tab['label'],
                    'pro'   => !empty($tab['pro']) && !$is_pro,
                ];
            }
            $data[$group['slug']] = ['label' => $group['label'], 'items' => $items];
        }
        ?>
        <style id="phpinfowp-wpnav-flyout-css">
        #toplevel_page_phpinfo-wp .wp-submenu li.phpinfowp-has-flyout { position: relative; }
        #toplevel_page_phpinfo-wp .wp-submenu li.phpinfowp-has-flyout > a { padding-right: 22px; }
        #toplevel_page_phpinfo-wp .wp-submenu li.phpinfowp-has-flyout > a::after {
            content: "›";
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            opacity: .55;
            font-size: 16px;
            line-height: 1;
        }
        .phpinfowp-wpnav-flyout {
            display: none;
            position: fixed;
            min-width: 210px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            background: #2c3338;
            border-left: 1px solid #1d2327;
            padding: 6px 0;
            z-index: 9999;
            box-shadow: 4px 4px 12px rgba(0,0,0,.25);
        }
        #toplevel_page_phpinfo-wp .wp-submenu li.phpinfowp-has-flyout:hover > .phpinfowp-wpnav-flyout,
        #toplevel_page_phpinfo-wp .wp-submenu li.phpinfowp-has-flyout:focus-within > .phpinfowp-wpnav-flyout {
            display: block;
        }
        .phpinfowp-wpnav-flyout a {
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 6px 14px !important;
            color: #c3c4c7 !important;
            text-decoration: none;
            font-size: 13px !important;
            line-height: 1.4 !important;
            font-weight: 400 !important;
        }
        .phpinfowp-wpnav-flyout a:hover,
        .phpinfowp-wpnav-flyout a:focus {
            color: #fff !important;
            background: #2271b1 !important;
        }
        .phpinfowp-wpnav-flyout-pro {
            display: inline-block;
            background: #777BB3;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .4px;
            padding: 1px 5px;
            border-radius: 3px;
        }
        /* When the WP sidebar is collapsed (folded), the submenu itself is
           already a flyout — nesting another flyout breaks; suppress. */
        body.folded #toplevel_page_phpinfo-wp .phpinfowp-wpnav-flyout { display: none !important; }
        </style>
        <script>
        (function() {
            var data = <?php echo wp_json_encode($data); ?>;
            var root = document.getElementById('toplevel_page_phpinfo-wp');
            if (!root) return;
            // Hide every submenu <li> that isn't one of the 5 visible group items.
            // The leaf pages are registered as real submenus (so WP highlights the
            // toplevel natively) — we just suppress them in the sidebar list.
            var visible = ['phpinfo-wp','phpinfowp-audit','phpinfowp-tools','phpinfowp-reports','phpinfowp-license'];
            root.querySelectorAll('.wp-submenu li > a').forEach(function(a) {
                var m = (a.getAttribute('href') || '').match(/[?&]page=([\w-]+)/);
                if (!m) return;
                if (visible.indexOf(m[1]) === -1) {
                    a.parentNode.style.display = 'none';
                }
            });
            // Reposition a flyout so it never overflows the viewport. Uses
            // position:fixed coords (set on .phpinfowp-wpnav-flyout). Anchors
            // to the right edge of the parent <li>; flips upward when there
            // isn't enough room below.
            function position(li, flyout) {
                var liRect = li.getBoundingClientRect();
                var vh     = window.innerHeight;
                flyout.style.left = liRect.right + 'px';
                // Measure natural height by temporarily clearing top + showing
                var fh = flyout.offsetHeight; // already display:block via :hover
                var topPreferred = liRect.top - 6; // default: align near top of <li>
                var maxTop       = vh - fh - 8;    // keep 8px from bottom edge
                var minTop       = 40;             // keep below WP admin bar
                var top = Math.max(minTop, Math.min(topPreferred, maxTop));
                flyout.style.top = top + 'px';
            }

            Object.keys(data).forEach(function(slug) {
                var grp = data[slug];
                var anchor = root.querySelector('.wp-submenu a[href*="page=' + slug + '"]');
                if (!anchor) return;
                var li = anchor.parentNode;
                if (li.classList.contains('phpinfowp-has-flyout')) return;
                li.classList.add('phpinfowp-has-flyout');
                var flyout = document.createElement('div');
                flyout.className = 'phpinfowp-wpnav-flyout';
                grp.items.forEach(function(it) {
                    var a = document.createElement('a');
                    a.href = it.url;
                    var label = document.createElement('span');
                    label.textContent = it.label;
                    a.appendChild(label);
                    if (it.pro) {
                        var badge = document.createElement('span');
                        badge.className = 'phpinfowp-wpnav-flyout-pro';
                        badge.textContent = 'PRO';
                        a.appendChild(badge);
                    }
                    flyout.appendChild(a);
                });
                li.appendChild(flyout);

                // Reposition on hover (and again on window resize while open
                // so a viewport change doesn't strand the flyout off-screen).
                li.addEventListener('mouseenter', function() { position(li, flyout); });
                li.addEventListener('focusin',    function() { position(li, flyout); });
                window.addEventListener('resize', function() {
                    if (li.matches(':hover, :focus-within')) position(li, flyout);
                });
            });
        })();
        </script>
        <?php
    }

    // Body classes for layout:
    //   .phpinfowp-page         — on every plugin admin page (topbar + margins)
    //   .phpinfowp-has-sidenav  — only on grouped pages (Audit/Tools/Reports)
    public function admin_body_class(string $classes): string {
        if ($this->is_plugin_page()) {
            $classes .= ' phpinfowp-page';
        }
        if (Phpinfo_WP_Admin_Nav::is_group_page()) {
            $classes .= ' phpinfowp-has-sidenav';
        }
        return $classes;
    }

    // Highlight the correct group submenu item (Audit/Tools/Reports) when on
    // any of its leaf pages. Returning the group's slug tells WP which
    // submenu <li> to mark current. ?string in/out — WP can pass null.
    public function fix_submenu_file(?string $submenu_file): ?string {
        $page = sanitize_key($_GET['page'] ?? '');
        $info = Phpinfo_WP_Admin_Nav::find($page);
        if ($info) return $info['group_info']['slug'];
        return $submenu_file;
    }

    // True when we're on any phpinfo() WP admin page (toplevel, group, or
    // hidden leaf). Cheap — just inspects $_GET['page'].
    private function is_plugin_page(): bool {
        $page = sanitize_key($_GET['page'] ?? '');
        return $page === 'phpinfo-wp' || str_starts_with($page, 'phpinfowp-');
    }

    // Render the dark topbar above every plugin page: logo + plugin name +
    // current page title (left), Pro CTA / status (right). Hooked to
    // in_admin_header so it appears above #wpbody-content's normal content.
    public function render_topbar(): void {
        if (!$this->is_plugin_page()) return;
        $page   = sanitize_key($_GET['page'] ?? '');
        $title  = $this->resolve_page_title($page);
        $is_pro = Phpinfo_WP_License::is_valid();
        ?>
        <div class="phpinfowp-topbar" role="banner">
            <div class="phpinfowp-topbar-brand">
                <span class="phpinfowp-topbar-logo" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#777BB3" width="22" height="22">
                        <path d="M7.4 2.6 C 3.4 6.2 3.4 13.8 7.4 17.4 L 9 16 C 5.8 12.8 5.8 7.2 9 4 Z"/>
                        <path d="M12.6 2.6 C 16.6 6.2 16.6 13.8 12.6 17.4 L 11 16 C 14.2 12.8 14.2 7.2 11 4 Z"/>
                        <circle cx="10" cy="10" r="1.6"/>
                    </svg>
                </span>
                <span class="phpinfowp-topbar-name">phpinfo() WP</span>
                <?php if ($title): ?>
                    <span class="phpinfowp-topbar-sep" aria-hidden="true">/</span>
                    <span class="phpinfowp-topbar-title"><?php echo esc_html($title); ?></span>
                <?php endif; ?>
            </div>
            <div class="phpinfowp-topbar-actions">
                <?php if ($is_pro): ?>
                    <span class="phpinfowp-topbar-pro-active">&#10003; Pro active</span>
                <?php else: ?>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener"
                       class="phpinfowp-topbar-cta">Unlock Pro &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function resolve_page_title(string $page): string {
        if ($page === 'phpinfo-wp')        return 'Dashboard';
        if ($page === 'phpinfowp-license') return 'License';
        $info = Phpinfo_WP_Admin_Nav::find($page);
        if ($info) return $info['tab_info']['label'];
        // Group landing slugs like phpinfowp-audit / -tools / -reports.
        foreach (Phpinfo_WP_Admin_Nav::groups() as $g) {
            if ($g['slug'] === $page) return $g['label'];
        }
        return '';
    }

    public static function thankyou(): void {
        add_filter('admin_footer_text', fn() =>
            '<span>Thank you for using <a href="https://wordpress.org/plugins/phpinfo-wp/">phpinfo() WP</a>.</span>'
        );
    }

    public function activate(): void {
        flush_rewrite_rules();
        Phpinfo_WP_Snapshots::install_table();
        Phpinfo_WP_License::schedule_remote_check_event();
        if (!wp_next_scheduled('phpinfowp_weekly_maintenance')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'weekly', 'phpinfowp_weekly_maintenance');
        }
    }

    public function deactivate(): void {
        flush_rewrite_rules();
        wp_clear_scheduled_hook('phpinfowp_license_ping');
        wp_clear_scheduled_hook('phpinfowp_weekly_maintenance');
        // Clean up safemode artifacts — leaving an orphaned mu-plugin behind
        // could keep filtering plugins even after this one is gone.
        Phpinfo_WP_Safemode::stop();
        Phpinfo_WP_Safemode::uninstall_mu_plugin();
    }
}

$phpinfo_wp = new Phpinfo_wp();
$phpinfo_wp->register();

register_activation_hook(__FILE__,   [$phpinfo_wp, 'activate']);
register_deactivation_hook(__FILE__, [$phpinfo_wp, 'deactivate']);

endif;
