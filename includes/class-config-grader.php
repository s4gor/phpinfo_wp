<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Config_Grader {

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    // Each check: weight (1=nice, 2=important, 3=critical), category, description, recommended value/logic
    private static function checks(): array {
        $is_https = is_ssl();

        return [
            // --- Memory & Execution ---
            [
                'key'      => 'memory_limit',
                'label'    => 'Memory Limit',
                'category' => 'Performance',
                'weight'   => 3,
                'check'    => fn($v) => self::bytes($v) >= 256 * MB_IN_BYTES,
                'warn'     => fn($v) => self::bytes($v) >= 128 * MB_IN_BYTES,
                'good'     => '256M or higher',
                'note'     => 'WordPress recommends 256M. WooCommerce needs 512M+.',
            ],
            [
                'key'      => 'max_execution_time',
                'label'    => 'Max Execution Time',
                'category' => 'Performance',
                'weight'   => 2,
                'check'    => fn($v) => (int)$v >= 60,
                'warn'     => fn($v) => (int)$v >= 30,
                'good'     => '60 seconds or more',
                'note'     => 'Imports, updates, and backups can fail with less than 60s.',
            ],
            [
                'key'      => 'max_input_vars',
                'label'    => 'Max Input Vars',
                'category' => 'Performance',
                'weight'   => 2,
                'check'    => fn($v) => (int)$v >= 3000,
                'warn'     => fn($v) => (int)$v >= 1000,
                'good'     => '3000 or more',
                'note'     => 'Page builders and complex forms need 3000+. Default 1000 breaks many plugins.',
            ],
            [
                'key'      => 'upload_max_filesize',
                'label'    => 'Upload Max Filesize',
                'category' => 'Performance',
                'weight'   => 2,
                'check'    => fn($v) => self::bytes($v) >= 64 * MB_IN_BYTES,
                'warn'     => fn($v) => self::bytes($v) >= 16 * MB_IN_BYTES,
                'good'     => '64M or higher',
                'note'     => 'Media uploads, plugin installs, and theme uploads require a reasonable limit.',
            ],
            [
                'key'      => 'post_max_size',
                'label'    => 'Post Max Size',
                'category' => 'Performance',
                'weight'   => 1,
                'check'    => fn($v) => self::bytes($v) >= 64 * MB_IN_BYTES,
                'warn'     => fn($v) => self::bytes($v) >= 16 * MB_IN_BYTES,
                'good'     => '64M or higher',
                'note'     => 'Must be at least equal to upload_max_filesize.',
            ],
            [
                'key'      => 'max_input_time',
                'label'    => 'Max Input Time',
                'category' => 'Performance',
                'weight'   => 1,
                'check'    => fn($v) => (int)$v >= 60 || (int)$v === -1,
                'warn'     => fn($v) => (int)$v >= 30,
                'good'     => '60 seconds or -1 (unlimited)',
                'note'     => 'Time allowed to parse request data.',
            ],

            // --- Security & Error Handling ---
            [
                'key'      => 'display_errors',
                'label'    => 'Display Errors',
                'category' => 'Security',
                'weight'   => 3,
                'check'    => fn($v) => in_array(strtolower($v), ['0', 'off', ''], true),
                'warn'     => null,
                'good'     => 'Off',
                'note'     => 'Exposing errors in production leaks code paths and server info to attackers.',
            ],
            [
                'key'      => 'expose_php',
                'label'    => 'Expose PHP Version',
                'category' => 'Security',
                'weight'   => 2,
                'check'    => fn($v) => in_array(strtolower($v), ['0', 'off', ''], true),
                'warn'     => null,
                'good'     => 'Off',
                'note'     => 'Hides PHP version from the X-Powered-By response header.',
            ],
            [
                'key'      => 'allow_url_include',
                'label'    => 'Allow URL Include',
                'category' => 'Security',
                'weight'   => 3,
                'check'    => fn($v) => in_array(strtolower($v), ['0', 'off', ''], true),
                'warn'     => null,
                'good'     => 'Off',
                'note'     => 'Enabled allow_url_include is a critical remote code execution risk.',
            ],
            [
                'key'      => 'log_errors',
                'label'    => 'Log Errors',
                'category' => 'Security',
                'weight'   => 2,
                'check'    => fn($v) => in_array(strtolower($v), ['1', 'on'], true),
                'warn'     => null,
                'good'     => 'On',
                'note'     => 'Errors should be logged silently, not displayed.',
            ],
            [
                'key'      => 'session.cookie_httponly',
                'label'    => 'Session Cookie HttpOnly',
                'category' => 'Security',
                'weight'   => 2,
                'check'    => fn($v) => in_array(strtolower($v), ['1', 'on'], true),
                'warn'     => null,
                'good'     => 'On',
                'note'     => 'Prevents JavaScript from accessing session cookies (mitigates XSS).',
            ],
            [
                'key'      => 'session.use_strict_mode',
                'label'    => 'Session Strict Mode',
                'category' => 'Security',
                'weight'   => 2,
                'check'    => fn($v) => in_array(strtolower($v), ['1', 'on'], true),
                'warn'     => null,
                'good'     => 'On',
                'note'     => 'Rejects uninitialized session IDs, preventing session fixation attacks.',
            ],
            [
                'key'      => 'session.cookie_secure',
                'label'    => 'Session Cookie Secure',
                'category' => 'Security',
                'weight'   => $is_https ? 2 : 1,
                'check'    => fn($v) => !$is_https || in_array(strtolower($v), ['1', 'on'], true),
                'warn'     => null,
                'good'     => $is_https ? 'On (site uses HTTPS)' : 'N/A — no HTTPS detected',
                'note'     => 'Ensures session cookies are only sent over HTTPS.',
            ],

            // --- OPcache ---
            [
                'key'      => 'opcache.enable',
                'label'    => 'OPcache Enabled',
                'category' => 'OPcache',
                'weight'   => 3,
                'check'    => fn($v) => in_array(strtolower($v), ['1', 'on'], true),
                'warn'     => null,
                'good'     => 'On',
                'note'     => 'OPcache can reduce PHP CPU usage by 50–80% on typical WordPress sites.',
            ],
            [
                'key'      => 'opcache.memory_consumption',
                'label'    => 'OPcache Memory',
                'category' => 'OPcache',
                'weight'   => 2,
                'check'    => fn($v) => (int)$v >= 128,
                'warn'     => fn($v) => (int)$v >= 64,
                'good'     => '128 MB or more',
                'note'     => 'Larger sites with many plugins need 128M–256M of OPcache memory.',
            ],
            [
                'key'      => 'opcache.max_accelerated_files',
                'label'    => 'OPcache Max Files',
                'category' => 'OPcache',
                'weight'   => 2,
                'check'    => fn($v) => (int)$v >= 4000,
                'warn'     => fn($v) => (int)$v >= 2000,
                'good'     => '4000 or more',
                'note'     => 'WordPress + plugins can exceed 2000 files. Low limits cause cache misses.',
            ],
            [
                'key'      => 'opcache.validate_timestamps',
                'label'    => 'OPcache Validate Timestamps',
                'category' => 'OPcache',
                'weight'   => 1,
                'check'    => fn($v) => in_array(strtolower($v), ['0', 'off', ''], true),
                'warn'     => null,
                'good'     => 'Off (production)',
                'note'     => 'Disable in production for best performance. Enable only in development.',
            ],
        ];
    }

    // Free-tier teaser: grade + score + counts only — no details
    public static function summary(): array {
        $score = 0;
        $total_w = 0;
        $passed_w = 0;
        $fails = 0;
        $warns = 0;
        $passes = 0;

        foreach (self::checks() as $c) {
            $raw  = ini_get($c['key']);
            $val  = $raw === false ? '' : $raw;
            $pass = $c['check']($val);
            $warn = !$pass && $c['warn'] ? $c['warn']($val) : false;
            $w    = $c['weight'];

            $total_w += $w;
            if ($pass)     { $passed_w += $w;       $passes++; }
            elseif ($warn) { $passed_w += $w * 0.5; $warns++; }
            else           { $fails++; }
        }

        $score = $total_w > 0 ? (int) round($passed_w / $total_w * 100) : 0;
        return [
            'score'  => $score,
            'grade'  => self::grade($score),
            'passes' => $passes,
            'warns'  => $warns,
            'fails'  => $fails,
            'total'  => $passes + $warns + $fails,
        ];
    }

    public static function run(): array {
        if (!self::_pro()) return ['checks' => [], 'score' => 0, 'grade' => 'F', 'categories' => []];
        $results    = [];
        $total_w    = 0;
        $passed_w   = 0;
        $categories = [];

        foreach (self::checks() as $c) {
            $raw     = ini_get($c['key']);
            $display = ($raw === false || $raw === '') ? '(not set)' : $raw;
            $pass    = $c['check']($raw === false ? '' : $raw);
            $warn    = !$pass && $c['warn'] ? $c['warn']($raw === false ? '' : $raw) : false;

            $status = $pass ? 'pass' : ($warn ? 'warn' : 'fail');
            $w      = $c['weight'];

            $total_w  += $w;
            if ($pass)        $passed_w += $w;
            elseif ($warn)    $passed_w += $w * 0.5;

            $results[] = array_merge($c, [
                'value'  => $display,
                'status' => $status,
            ]);
            $categories[$c['category']] = true;
        }

        $score = $total_w > 0 ? (int) round($passed_w / $total_w * 100) : 0;

        return [
            'checks'     => $results,
            'score'      => $score,
            'grade'      => self::grade($score),
            'categories' => array_keys($categories),
        ];
    }

    private static function grade(int $score): string {
        if ($score >= 95) return 'A+';
        if ($score >= 85) return 'A';
        if ($score >= 75) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 45) return 'D';
        return 'F';
    }

    // Convert shorthand (256M, 1G) to bytes
    private static function bytes(string $val): int {
        $val  = trim($val);
        $last = strtolower(substr($val, -1));
        $num  = (int) $val;
        switch ($last) {
            case 'g': return $num * GB_IN_BYTES;
            case 'm': return $num * MB_IN_BYTES;
            case 'k': return $num * KB_IN_BYTES;
        }
        return $num;
    }
}
