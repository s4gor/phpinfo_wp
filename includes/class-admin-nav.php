<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * Admin navigation — collapses 18+ submenu items into 5 logical groups.
 *
 * The sidebar shows only: Dashboard, Audit, Tools, Reports, License.
 * Within Audit / Tools / Reports, a horizontal tab bar lets users move
 * between related screens. All legacy slugs (?page=phpinfowp-eol etc.)
 * still work for backward compatibility — direct links from the admin
 * bar, dashboard widget, and admin notices keep functioning.
 */
class Phpinfo_WP_Admin_Nav {

    private static ?array $cache = null;
    private static bool $tabs_rendered = false;

    /**
     * Group definitions: each group has a label, a slug for its sidebar
     * entry, and an ordered map of tab-slug => tab-info.
     */
    public static function groups(): array {
        if (self::$cache !== null) return self::$cache;

        self::$cache = [
            'audit' => [
                'label' => 'Audit',
                'slug'  => 'phpinfowp-audit',
                'desc'  => 'Read-only health checks across PHP, config, security, and infrastructure',
                'tabs'  => [
                    'phpinfowp-config-grader'    => ['label' => 'Config Grader',     'pro' => false, 'icon' => 'dashicons-chart-bar'],
                    'phpinfowp-eol'              => ['label' => 'PHP EOL',           'pro' => false, 'icon' => 'dashicons-calendar-alt'],
                    'phpinfowp-compat'           => ['label' => 'PHP Compatibility', 'pro' => false, 'icon' => 'dashicons-yes-alt'],
                    'phpinfowp-security-headers' => ['label' => 'Security Headers',  'pro' => true,  'icon' => 'dashicons-shield-alt'],
                    'phpinfowp-ssl'              => ['label' => 'SSL Monitor',       'pro' => true,  'icon' => 'dashicons-lock'],
                    'phpinfowp-opcache'          => ['label' => 'OPcache',           'pro' => true,  'icon' => 'dashicons-performance'],
                    'phpinfowp-db-health'        => ['label' => 'Database',          'pro' => true,  'icon' => 'dashicons-database'],
                ],
            ],
            'tools' => [
                'label' => 'Tools',
                'slug'  => 'phpinfowp-tools',
                'desc'  => 'Active operations — edit config, troubleshoot, take snapshots, run diagnostics',
                'tabs'  => [
                    'phpinfowp-viewer'     => ['label' => 'phpinfo() Viewer',  'pro' => false, 'icon' => 'dashicons-info'],
                    'phpinfowp-htaccess'   => ['label' => 'PHP Config Editor', 'pro' => false, 'icon' => 'dashicons-editor-code'],
                    'phpinfowp-extensions' => ['label' => 'Extensions',        'pro' => false, 'icon' => 'dashicons-admin-plugins'],
                    'phpinfowp-info'       => ['label' => 'Basic Info',        'pro' => false, 'icon' => 'dashicons-clipboard'],
                    'phpinfowp-safemode'   => ['label' => 'Troubleshooting',   'pro' => false, 'icon' => 'dashicons-sos'],
                    'phpinfowp-snapshots'  => ['label' => 'Config Snapshots',  'pro' => true,  'icon' => 'dashicons-camera'],
                    'phpinfowp-cron'       => ['label' => 'WP-Cron Monitor',   'pro' => true,  'icon' => 'dashicons-clock'],
                    'phpinfowp-mail'       => ['label' => 'Mail',              'pro' => true,  'icon' => 'dashicons-email-alt'],
                    'phpinfowp-error-log'  => ['label' => 'Error Log',         'pro' => true,  'icon' => 'dashicons-warning'],
                ],
            ],
            'reports' => [
                'label' => 'Reports',
                'slug'  => 'phpinfowp-reports',
                'desc'  => 'Audit PDFs, change history, and outbound alerts',
                'tabs'  => [
                    'phpinfowp-log'    => ['label' => 'Activity Log', 'pro' => false, 'icon' => 'dashicons-list-view'],
                    'phpinfowp-report' => ['label' => 'Audit Report', 'pro' => true,  'icon' => 'dashicons-media-document'],
                    'phpinfowp-alerts' => ['label' => 'Alerts',       'pro' => true,  'icon' => 'dashicons-bell'],
                ],
            ],
        ];
        return self::$cache;
    }

