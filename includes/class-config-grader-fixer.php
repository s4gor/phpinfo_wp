<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * One-click Config Grader auto-fix engine.
 *
 * Maps each Grader check to a recommended runtime value, writes it via
 * .htaccess (`php_value`) on Apache+mod_php or .user.ini on FPM/CGI, then
 * verifies the site still returns 200. On 500 we restore the backup —
 * the user can't break their own site.
 *
 * Directives that require PHP_INI_SYSTEM (only settable in php.ini, not
 * per-directory) are detected and surfaced as "Can't auto-fix — copy this
 * line into your php.ini." Honest is better than a silent failure.
 *
 * Pro only.
 */
class Phpinfo_WP_Config_Grader_Fixer {

    const MARK_BEGIN = '# BEGIN phpinfo-wp-autofix';
    const MARK_END   = '# END phpinfo-wp-autofix';

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    /**
     * Map: ini key → ['value' => string|null, 'requires_php_ini' => bool, 'note' => ?string]
     * value=null means the key cannot be fixed via .htaccess/.user.ini.
     */
    public static function fix_map(): array {
        $is_https = is_ssl();
        return [
            'memory_limit'              => ['value' => '256M', 'requires_php_ini' => false],
            'max_execution_time'        => ['value' => '60',   'requires_php_ini' => false],
            'max_input_vars'            => ['value' => '3000', 'requires_php_ini' => false],
            'upload_max_filesize'       => ['value' => '64M',  'requires_php_ini' => false],
            'post_max_size'             => ['value' => '64M',  'requires_php_ini' => false],
            'max_input_time'            => ['value' => '60',   'requires_php_ini' => false],

            'display_errors'            => ['value' => '0',    'requires_php_ini' => false],
            'log_errors'                => ['value' => '1',    'requires_php_ini' => false],

            'session.cookie_httponly'   => ['value' => '1',    'requires_php_ini' => false],
            'session.use_strict_mode'   => ['value' => '1',    'requires_php_ini' => false],
            'session.cookie_secure'     => ['value' => $is_https ? '1' : '0', 'requires_php_ini' => false],

            // PHP_INI_SYSTEM — cannot be set per-directory
            'expose_php'                => ['value' => null, 'requires_php_ini' => true,
                'note' => 'expose_php = Off must be set in php.ini and PHP restarted.'],
            'allow_url_include'         => ['value' => null, 'requires_php_ini' => true,
                'note' => 'allow_url_include = Off must be set in php.ini.'],
            'opcache.enable'            => ['value' => null, 'requires_php_ini' => true,
                'note' => 'opcache.enable must be set in php.ini and PHP restarted.'],
            'opcache.memory_consumption'=> ['value' => null, 'requires_php_ini' => true,
                'note' => 'opcache.memory_consumption must be set in php.ini.'],
            'opcache.max_accelerated_files' => ['value' => null, 'requires_php_ini' => true,
                'note' => 'opcache.max_accelerated_files must be set in php.ini.'],
            'opcache.validate_timestamps'   => ['value' => null, 'requires_php_ini' => true,
                'note' => 'opcache.validate_timestamps must be set in php.ini.'],
        ];
    }

    public static function can_fix(string $key): bool {
        $map = self::fix_map();
        return isset($map[$key]) && $map[$key]['value'] !== null && self::target_writable();
    }

    public static function manual_note(string $key): ?string {
        $map = self::fix_map();
        return $map[$key]['note'] ?? null;
    }

    /** Detects which target file applies to this server. */
    public static function detect_target(): array {
        $sapi            = php_sapi_name();
        $server_software = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
        $is_litespeed    = str_contains($server_software, 'litespeed');
        $mode            = ($sapi === 'apache2handler' && !$is_litespeed) ? 'htaccess' : 'userini';
        $root            = get_home_path();
        $file            = $root . ($mode === 'htaccess' ? '.htaccess' : '.user.ini');
        return ['mode' => $mode, 'file' => $file, 'root' => $root];
    }

    public static function target_writable(): bool {
        $t = self::detect_target();
        if (!is_writable($t['root'])) return false;
        if (file_exists($t['file']) && !is_writable($t['file'])) return false;
        return true;
    }

