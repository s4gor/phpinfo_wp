<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_EOL {

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    // EOL dates from https://www.php.net/supported-versions.php
    private static array $eol = [
        '5.6' => '2018-12-31',
        '7.0' => '2019-12-03',
        '7.1' => '2019-12-01',
        '7.2' => '2020-11-30',
        '7.3' => '2021-12-06',
        '7.4' => '2022-11-28',
        '8.0' => '2023-11-26',
        '8.1' => '2024-12-31',
        '8.2' => '2026-12-31',
        '8.3' => '2027-12-31',
        '8.4' => '2028-12-31',
    ];

    public static function minor(): string {
        preg_match('/^(\d+\.\d+)/', PHP_VERSION, $m);
        return $m[1] ?? '';
    }

    public static function eol_date(string $minor = ''): ?string {
        $minor = $minor ?: self::minor();
        return self::$eol[$minor] ?? null;
    }

    // Returns: ['status' => ok|warning|eol|unknown, 'eol' => date|null, 'days' => int|null]
    public static function status(): array {
        $minor = self::minor();
        $eol   = self::eol_date($minor);

        if (!$eol) return ['status' => 'unknown', 'eol' => null, 'days' => null, 'minor' => $minor];

        $days = (int) round((strtotime($eol) - time()) / DAY_IN_SECONDS);

        $state = 'ok';
        if ($days < 0)  $state = 'eol';
        elseif ($days < 90) $state = 'warning';

        return ['status' => $state, 'eol' => $eol, 'days' => $days, 'minor' => $minor];
    }

    // Shown on every admin page — free feature, drives urgency
    public static function admin_notice(): void {
        $s = self::status();
        if ($s['status'] === 'ok' || $s['status'] === 'unknown') return;

        if ($s['status'] === 'eol') {
            $msg  = sprintf(
                'PHP %s reached end-of-life on %s and no longer receives security patches. Upgrade immediately.',
                esc_html($s['minor']), esc_html($s['eol'])
            );
            $type = 'error';
        } else {
            $msg  = sprintf(
                'PHP %s reaches end-of-life on %s (%d days). Plan your upgrade now.',
                esc_html($s['minor']), esc_html($s['eol']), $s['days']
            );
            $type = 'warning';
        }

        printf(
            '<div class="notice notice-%s is-dismissible"><p><strong>phpinfo() WP:</strong> %s</p></div>',
            $type, $msg
        );
    }

    // Full EOL timeline table — free feature; drives urgency for Pro upsell
    public static function timeline(): array {
        $now = time();
        $out = [];
        foreach (self::$eol as $ver => $date) {
            $ts    = strtotime($date);
            $days  = (int) round(($ts - $now) / DAY_IN_SECONDS);
            $out[] = [
                'version' => $ver,
                'eol'     => $date,
                'days'    => $days,
                'status'  => $days < 0 ? 'eol' : ($days < 90 ? 'warning' : 'ok'),
                'current' => ($ver === self::minor()),
            ];
        }
        return $out;
    }
}