    /**
     * Look up which group/tab a slug belongs to. Returns
     * ['group' => key, 'group_info' => array, 'tab' => slug, 'tab_info' => array]
     * or null if the slug isn't in any group.
     */
    public static function find(string $slug): ?array {
        foreach (self::groups() as $key => $group) {
            if (isset($group['tabs'][$slug])) {
                return [
                    'group'      => $key,
                    'group_info' => $group,
                    'tab'        => $slug,
                    'tab_info'   => $group['tabs'][$slug],
                ];
            }
        }
        return null;
    }

    /**
     * The first tab in a group — used as the default when the user lands
     * on the group page without a specific tab.
     */
    public static function first_tab(string $group_key): ?string {
        $g = self::groups()[$group_key] ?? null;
        if (!$g) return null;
        return array_key_first($g['tabs']);
    }

    /**
     * Render the vertical secondary sidebar for the current screen. Pass the
     * current page slug; if omitted we read it from $_GET['page']. Silently
     * no-ops if the slug isn't part of any group.
     *
     * Layout mirrors Elementor's in-page secondary nav: a fixed-width column
     * on the left of #wpbody-content with grouped vertical links. CSS pushes
     * the page's .wrap to the right via a body class added in admin_body_class.
     */
    public static function render_tabs(?string $current_slug = null): void {
        // Idempotent — render_group() calls this, then dispatches to a view
        // file that may also call it. Render only once per request.
        if (self::$tabs_rendered) return;

        $current_slug = $current_slug ?? sanitize_key($_GET['page'] ?? '');
        $info = self::find($current_slug);
        if (!$info) return;

        self::$tabs_rendered = true;

        $is_pro = Phpinfo_WP_License::is_valid();
        $group  = $info['group_info'];

        echo '<aside class="phpinfowp-side-nav" role="navigation" aria-label="' . esc_attr($group['label']) . '">';
        echo '<ul class="phpinfowp-side-nav-list">';
        foreach ($group['tabs'] as $slug => $tab) {
            $is_active = $slug === $current_slug;
            $url       = admin_url('admin.php?page=' . $slug);
            $classes   = 'phpinfowp-side-nav-item' . ($is_active ? ' is-active' : '');
            $icon      = !empty($tab['icon']) ? $tab['icon'] : 'dashicons-admin-generic';
            $badge     = (!$is_pro && !empty($tab['pro']))
                ? '<span class="phpinfowp-side-nav-pro">PRO</span>'
                : '';
            echo '<li><a href="' . esc_url($url) . '" class="' . esc_attr($classes) . '">';
            echo '<span class="dashicons ' . esc_attr($icon) . ' phpinfowp-side-nav-icon" aria-hidden="true"></span>';
            echo '<span class="phpinfowp-side-nav-label-text">' . esc_html($tab['label']) . '</span>' . $badge;
            echo '</a></li>';
        }
        echo '</ul>';
        echo '</aside>';
    }

    /**
     * Render the group landing page. The tab bar itself is injected by the
     * admin_notices hook (see auto_render below) so it appears above every
     * grouped page — not just the group landing. Here we just dispatch to
     * the active tab's view file.
     */
    public static function render_group(string $group_key): void {
        $groups = self::groups();
        if (!isset($groups[$group_key])) wp_die('Unknown group.');

        $tab = self::resolve_group_tab($group_key);

        // Briefly impersonate the tab slug so the dispatched view's internal
        // POST handlers and "is this my page?" checks work unchanged.
        $original_page = $_GET['page'] ?? '';
        $_GET['page'] = $tab;
        self::dispatch_view($tab);
        $_GET['page'] = $original_page;
    }