    /**
     * Apply one or more fixes. Returns ['ok' => bool, 'applied' => [keys], 'skipped' => [...], 'error' => ?string].
     */
    public static function apply(array $keys): array {
        if (!self::_pro()) {
            return ['ok' => false, 'error' => 'Pro license required for one-click fixes.'];
        }
        if (!current_user_can('manage_options')) {
            return ['ok' => false, 'error' => 'Insufficient permissions.'];
        }

        $target  = self::detect_target();
        $file    = $target['file'];
        $mode    = $target['mode'];
        $map     = self::fix_map();

        if (!self::target_writable()) {
            return ['ok' => false, 'error' => 'Config file is not writable: ' . esc_html($file)];
        }

        // Resolve which keys to actually fix vs note as manual
        $to_write = [];
        $skipped  = [];
        foreach ($keys as $k) {
            $k = (string) $k;
            if (!isset($map[$k])) { $skipped[$k] = 'unknown directive'; continue; }
            if ($map[$k]['value'] === null) { $skipped[$k] = 'requires php.ini'; continue; }
            $to_write[$k] = $map[$k]['value'];
        }
        if (!$to_write) {
            return ['ok' => false, 'error' => 'Nothing to write — all selected directives require manual php.ini changes.', 'skipped' => $skipped];
        }

        $current = file_exists($file) ? file_get_contents($file) : '';
        // Backup
        $backup_path = $file . '.phpinfowp-autofix.bak';
        @file_put_contents($backup_path, $current);

        // Merge with any existing autofix block (overwrite directives we manage)
        $managed = self::extract_managed($current, $mode);
        foreach ($to_write as $k => $v) $managed[$k] = $v;
        $block = self::render_block($managed, $mode);

        $base = self::strip_managed_block($current);
        $new  = rtrim($base) . "\n\n" . $block . "\n";
        $ok   = (bool) @file_put_contents($file, $new);
        if (!$ok) return ['ok' => false, 'error' => 'Failed to write ' . esc_html($file)];

        // Verify site still responds
        $verify = self::verify_site();
        if (!$verify['ok']) {
            // Restore
            @file_put_contents($file, $current);
            return [
                'ok' => false,
                'error' => 'Configuration caused HTTP ' . $verify['code'] . ' — original config restored.',
                'rolled_back' => true,
            ];
        }

        // Log to activity log
        self::log_change('autofix applied: ' . implode(', ', array_keys($to_write)));

        return [
            'ok'      => true,
            'applied' => array_keys($to_write),
            'skipped' => $skipped,
            'mode'    => $mode,
            'file'    => $file,
        ];
    }

    // Compare the autofix block on disk against what PHP actually reports.
    // Returns rows where we wrote a value but PHP is using a different one —
    // usually a competing .user.ini in a parent directory, a stale cache,
    // or a host-level lockdown.
    //
    // Returns: [['key', 'expected', 'actual'], ...] — empty if all match.
    public static function detect_overrides(): array {
        $t = self::detect_target();
        if (!file_exists($t['file'])) return [];

        $managed = self::extract_managed((string) file_get_contents($t['file']), $t['mode']);
        $out = [];
        foreach ($managed as $key => $expected) {
            $actual = ini_get($key);
            if ($actual === false) continue;
            if (!self::values_match($key, $expected, (string) $actual)) {
                $out[] = ['key' => $key, 'expected' => $expected, 'actual' => (string) $actual];
            }
        }
        return $out;
    }

    // Compare two ini values smartly. PHP normalizes "On"/"1"/"true" to "1",
    // "Off"/"0"/"" to "", so a raw string compare gives false negatives.
    private static function values_match(string $key, string $expected, string $actual): bool {
        $boolish = ['1' => '1', 'on' => '1', 'true' => '1', 'yes' => '1',
                    '0' => '0', 'off' => '0', 'false' => '0', 'no' => '0', '' => '0'];
        $e = $boolish[strtolower(trim($expected))] ?? trim($expected);
        $a = $boolish[strtolower(trim($actual))]   ?? trim($actual);
        // Size shorthand: 256M vs 268435456, etc — normalize via bytes.
        if (preg_match('/^\d+\s*[kmg]?$/i', $e) && preg_match('/^\d+\s*[kmg]?$/i', $a)) {
            return self::to_bytes($e) === self::to_bytes($a);
        }
        return $e === $a;
    }

