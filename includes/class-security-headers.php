<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Security_Headers {

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    // Points per header — total 100
    private static array $headers = [
        'content-security-policy'   => ['label' => 'Content-Security-Policy',   'points' => 30, 'desc' => 'Prevents XSS and data injection attacks by declaring approved content sources.'],
        'strict-transport-security' => ['label' => 'Strict-Transport-Security', 'points' => 25, 'desc' => 'Forces HTTPS connections, preventing protocol downgrade attacks.'],
        'x-frame-options'           => ['label' => 'X-Frame-Options',           'points' => 15, 'desc' => 'Prevents clickjacking by controlling whether the page can be framed.'],
        'x-content-type-options'    => ['label' => 'X-Content-Type-Options',    'points' => 15, 'desc' => 'Stops MIME-type sniffing, forcing the declared Content-Type.'],
        'referrer-policy'           => ['label' => 'Referrer-Policy',           'points' => 10, 'desc' => 'Controls how much referrer information is sent with requests.'],
        'permissions-policy'        => ['label' => 'Permissions-Policy',        'points' =>  5, 'desc' => 'Restricts which browser features the page can use (camera, mic, etc.).'],
    ];

    public static function audit(string $url = ''): array {
        if (!self::_pro()) return ['error' => 'Pro license required.'];
        if (!$url) $url = get_site_url();

        $response = wp_remote_head($url, [
            'timeout'    => 15,
            'redirection'=> 5,
            'sslverify'  => false,
            'user-agent' => 'phpinfo-WP-auditor/' . PHPINFOWP_VERSION,
        ]);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $raw     = wp_remote_retrieve_headers($response);
        $status  = wp_remote_retrieve_response_code($response);
        $results = [];
        $score   = 0;

        foreach (self::$headers as $key => $meta) {
            $value   = $raw[$key] ?? null;
            $present = $value !== null;
            if ($present) $score += $meta['points'];

            $results[] = [
                'key'     => $key,
                'label'   => $meta['label'],
                'points'  => $meta['points'],
                'desc'    => $meta['desc'],
                'present' => $present,
                'value'   => $present ? (string) $value : null,
                'warning' => self::_warn($key, $present ? (string) $value : null),
            ];
        }

        return [
            'url'     => $url,
            'status'  => $status,
            'results' => $results,
            'score'   => $score,
            'grade'   => self::_grade($score),
            'cached'  => false,
        ];
    }

    // Results are expensive (HTTP call) — cache per site for 1 hour
    public static function get_cached(): array {
        if (!self::_pro()) return ['error' => 'Pro license required.'];
        $cached = get_transient('phpinfowp_sec_headers');
        if ($cached !== false) {
            $cached['cached'] = true;
            return $cached;
        }
        $result = self::audit();
        if (!isset($result['error'])) {
            set_transient('phpinfowp_sec_headers', $result, HOUR_IN_SECONDS);
        }
        return $result;
    }

    public static function bust_cache(): void {
        delete_transient('phpinfowp_sec_headers');
    }

    private static function _grade(int $score): string {
        if ($score >= 95) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 65) return 'B';
        if ($score >= 50) return 'C';
        if ($score >= 35) return 'D';
        return 'F';
    }

    // Returns a short actionable warning if header is missing or misconfigured
    private static function _warn(string $key, ?string $value): ?string {
        if ($value === null) return 'Missing — add this header in your web server config or .htaccess.';

        switch ($key) {
            case 'strict-transport-security':
                if (strpos($value, 'max-age') === false) return 'max-age directive is missing.';
                preg_match('/max-age=(\d+)/', $value, $m);
                if (isset($m[1]) && (int)$m[1] < 31536000) return 'max-age is below recommended 31536000 (1 year).';
                break;
            case 'x-frame-options':
                $v = strtoupper($value);
                if (!in_array($v, ['DENY', 'SAMEORIGIN'], true)) return 'Value should be DENY or SAMEORIGIN.';
                break;
            case 'x-content-type-options':
                if (strtolower(trim($value)) !== 'nosniff') return 'Value must be exactly "nosniff".';
                break;
        }
        return null;
    }
}