    /**
     * Resolve which tab is active when on a group landing page. Reads ?tab=
     * if present and valid; otherwise returns the first tab.
     */
    public static function resolve_group_tab(string $group_key): string {
        $g = self::groups()[$group_key] ?? null;
        if (!$g) return '';
        $req = sanitize_key($_GET['tab'] ?? '');
        return isset($g['tabs'][$req]) ? $req : array_key_first($g['tabs']);
    }

    /**
     * Map group-landing slugs back to their group key. Used by auto_render
     * to know "if user is on phpinfowp-audit, what group is that?"
     */
    private static function group_for_slug(string $slug): ?string {
        $m = [
            'phpinfowp-audit'   => 'audit',
            'phpinfowp-tools'   => 'tools',
            'phpinfowp-reports' => 'reports',
        ];
        return $m[$slug] ?? null;
    }

    /**
     * Hook target — emits the tab bar at the very top of every grouped page,
     * including direct legacy slugs and the new group landing slugs.
     */
    public static function auto_render(): void {
        $tab = self::current_tab_slug();
        if (!$tab) return;

        // admin_notices fires above the page's .wrap. The sidebar is
        // position:absolute via CSS, so it lifts out of normal flow and the
        // body-class added in admin_body_class pushes .wrap right to make room.
        self::render_tabs($tab);
    }

    /**
     * True when the current admin page belongs to a grouped phpinfo screen
     * (so it needs the secondary sidebar layout). Used by admin_body_class.
     */
    public static function is_group_page(): bool {
        return self::current_tab_slug() !== null;
    }

    /**
     * Resolve the active tab slug for the current request, or null if the
     * current page isn't part of any group.
     */
    private static function current_tab_slug(): ?string {
        $page = sanitize_key($_GET['page'] ?? '');
        if (!$page || !str_starts_with($page, 'phpinfo')) return null;
        if (self::find($page)) return $page;
        $group_key = self::group_for_slug($page);
        return $group_key ? self::resolve_group_tab($group_key) : null;
    }

    /**
     * Slug → view file mapping. Mirrors the require statements that the
     * Phpinfo_wp view_* methods used to do, keeping a single source of truth.
     */
    private static function dispatch_view(string $slug): void {
        $map = [
            'phpinfowp-config-grader'    => 'views/pro/config-grader.php',
            'phpinfowp-eol'              => 'views/pro/eol.php',
            'phpinfowp-compat'           => 'views/pro/compat.php',
            'phpinfowp-security-headers' => 'views/pro/security-headers.php',
            'phpinfowp-ssl'              => 'views/pro/ssl.php',
            'phpinfowp-opcache'          => 'views/pro/opcache.php',
            'phpinfowp-db-health'        => 'views/pro/db-health.php',
            'phpinfowp-htaccess'         => 'views/htaccess.php',
            'phpinfowp-safemode'         => 'views/safemode.php',
            'phpinfowp-viewer'           => 'views/phpinfo.php',
            'phpinfowp-extensions'       => 'views/extension.php',
            'phpinfowp-info'             => 'views/info.php',
            'phpinfowp-snapshots'        => 'views/pro/snapshots.php',
            'phpinfowp-cron'             => 'views/pro/cron.php',
            'phpinfowp-mail'             => 'views/pro/mail.php',
            'phpinfowp-error-log'        => 'views/pro/error-log.php',
            'phpinfowp-log'              => 'views/log.php',
            'phpinfowp-report'           => 'views/pro/report.php',
            'phpinfowp-alerts'           => 'views/pro/alerts.php',
        ];
        $file = $map[$slug] ?? null;
        if (!$file) { echo '<p>Unknown view.</p>'; return; }
        require PHPINFOWP_DIR . $file;
    }
}
