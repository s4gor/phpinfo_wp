<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Site_Health {

    public static function register(): void {
        add_filter('site_status_tests', [__CLASS__, 'add_tests']);
    }

    public static function add_tests(array $tests): array {
        $tests['direct']['phpinfowp_php_eol'] = [
            'label' => __('PHP version end-of-life status'),
            'test'  => [__CLASS__, 'test_php_eol'],
        ];
        $tests['direct']['phpinfowp_display_errors'] = [
            'label' => __('PHP error display is disabled in production'),
            'test'  => [__CLASS__, 'test_display_errors'],
        ];
        $tests['direct']['phpinfowp_allow_url_include'] = [
            'label' => __('PHP remote file inclusion is disabled'),
            'test'  => [__CLASS__, 'test_allow_url_include'],
        ];
        $tests['direct']['phpinfowp_expose_php'] = [
            'label' => __('PHP version is not exposed in HTTP headers'),
            'test'  => [__CLASS__, 'test_expose_php'],
        ];
        $tests['direct']['phpinfowp_memory'] = [
            'label' => __('PHP memory limit is sufficient'),
            'test'  => [__CLASS__, 'test_memory'],
        ];
        $tests['direct']['phpinfowp_opcache'] = [
            'label' => __('OPcache is enabled'),
            'test'  => [__CLASS__, 'test_opcache'],
        ];
        return $tests;
    }

    // ── Individual tests ─────────────────────────────────────────────

    public static function test_php_eol(): array {
        $s = Phpinfo_WP_EOL::status();

        if ($s['status'] === 'eol') {
            return [
                'label'       => 'PHP ' . $s['minor'] . ' is past end-of-life — no security patches',
                'status'      => 'critical',
                'badge'       => ['label' => 'Security', 'color' => 'red'],
                'description' => '<p>PHP ' . esc_html($s['minor']) . ' reached end-of-life on <strong>' . esc_html($s['eol']) . '</strong>. '
                               . 'No security updates are being issued. Your site is exposed to unpatched PHP vulnerabilities. '
                               . 'Contact your host and request an upgrade to PHP 8.2 or newer immediately.</p>',
                'actions'     => '<a href="' . esc_url(admin_url('admin.php?page=phpinfowp-eol')) . '">View PHP EOL timeline</a>',
                'test'        => 'phpinfowp_php_eol',
            ];
        }

        if ($s['status'] === 'warning') {
            return [
                'label'       => 'PHP ' . $s['minor'] . ' reaches end-of-life in ' . $s['days'] . ' days',
                'status'      => 'recommended',
                'badge'       => ['label' => 'Security', 'color' => 'orange'],
                'description' => '<p>PHP ' . esc_html($s['minor']) . ' will reach end-of-life on <strong>' . esc_html($s['eol']) . '</strong> '
                               . '(' . esc_html($s['days']) . ' days from now). After that date, no security patches will be issued. '
                               . 'Plan your PHP upgrade before this deadline.</p>',
                'actions'     => '<a href="' . esc_url(admin_url('admin.php?page=phpinfowp-eol')) . '">View PHP EOL timeline</a>',
                'test'        => 'phpinfowp_php_eol',
            ];
        }

        return [
            'label'       => 'PHP ' . PHP_VERSION . ' is actively supported',
            'status'      => 'good',
            'badge'       => ['label' => 'Security', 'color' => 'blue'],
            'description' => '<p>PHP ' . esc_html(PHP_VERSION) . ' is actively supported until <strong>' . esc_html($s['eol']) . '</strong> '
                           . '(' . esc_html($s['days']) . ' days from now).</p>',
            'actions'     => '',
            'test'        => 'phpinfowp_php_eol',
        ];
    }

    public static function test_display_errors(): array {
        $val = ini_get('display_errors');
        $on  = ($val === '1' || strtolower((string) $val) === 'on');

        if ($on) {
            return [
                'label'       => 'PHP is configured to display errors publicly',
                'status'      => 'critical',
                'badge'       => ['label' => 'Security', 'color' => 'red'],
                'description' => '<p><code>display_errors</code> is <strong>On</strong>. PHP error messages are visible to site visitors, '
                               . 'which can expose file paths, database credentials, and internal logic. '
                               . 'Set <code>display_errors = Off</code> in <code>php.ini</code> or your <code>.user.ini</code>.</p>',
                'actions'     => '<a href="' . esc_url(admin_url('admin.php?page=phpinfowp-config-grader')) . '">View Config Grader</a>',
                'test'        => 'phpinfowp_display_errors',
            ];
        }

        return [
            'label'       => 'PHP error display is correctly disabled',
            'status'      => 'good',
            'badge'       => ['label' => 'Security', 'color' => 'blue'],
            'description' => '<p><code>display_errors</code> is <strong>Off</strong>. PHP errors are not visible to site visitors.</p>',
            'actions'     => '',
            'test'        => 'phpinfowp_display_errors',
        ];
    }

    public static function test_allow_url_include(): array {
        $val = ini_get('allow_url_include');
        $on  = ($val === '1' || strtolower((string) $val) === 'on');

        if ($on) {
            return [
                'label'       => 'PHP remote file inclusion (allow_url_include) is enabled',
                'status'      => 'critical',
                'badge'       => ['label' => 'Security', 'color' => 'red'],
                'description' => '<p><code>allow_url_include</code> is <strong>On</strong>. This allows PHP code to include files from remote URLs, '
                               . 'which is a common vector for Remote File Inclusion (RFI) attacks. '
                               . 'This directive should be disabled on all production servers. Contact your host if you cannot change it.</p>',
                'actions'     => '<a href="' . esc_url(admin_url('admin.php?page=phpinfowp-htaccess')) . '">Open PHP Config editor</a>',
                'test'        => 'phpinfowp_allow_url_include',
            ];
        }

        return [
            'label'       => 'PHP remote file inclusion is disabled',
            'status'      => 'good',
            'badge'       => ['label' => 'Security', 'color' => 'blue'],
            'description' => '<p><code>allow_url_include</code> is <strong>Off</strong>. Remote file inclusion attacks are blocked at the PHP level.</p>',
            'actions'     => '',
            'test'        => 'phpinfowp_allow_url_include',
        ];
    }

    public static function test_expose_php(): array {
        $val = ini_get('expose_php');
        $on  = ($val === '1' || strtolower((string) $val) === 'on');

        if ($on) {
            return [
                'label'       => 'PHP version is exposed in HTTP response headers',
                'status'      => 'recommended',
                'badge'       => ['label' => 'Security', 'color' => 'orange'],
                'description' => '<p><code>expose_php</code> is <strong>On</strong>. PHP adds an <code>X-Powered-By: PHP/x.x.x</code> header to every response, '
                               . 'advertising your exact PHP version to attackers. Set <code>expose_php = Off</code> to remove it.</p>',
                'actions'     => '<a href="' . esc_url(admin_url('admin.php?page=phpinfowp-htaccess')) . '">Open PHP Config editor</a>',
                'test'        => 'phpinfowp_expose_php',
            ];
        }

        return [
            'label'       => 'PHP version is not exposed in HTTP headers',
            'status'      => 'good',
            'badge'       => ['label' => 'Security', 'color' => 'blue'],
            'description' => '<p><code>expose_php</code> is <strong>Off</strong>. The PHP version is not advertised in response headers.</p>',
            'actions'     => '',
            'test'        => 'phpinfowp_expose_php',
        ];
    }

    public static function test_memory(): array {
        $raw   = ini_get('memory_limit');
        $bytes = self::_to_bytes($raw);

        if ($bytes > 0 && $bytes < 128 * 1048576) {
            return [
                'label'       => 'PHP memory limit is below recommended minimum (128 MB)',
                'status'      => 'recommended',
                'badge'       => ['label' => 'Performance', 'color' => 'orange'],
                'description' => '<p><code>memory_limit</code> is set to <strong>' . esc_html($raw) . '</strong>. '
                               . 'WordPress and popular plugins routinely require 128 MB or more. A low limit causes white screens and failed operations. '
                               . 'The recommended minimum is 256 MB.</p>',
                'actions'     => '<a href="' . esc_url(admin_url('admin.php?page=phpinfowp-htaccess')) . '">Open PHP Config editor</a>',
                'test'        => 'phpinfowp_memory',
            ];
        }

        return [
            'label'       => 'PHP memory limit is sufficient (' . esc_html($raw) . ')',
            'status'      => 'good',
            'badge'       => ['label' => 'Performance', 'color' => 'blue'],
            'description' => '<p><code>memory_limit</code> is set to <strong>' . esc_html($raw) . '</strong>, which meets the recommended minimum of 128 MB.</p>',
            'actions'     => '',
            'test'        => 'phpinfowp_memory',
        ];
    }

    public static function test_opcache(): array {
        if (!function_exists('opcache_get_status')) {
            return [
                'label'       => 'OPcache extension is not installed',
                'status'      => 'recommended',
                'badge'       => ['label' => 'Performance', 'color' => 'orange'],
                'description' => '<p>The <code>opcache</code> PHP extension is not installed. OPcache caches compiled PHP bytecode in memory, '
                               . 'reducing CPU usage by 50–80% and speeding up every page load. Ask your host to enable it.</p>',
                'actions'     => '',
                'test'        => 'phpinfowp_opcache',
            ];
        }

        $status = @opcache_get_status(false);
        if (empty($status['opcache_enabled'])) {
            return [
                'label'       => 'OPcache is installed but disabled',
                'status'      => 'recommended',
                'badge'       => ['label' => 'Performance', 'color' => 'orange'],
                'description' => '<p>The <code>opcache</code> extension is installed but not enabled. '
                               . 'Enable it by setting <code>opcache.enable = 1</code> in your PHP configuration.</p>',
                'actions'     => '<a href="' . esc_url(admin_url('admin.php?page=phpinfowp-opcache')) . '">View OPcache dashboard</a>',
                'test'        => 'phpinfowp_opcache',
            ];
        }

        return [
            'label'       => 'OPcache is enabled and running',
            'status'      => 'good',
            'badge'       => ['label' => 'Performance', 'color' => 'blue'],
            'description' => '<p>OPcache is active. PHP bytecode is cached in memory, improving performance on every request.</p>',
            'actions'     => '<a href="' . esc_url(admin_url('admin.php?page=phpinfowp-opcache')) . '">View OPcache dashboard</a>',
            'test'        => 'phpinfowp_opcache',
        ];
    }

    private static function _to_bytes(string $val): int {
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
}
