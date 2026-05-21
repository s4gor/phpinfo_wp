<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Alerts {

    const OPT = 'phpinfowp_alert_settings';

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    public static function get_settings(): array {
        return wp_parse_args(get_option(self::OPT, []), [
            'enabled'         => false,
            'emails'          => get_option('admin_email', ''),
            'eol_warning'     => true,
            'config_change'   => true,
            'opcache_low'     => false,
            'opcache_thresh'  => 80,
            'weekly_digest'   => false,
            'ssl_expiry'      => true,
            'ssl_thresh'      => 30,
            'webhook_url'     => '',
            'webhook_type'    => 'slack',
        ]);
    }

    public static function save_settings(array $s): void {
        $url  = esc_url_raw(trim($s['webhook_url'] ?? ''));
        $type = in_array($s['webhook_type'] ?? '', ['slack', 'discord', 'generic'], true) ? $s['webhook_type'] : 'slack';
        update_option(self::OPT, [
            'enabled'         => !empty($s['enabled']),
            'emails'          => sanitize_textarea_field($s['emails'] ?? ''),
            'eol_warning'     => !empty($s['eol_warning']),
            'config_change'   => !empty($s['config_change']),
            'opcache_low'     => !empty($s['opcache_low']),
            'opcache_thresh'  => max(0, min(100, (int)($s['opcache_thresh'] ?? 80))),
            'weekly_digest'   => !empty($s['weekly_digest']),
            'ssl_expiry'      => !empty($s['ssl_expiry']),
            'ssl_thresh'      => max(1, (int)($s['ssl_thresh'] ?? 30)),
            'webhook_url'     => (strpos($url, 'https://') === 0) ? $url : '',
            'webhook_type'    => $type,
        ], false);
    }

    private static function recipients(): array {
        $s    = self::get_settings();
        $raw  = $s['emails'] ?? '';
        $list = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $raw)));
        return array_values(array_filter($list, 'is_email'));
    }

    private static function send(string $subject, string $body): bool {
        $sent = false;

        $to = self::recipients();
        if ($to) {
            $site    = get_bloginfo('name') . ' (' . get_site_url() . ')';
            $full    = "Alert from phpinfo() WP Pro\nSite: {$site}\n\n{$body}\n\n---\n"
                     . "Manage alerts: " . admin_url('admin.php?page=phpinfowp-alerts');
            $headers = [
                'Content-Type: text/plain; charset=UTF-8',
                'From: phpinfo() WP <' . get_option('admin_email') . '>',
            ];
            $sent = wp_mail($to, "[phpinfo() WP] {$subject}", $full, $headers);
        }

        self::send_webhook($subject, $body);
        return $sent;
    }

    public static function send_webhook(string $subject, string $body): bool {
        $s = self::get_settings();
        $url = $s['webhook_url'] ?? '';
        if (!$url) return false;

        $site = get_bloginfo('name') . ' (' . get_site_url() . ')';
        $text = "*[phpinfo() WP] {$subject}*\nSite: {$site}\n\n{$body}";

        $payload = match ($s['webhook_type']) {
            'discord' => ['content' => substr($text, 0, 1900)],
            'slack'   => ['text'    => $text],
            default   => ['site' => $site, 'subject' => $subject, 'body' => $body, 'at' => time()],
        };

        $resp = wp_remote_post($url, [
            'timeout' => 10,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload),
        ]);
        if (is_wp_error($resp)) return false;
        $code = wp_remote_retrieve_response_code($resp);
        return $code >= 200 && $code < 300;
    }

    public static function maybe_send_eol(): void {
        if (!self::_pro()) return;
        $s = self::get_settings();
        if (!$s['enabled'] || !$s['eol_warning']) return;

        $status = Phpinfo_WP_EOL::status();
        if (!in_array($status['status'], ['warning', 'eol'], true)) return;

        // Throttle: don't send more than once per 7 days
        if (get_transient('phpinfowp_alert_eol_sent')) return;
        set_transient('phpinfowp_alert_eol_sent', 1, 7 * DAY_IN_SECONDS);

        $minor = $status['minor'];
        $eol   = $status['eol'];
        $days  = $status['days'];

        if ($status['status'] === 'eol') {
            $subject = "ACTION REQUIRED: PHP {$minor} is past end-of-life";
            $body    = "PHP {$minor} reached end-of-life on {$eol}.\n"
                     . "No security updates are being issued for this version.\n"
                     . "Upgrade to PHP 8.2 or newer immediately.";
        } else {
            $subject = "Warning: PHP {$minor} reaches end-of-life in {$days} days";
            $body    = "PHP {$minor} will reach end-of-life on {$eol} ({$days} days from now).\n"
                     . "Plan your PHP upgrade before that date to avoid running unsupported software.";
        }

        self::send($subject, $body);
    }

    public static function maybe_send_config_change(array $diff): void {
        if (!self::_pro()) return;
        $s = self::get_settings();
        if (!$s['enabled'] || !$s['config_change'] || !$diff) return;

        $lines = ["The following PHP configuration changes were detected:\n"];
        foreach ($diff as $item) {
            $key = $item['key'];
            if ($item['type'] === 'changed') {
                $lines[] = "  CHANGED  {$key}: \"{$item['old']}\" → \"{$item['new']}\"";
            } elseif ($item['type'] === 'added') {
                $lines[] = "  ADDED    {$key}: \"{$item['new']}\"";
            } else {
                $lines[] = "  REMOVED  {$key} (was \"{$item['old']}\")";
            }
        }

        $count   = count($diff);
        $subject = "{$count} PHP config change" . ($count > 1 ? 's' : '') . " detected";
        self::send($subject, implode("\n", $lines));
    }

    public static function maybe_send_opcache_low(): void {
        if (!self::_pro()) return;
        $s = self::get_settings();
        if (!$s['enabled'] || !$s['opcache_low']) return;

        $status = Phpinfo_WP_OPcache::status();
        if (!$status || $status['hit_rate'] === null) return;
        if ($status['hit_rate'] >= (float) $s['opcache_thresh']) return;

        if (get_transient('phpinfowp_alert_opcache_sent')) return;
        set_transient('phpinfowp_alert_opcache_sent', 1, DAY_IN_SECONDS);

        $rate    = $status['hit_rate'];
        $thresh  = $s['opcache_thresh'];
        $subject = "OPcache hit rate is low: {$rate}% (threshold: {$thresh}%)";
        $body    = "OPcache hit rate has dropped to {$rate}%, below your threshold of {$thresh}%.\n"
                 . "This may indicate OPcache memory is too small or is restarting frequently.\n"
                 . "Current OPcache memory used: " . Phpinfo_WP_OPcache::format_bytes($status['memory_used']) . " / "
                 . Phpinfo_WP_OPcache::format_bytes($status['memory_total']);

        self::send($subject, $body);
    }

    public static function maybe_send_ssl(): void {
        if (!self::_pro()) return;
        $s = self::get_settings();
        if (!$s['enabled'] || !$s['ssl_expiry']) return;
        if (get_transient('phpinfowp_alert_ssl_sent')) return;

        $results  = Phpinfo_WP_SSL::check_all();
        $problems = array_filter($results, function ($r) use ($s) {
            return empty($r['error'])
                && isset($r['days'])
                && $r['days'] <= (int)$s['ssl_thresh'];
        });

        if (!$problems) return;
        set_transient('phpinfowp_alert_ssl_sent', 1, DAY_IN_SECONDS);

        $lines = [];
        foreach ($problems as $cert) {
            $lines[] = $cert['days'] < 0
                ? "  EXPIRED  {$cert['host']} (expired {$cert['expiry']})"
                : "  EXPIRING {$cert['host']} — {$cert['days']} days left (expires {$cert['expiry']})";
        }

        $count   = count($problems);
        $subject = "SSL certificate" . ($count > 1 ? 's' : '') . " expiring soon";
        self::send($subject, implode("\n", $lines));
    }

    public static function send_weekly_digest(): void {
        if (!self::_pro()) return;
        $s = self::get_settings();
        if (!$s['enabled'] || !$s['weekly_digest']) return;

        $site    = get_bloginfo('name') . ' (' . get_site_url() . ')';
        $eol     = Phpinfo_WP_EOL::status();
        $grader  = Phpinfo_WP_Config_Grader::run();
        $mem_used  = size_format(memory_get_usage(true));
        $mem_limit = ini_get('memory_limit');

        $lines   = [];
        $lines[] = "Weekly server health digest for: {$site}";
        $lines[] = str_repeat('─', 60);
        $lines[] = '';

        // PHP
        $lines[] = "PHP VERSION";
        $lines[] = "  PHP " . PHP_VERSION;
        if ($eol['status'] === 'eol') {
            $lines[] = "  ⚠ End of life — upgrade immediately";
        } elseif ($eol['status'] === 'warning') {
            $lines[] = "  ⚠ EOL in {$eol['days']} days ({$eol['eol']})";
        } else {
            $lines[] = "  ✓ Supported until {$eol['eol']}";
        }

        // Memory
        $lines[] = '';
        $lines[] = "MEMORY";
        $lines[] = "  Used: {$mem_used} / Limit: {$mem_limit}";

        // Config grade
        $lines[] = '';
        $lines[] = "CONFIG GRADE";
        $lines[] = "  Score: {$grader['score']}/100 (Grade: {$grader['grade']})";
        $failing = array_filter($grader['checks'], fn($c) => $c['status'] === 'fail');
        foreach (array_slice($failing, 0, 5) as $f) {
            $lines[] = "  ✗ {$f['key']}: currently {$f['value']} (recommended: {$f['good']})";
        }

        // OPcache
        $oc = Phpinfo_WP_OPcache::status();
        if ($oc && $oc['enabled']) {
            $lines[] = '';
            $lines[] = "OPCACHE";
            $lines[] = "  Hit rate: " . ($oc['hit_rate'] ?? 'N/A') . "%";
            $lines[] = "  Memory: " . Phpinfo_WP_OPcache::format_bytes($oc['memory_used'])
                     . " / " . Phpinfo_WP_OPcache::format_bytes($oc['memory_total']);
        }

        // SSL
        $ssl_results = Phpinfo_WP_SSL::check_all();
        if ($ssl_results) {
            $lines[] = '';
            $lines[] = "SSL CERTIFICATES";
            foreach ($ssl_results as $cert) {
                if (!empty($cert['error'])) {
                    $lines[] = "  ✗ {$cert['host']}: {$cert['error']}";
                } elseif ($cert['days'] < 0) {
                    $lines[] = "  ✗ {$cert['host']}: EXPIRED {$cert['expiry']}";
                } else {
                    $icon     = $cert['days'] < 30 ? '⚠' : '✓';
                    $lines[] = "  {$icon} {$cert['host']}: {$cert['days']} days left (expires {$cert['expiry']})";
                }
            }
        }

        // Snapshots — any changes this week
        $latest = Phpinfo_WP_Snapshots::get_latest();
        $prev   = $latest ? Phpinfo_WP_Snapshots::get_previous_to($latest->id) : null;
        if ($latest && $prev) {
            $diff = Phpinfo_WP_Snapshots::diff($prev->snapshot_data, $latest->snapshot_data);
            if ($diff) {
                $lines[] = '';
                $lines[] = "CONFIG CHANGES SINCE LAST SNAPSHOT";
                foreach (array_slice($diff, 0, 10) as $item) {
                    $lines[] = "  " . strtoupper($item['type']) . " {$item['key']}: "
                             . ($item['old'] ?? '—') . " → " . ($item['new'] ?? '—');
                }
            }
        }

        $lines[] = '';
        $lines[] = str_repeat('─', 60);
        $lines[] = "View full dashboard: " . admin_url('admin.php?page=phpinfo-wp');

        self::send("Weekly health digest — " . get_bloginfo('name'), implode("\n", $lines));
    }

    // Weekly cron — runs all alert checks + auto-snapshot
    public static function cron_weekly(): void {
        if (!self::_pro()) return;
        $diff = Phpinfo_WP_Snapshots::auto_snapshot();
        self::maybe_send_config_change($diff);
        Phpinfo_WP_Snapshots::prune(30);

        self::maybe_send_eol();
        self::maybe_send_opcache_low();
        self::maybe_send_ssl();
        self::send_weekly_digest();
    }
}
