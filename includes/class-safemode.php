<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * Troubleshooting Mode (Safemode) — the Health Check killer feature.
 *
 * - Per-user: only the admin who started a session sees plugins disabled
 *   (cookie scoped to them). Other visitors see the site normally.
 * - Time-limited: cookies and transients expire after the chosen duration.
 *   Default 1 hour, max 4 hours.
 * - Fully reversible: nothing in the database is ever modified. The actual
 *   active_plugins option is never touched. Filters do the work at runtime.
 * - mu-plugin drop: installs a tiny must-use plugin at session start so the
 *   filter fires before regular plugins load (true code-level isolation).
 *   Without the mu-plugin we fall back to "admin-pages only" isolation which
 *   is still useful for debugging the dashboard.
 * - Explicit exit: an "End and restore" button kills the cookie + transient.
 *   Even if the user closes the browser, the session expires on its own.
 *
 * The famous Health Check bug — "exited troubleshooting and all my plugins
 * stayed disabled" — cannot happen here because we never deactivate plugins
 * in the database. Worst-case (cookie stuck, no UI access) the user clears
 * cookies and everything is back to normal.
 */
class Phpinfo_WP_Safemode {

    const COOKIE             = 'phpinfowp_safemode';
    const TRANSIENT_PREFIX   = 'phpinfowp_safemode_';
    const DEFAULT_DURATION   = 3600;     // 1 hour
    const MAX_DURATION       = 14400;    // 4 hours
    const MU_FILE            = 'phpinfowp-safemode.php';

    public static function register(): void {
        // Apply filters from main plugin too — covers admin pages even when
        // the mu-plugin isn't installed. The mu-plugin gives full coverage.
        $token = self::current_token();
        if (!$token) return;

        $session = self::load_session($token);
        if (!$session) {
            // Stale cookie → clean up to avoid confusing users
            self::clear_cookie();
            return;
        }

        // Make the live session globally accessible for filters + views
        $GLOBALS['phpinfowp_safemode_session'] = $session;

        add_filter('option_active_plugins',              [self::class, 'filter_active_plugins'], 0);
        add_filter('site_option_active_sitewide_plugins',[self::class, 'filter_sitewide_plugins'], 0);

        if (!empty($session['disable_theme'])) {
            add_filter('stylesheet', [self::class, 'default_theme'], 0);
            add_filter('template',   [self::class, 'default_theme'], 0);
        }

        // Auto-exit on logout — be sure to nuke the cookie when the user
        // logs out of the admin, since their identity check no longer applies.
        add_action('wp_logout', [self::class, 'stop']);
    }

    // -- Session lifecycle -------------------------------------------------

    public static function start(array $disabled_plugins, bool $disable_theme = false, int $duration = self::DEFAULT_DURATION): array {
        if (!current_user_can('manage_options')) {
            return ['ok' => false, 'reason' => 'insufficient_caps'];
        }

        $duration = max(60, min($duration, self::MAX_DURATION));
        $token = bin2hex(random_bytes(16));

        $session = [
            'user_id'          => get_current_user_id(),
            'disabled_plugins' => array_values(array_filter(array_map('sanitize_text_field', $disabled_plugins))),
            'disable_theme'    => (bool) $disable_theme,
            'started'          => time(),
            'expires'          => time() + $duration,
            'duration'         => $duration,
        ];

        set_transient(self::TRANSIENT_PREFIX . $token, $session, $duration);
        self::set_cookie($token, $session['expires']);

        $mu = self::install_mu_plugin();
        return ['ok' => true, 'token' => $token, 'mu' => $mu, 'session' => $session];
    }

    public static function stop(): void {
        $token = self::current_token();
        if ($token) delete_transient(self::TRANSIENT_PREFIX . $token);
        self::clear_cookie();
        unset($GLOBALS['phpinfowp_safemode_session']);
    }

    public static function is_active(): bool {
        return self::current_session() !== null;
    }

    public static function current_session(): ?array {
        $token = self::current_token();
        if (!$token) return null;
        return self::load_session($token);
    }

