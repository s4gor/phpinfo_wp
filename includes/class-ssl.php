<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_SSL {

    const OPT_DOMAINS = 'phpinfowp_ssl_domains';

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    // Returns cert info array or ['error' => '...']
    public static function check(string $host, int $port = 443): array {
        if (!self::_pro()) return ['error' => 'Pro license required.'];
        $host = strtolower(trim($host));
        if (!$host) return ['error' => 'No host provided.'];

        $result = self::_check_via_stream($host, $port);
        if (isset($result['error']) && function_exists('curl_init')) {
            $result = self::_check_via_curl($host, $port);
        }
        return $result;
    }

    private static function _check_via_stream(string $host, int $port): array {
        if (!function_exists('stream_socket_client')) {
            return ['error' => 'stream_socket_client not available.'];
        }

        $ctx = stream_context_create([
            'ssl' => [
                'capture_peer_cert'  => true,
                'verify_peer'        => false,
                'verify_peer_name'   => false,
                'SNI_enabled'        => true,
                'peer_name'          => $host,
            ],
        ]);

        $socket = @stream_socket_client(
            "ssl://{$host}:{$port}", $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT, $ctx
        );

        if (!$socket) {
            return ['error' => $errstr ?: "Could not connect to {$host}:{$port}"];
        }

        $params = stream_context_get_params($socket);
        $cert   = $params['options']['ssl']['peer_certificate'] ?? null;
        fclose($socket);

        if (!$cert) return ['error' => 'Connected but no certificate returned.'];
        return self::_parse_cert($cert, $host);
    }

    private static function _check_via_curl(string $host, int $port): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => "https://{$host}:{$port}/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_CERTINFO       => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 15,
        ]);
        curl_exec($ch);

        $info    = curl_getinfo($ch);
        $certinfo = $info['certinfo'] ?? [];
        curl_close($ch);

        if (empty($certinfo[0])) {
            return ['error' => 'Could not retrieve certificate via cURL.'];
        }

        $c      = $certinfo[0];
        $expiry = isset($c['Expire date']) ? strtotime($c['Expire date']) : 0;
        $issued = isset($c['Start date'])  ? strtotime($c['Start date'])  : 0;
        $days   = $expiry ? (int) round(($expiry - time()) / DAY_IN_SECONDS) : 0;

        return [
            'host'      => $host,
            'cn'        => $c['Subject'] ?? $host,
            'issuer'    => $c['Issuer'] ?? '—',
            'issued'    => $issued  ? gmdate('Y-m-d', $issued)  : '—',
            'expiry'    => $expiry  ? gmdate('Y-m-d', $expiry)  : '—',
            'expiry_ts' => $expiry,
            'days'      => $days,
            'status'    => self::_status($days),
            'sans'      => [],
            'error'     => null,
        ];
    }

    private static function _parse_cert($cert, string $host): array {
        $info = openssl_x509_parse($cert);
        if (!$info) return ['error' => 'Could not parse certificate data.'];
        $expiry = (int) ($info['validTo_time_t']   ?? 0);
        $issued = (int) ($info['validFrom_time_t']  ?? 0);
        $days   = $expiry ? (int) round(($expiry - time()) / DAY_IN_SECONDS) : 0;

        $sans = [];
        if (!empty($info['extensions']['subjectAltName'])) {
            preg_match_all('/DNS:([^,\s]+)/', $info['extensions']['subjectAltName'], $m);
            $sans = $m[1] ?? [];
        }

        $cn     = $info['subject']['CN']  ?? $host;
        $issuer = $info['issuer']['O']    ?? ($info['issuer']['CN'] ?? '—');

        return [
            'host'      => $host,
            'cn'        => $cn,
            'issuer'    => $issuer,
            'issued'    => $issued ? gmdate('Y-m-d', $issued) : '—',
            'expiry'    => $expiry ? gmdate('Y-m-d', $expiry) : '—',
            'expiry_ts' => $expiry,
            'days'      => $days,
            'status'    => self::_status($days),
            'sans'      => $sans,
            'error'     => null,
        ];
    }

    private static function _status(int $days): string {
        if ($days < 0)  return 'expired';
        if ($days < 7)  return 'critical';
        if ($days < 30) return 'warning';
        return 'ok';
    }

    // Checks the site's own cert + any stored extra domains
    public static function check_all(): array {
        if (!self::_pro()) return [];
        $site_host = parse_url(get_site_url(), PHP_URL_HOST) ?: '';
        $hosts     = [$site_host];

        $extra = self::get_extra_domains();
        foreach ($extra as $h) {
            if ($h && $h !== $site_host) $hosts[] = $h;
        }

        $results = [];
        foreach (array_unique($hosts) as $host) {
            $cache_key = 'phpinfowp_ssl_' . md5($host);
            $cached    = get_transient($cache_key);
            if ($cached !== false) {
                $cached['cached'] = true;
                $results[] = $cached;
            } else {
                $r = self::check($host);
                set_transient($cache_key, $r, 6 * HOUR_IN_SECONDS);
                $results[] = $r;
            }
        }
        return $results;
    }

    public static function bust_cache(): void {
        $hosts = [parse_url(get_site_url(), PHP_URL_HOST) ?: ''];
        foreach (self::get_extra_domains() as $h) $hosts[] = $h;
        foreach ($hosts as $h) delete_transient('phpinfowp_ssl_' . md5($h));
    }

    public static function get_extra_domains(): array {
        if (!self::_pro()) return [];
        $raw = get_option(self::OPT_DOMAINS, '');
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', $raw))));
    }

    public static function save_extra_domains(string $raw): void {
        if (!self::_pro()) return;
        $domains = array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', $raw))));
        // Sanitize each as a hostname
        $clean = array_filter($domains, fn($d) => preg_match('/^[a-z0-9._-]+$/i', $d));
        update_option(self::OPT_DOMAINS, implode("\n", $clean), false);
        self::bust_cache();
    }

    public static function status_color(string $status): string {
        return match ($status) {
            'expired'  => '#d63638',
            'critical' => '#d63638',
            'warning'  => '#dba617',
            'ok'       => '#00a32a',
            default    => '#666',
        };
    }

    public static function status_label(string $status): string {
        return match ($status) {
            'expired'  => 'EXPIRED',
            'critical' => 'CRITICAL',
            'warning'  => 'EXPIRING SOON',
            'ok'       => 'VALID',
            default    => 'UNKNOWN',
        };
    }
}