    private static function to_bytes(string $v): int {
        $v = trim($v);
        $last = strtolower(substr($v, -1));
        $n = (int) $v;
        if ($last === 'g') return $n * 1024 * 1024 * 1024;
        if ($last === 'm') return $n * 1024 * 1024;
        if ($last === 'k') return $n * 1024;
        return $n;
    }

    public static function revert_all(): array {
        if (!self::_pro()) return ['ok' => false, 'error' => 'Pro license required.'];
        $t = self::detect_target();
        if (!is_writable($t['file'])) return ['ok' => false, 'error' => 'Config file not writable.'];

        $current = file_exists($t['file']) ? file_get_contents($t['file']) : '';
        if (!str_contains($current, self::MARK_BEGIN)) {
            return ['ok' => true, 'reverted' => false, 'message' => 'No autofix block to revert.'];
        }
        $base = self::strip_managed_block($current);
        @file_put_contents($t['file'], rtrim($base) . "\n");
        $verify = self::verify_site();
        if (!$verify['ok']) {
            @file_put_contents($t['file'], $current);
            return ['ok' => false, 'error' => 'Reverting caused HTTP ' . $verify['code'] . '.'];
        }
        self::log_change('autofix block reverted');
        return ['ok' => true, 'reverted' => true];
    }

    private static function extract_managed(string $content, string $mode): array {
        if (!preg_match('/' . preg_quote(self::MARK_BEGIN, '/') . '\s*(.*?)\s*' . preg_quote(self::MARK_END, '/') . '/s', $content, $m)) {
            return [];
        }
        $block = $m[1];
        $out   = [];
        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) continue;
            if ($mode === 'htaccess') {
                if (preg_match('/^php_value\s+([^\s]+)\s+(.+)$/', $line, $mm)) {
                    $out[$mm[1]] = trim($mm[2], "\"' ");
                }
            } else {
                if (preg_match('/^([^=\s]+)\s*=\s*(.+)$/', $line, $mm)) {
                    $out[$mm[1]] = trim($mm[2], "\"' ");
                }
            }
        }
        return $out;
    }

    private static function strip_managed_block(string $content): string {
        return preg_replace(
            '/\n*' . preg_quote(self::MARK_BEGIN, '/') . '.*?' . preg_quote(self::MARK_END, '/') . '\n*/s',
            "\n",
            $content
        );
    }

    private static function render_block(array $pairs, string $mode): string {
        $lines = [self::MARK_BEGIN, '# Managed by phpinfo() WP — auto-fix. Edit via the Config Grader page.'];
        if ($mode === 'htaccess') {
            foreach ($pairs as $k => $v) $lines[] = "php_value {$k} \"{$v}\"";
        } else {
            foreach ($pairs as $k => $v) $lines[] = "{$k} = {$v}";
        }
        $lines[] = self::MARK_END;
        return implode("\n", $lines);
    }

    private static function verify_site(): array {
        $url = get_site_url();
        $resp = wp_remote_get($url, ['timeout' => 8, 'sslverify' => false, 'redirection' => 1]);
        if (is_wp_error($resp)) {
            // If we can't reach the site at all, treat as failure
            return ['ok' => false, 'code' => 0, 'err' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        // 500-range is bad; anything else (200, 3xx, even 401/403 for staging) is fine
        return ['ok' => $code < 500, 'code' => $code];
    }

    private static function log_change(string $msg): void {
        $log_dir  = WP_CONTENT_DIR . '/logs/phpinfo-WP';
        $log_file = $log_dir . '/log.txt';
        if (!file_exists($log_dir)) @wp_mkdir_p($log_dir);
        $user = wp_get_current_user();
        $line = sprintf("Config %s on %s by %s<br />",
            $msg, current_time('mysql'), $user->user_login ?: 'unknown');
        @file_put_contents($log_file, $line, FILE_APPEND);
    }
}