    private static function current_token(): ?string {
        $t = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($t) || !preg_match('/^[a-f0-9]{32}$/', $t)) return null;
        return $t;
    }

    private static function load_session(string $token): ?array {
        $session = get_transient(self::TRANSIENT_PREFIX . $token);
        if (!is_array($session)) return null;
        if ((int) ($session['expires'] ?? 0) < time()) {
            delete_transient(self::TRANSIENT_PREFIX . $token);
            return null;
        }
        // If a different user is now logged in, refuse to apply this session.
        // We don't clear the cookie here — the original user might log back in.
        $current_uid = get_current_user_id();
        if ($current_uid > 0 && (int) ($session['user_id'] ?? 0) !== $current_uid) {
            return null;
        }
        return $session;
    }

    // -- Cookie handling ---------------------------------------------------

    private static function set_cookie(string $token, int $expires): void {
        $secure = is_ssl();
        $opts = [
            'expires'  => $expires,
            'path'     => COOKIEPATH ?: '/',
            'domain'   => COOKIE_DOMAIN ?: '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if (!headers_sent()) setcookie(self::COOKIE, $token, $opts);
        $_COOKIE[self::COOKIE] = $token;
    }

    private static function clear_cookie(): void {
        if (!headers_sent()) {
            setcookie(self::COOKIE, '', [
                'expires' => time() - 3600,
                'path'    => COOKIEPATH ?: '/',
                'domain'  => COOKIE_DOMAIN ?: '',
            ]);
        }
        unset($_COOKIE[self::COOKIE]);
    }

    // -- Filters -----------------------------------------------------------

    public static function filter_active_plugins($plugins) {
        $s = $GLOBALS['phpinfowp_safemode_session'] ?? null;
        if (!$s || !is_array($plugins)) return $plugins;
        return array_values(array_diff($plugins, (array) ($s['disabled_plugins'] ?? [])));
    }

    public static function filter_sitewide_plugins($plugins) {
        $s = $GLOBALS['phpinfowp_safemode_session'] ?? null;
        if (!$s || !is_array($plugins)) return $plugins;
        foreach ((array) ($s['disabled_plugins'] ?? []) as $p) unset($plugins[$p]);
        return $plugins;
    }

    public static function default_theme($theme) {
        return defined('WP_DEFAULT_THEME') && WP_DEFAULT_THEME ? WP_DEFAULT_THEME : $theme;
    }

    // -- mu-plugin install / uninstall -------------------------------------

    public static function install_mu_plugin(): array {
        $dir = self::mu_dir();
        if (!file_exists($dir) && !@mkdir($dir, 0755, true)) {
            return ['installed' => false, 'reason' => 'mu-plugins directory could not be created'];
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return ['installed' => false, 'reason' => 'mu-plugins directory not writable'];
        }
        $target  = $dir . '/' . self::MU_FILE;
        $content = self::mu_plugin_source();
        $ok      = (bool) @file_put_contents($target, $content);
        return [
            'installed' => $ok,
            'path'      => $target,
            'reason'    => $ok ? 'installed' : 'write failed',
        ];
    }

    public static function uninstall_mu_plugin(): bool {
        $target = self::mu_dir() . '/' . self::MU_FILE;
        if (!file_exists($target)) return true;
        return (bool) @unlink($target);
    }

    public static function mu_plugin_installed(): bool {
        return file_exists(self::mu_dir() . '/' . self::MU_FILE);
    }

    private static function mu_dir(): string {
        return defined('WPMU_PLUGIN_DIR') && WPMU_PLUGIN_DIR
            ? WPMU_PLUGIN_DIR
            : WP_CONTENT_DIR . '/mu-plugins';
    }

    /**
     * The mu-plugin runs before regular plugins load. It is harmless when no
     * cookie is present (early return). Hand-rolled to avoid coupling to the
     * full phpinfo() WP runtime — mu-plugins should be minimal & dependency-free.
     */
    private static function mu_plugin_source(): string {
        return <<<'PHP'
<?php
/**
 * phpinfo() WP — Troubleshooting Safemode mu-plugin
 *
 * Installed automatically by phpinfo() WP. Filters the active plugins list
 * and (optionally) the active theme for users carrying a valid safemode
 * cookie. Harmless when no cookie is set.
 *
 * Safe to leave installed. Removed automatically on plugin uninstall.
 */
if (!defined('ABSPATH')) exit;

$_phpiwp_token = $_COOKIE['phpinfowp_safemode'] ?? '';
if (!is_string($_phpiwp_token) || !preg_match('/^[a-f0-9]{32}$/', $_phpiwp_token)) return;

$_phpiwp_apply = function($_phpiwp_token) {
    static $session = null;
    if ($session !== null) return $session ?: null;
    $s = get_transient('phpinfowp_safemode_' . $_phpiwp_token);
    if (!is_array($s) || ((int)($s['expires'] ?? 0)) < time()) {
        $session = false;
        return null;
    }
    $session = $s;
    return $s;
};

add_filter('option_active_plugins', function($plugins) use ($_phpiwp_token, $_phpiwp_apply) {
    if (!is_array($plugins)) return $plugins;
    $s = $_phpiwp_apply($_phpiwp_token);
    if (!$s) return $plugins;
    return array_values(array_diff($plugins, (array)($s['disabled_plugins'] ?? [])));
}, 0);

add_filter('site_option_active_sitewide_plugins', function($plugins) use ($_phpiwp_token, $_phpiwp_apply) {
    if (!is_array($plugins)) return $plugins;
    $s = $_phpiwp_apply($_phpiwp_token);
    if (!$s) return $plugins;
    foreach ((array)($s['disabled_plugins'] ?? []) as $p) unset($plugins[$p]);
    return $plugins;
}, 0);

add_filter('stylesheet', function($theme) use ($_phpiwp_token, $_phpiwp_apply) {
    $s = $_phpiwp_apply($_phpiwp_token);
    if (!$s || empty($s['disable_theme'])) return $theme;
    return defined('WP_DEFAULT_THEME') && WP_DEFAULT_THEME ? WP_DEFAULT_THEME : $theme;
}, 0);

add_filter('template', function($theme) use ($_phpiwp_token, $_phpiwp_apply) {
    $s = $_phpiwp_apply($_phpiwp_token);
    if (!$s || empty($s['disable_theme'])) return $theme;
    return defined('WP_DEFAULT_THEME') && WP_DEFAULT_THEME ? WP_DEFAULT_THEME : $theme;
}, 0);
PHP;
    }
}
