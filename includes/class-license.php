<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_License {

    const OPT_KEY    = 'phpinfowp_license_key';
    const OPT_CACHE  = 'phpinfowp_lic_cache';
    const OPT_FAILS  = 'phpinfowp_lic_fails';
    const OPT_LOCKED = 'phpinfowp_lic_locked';
    const MAX_FAILS  = 2;
    const PING_URL   = 'https://exeebit.com/api/license/validate';

    // Secret assembled from fragments — harder to spot and patch as a unit
    private const _F1 = "\x50\x49\x57\x50"; // PIWP
    private const _F2 = "\x5f\x70\x72\x6f"; // _pro
    private const _F3 = "\x5f\x73\x65\x63"; // _sec

    private static function _hmac_secret(): string {
        static $s;
        if ($s !== null) return $s;
        $raw = self::_F1 . self::_F2 . self::_F3 . "\x72\x65\x74\x5f\x76\x31"; // ret_v1
        $s   = hash('sha256', $raw, true);
        return $s;
    }

    // Cache secret uses site's AUTH_KEY — unique per WP install.
    // Injecting a fake "valid" row into wp_options fails because the MAC won't verify.
    private static function _cache_secret(): string {
        static $cs;
        if ($cs !== null) return $cs;
        $salt = defined('AUTH_KEY') ? AUTH_KEY : (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'phpinfowp_fallback_salt');
        $cs   = hash('sha256', $salt . 'phpinfowp_cache_v1', true);
        return $cs;
    }

    private static function _cache_write(bool $valid): void {
        $data = json_encode(['v' => (int) $valid, 't' => time()]);
        $mac  = hash_hmac('sha256', $data, self::_cache_secret());
        update_option(self::OPT_CACHE, $mac . '|' . base64_encode($data), false);
    }

    private static function _cache_read(): ?bool {
        $stored = get_option(self::OPT_CACHE, '');
        if (!$stored || strpos($stored, '|') === false) return null;

        [$mac, $data_b64] = explode('|', $stored, 2);
        $data = base64_decode($data_b64);
        if (!$data) return null;

        $expected = hash_hmac('sha256', $data, self::_cache_secret());
        if (!hash_equals($expected, $mac)) return null; // Tampered

        $parsed = json_decode($data, true);
        if (!isset($parsed['v'], $parsed['t'])) return null;
        if (time() - (int) $parsed['t'] > 6 * HOUR_IN_SECONDS) return null; // Stale

        return (bool) $parsed['v'];
    }

    private static function _cache_clear(): void {
        delete_option(self::OPT_CACHE);
    }

    // --- Public API ---

    public static function get_key(): string {
        return (string) get_option(self::OPT_KEY, '');
    }

    // Parse the active key's payload for UI display. Returns null when the
    // key is missing or malformed. Does NOT re-verify the HMAC — call
    // is_valid() for that. Returned keys: email, url, exp, iat (all from
    // the payload), plus derived: days_left, expiry_human, is_lifetime.
    public static function payload(): ?array {
        $key = self::get_key();
        if (!$key) return null;

        $parts = explode('-', $key, 3);
        if (count($parts) !== 3 || $parts[0] !== 'PIWP') return null;

        $raw = base64_decode(strtr($parts[1], '-_', '+/'));
        if (!$raw) return null;
        $p = json_decode($raw, true);
        if (!isset($p['exp'], $p['email'])) return null;

        $exp       = (int) $p['exp'];
        $days_left = (int) floor(($exp - time()) / DAY_IN_SECONDS);
        // Lifetime keys use a sentinel year-2099 timestamp — 50+ years out
        // means we treat it as lifetime rather than print "26,000 days left".
        $is_life   = $days_left > (50 * 365);

        return [
            'email'        => (string) $p['email'],
            'url'          => (string) ($p['url'] ?? ''),
            'exp'          => $exp,
            'iat'          => (int) ($p['iat'] ?? 0),
            'days_left'    => $days_left,
            'expiry_human' => $is_life ? 'Lifetime' : date_i18n(get_option('date_format'), $exp),
            'is_lifetime'  => $is_life,
        ];
    }

    public static function is_valid(): bool {
        if (self::is_locked()) return false;

        $cached = self::_cache_read();
        if ($cached !== null) return $cached;

        $valid = self::_validate_local(self::get_key());
        self::_cache_write($valid);
        return $valid;
    }

    public static function is_locked(): bool {
        return (bool) get_option(self::OPT_LOCKED, false);
    }

    public static function activate(string $key): bool {
        $key = sanitize_text_field(trim($key));
        update_option(self::OPT_KEY, $key, false);
        self::_cache_clear();
        delete_option(self::OPT_FAILS);
        delete_option(self::OPT_LOCKED);

        $valid = self::_validate_local($key);
        if (!$valid) {
            // Attempt remote as fallback (e.g. clock skew on expiry edge)
            $valid = self::_ping_remote($key);
        }
        self::_cache_write($valid);
        return $valid;
    }

    public static function deactivate(): void {
        delete_option(self::OPT_KEY);
        self::_cache_clear();
        delete_option(self::OPT_FAILS);
        delete_option(self::OPT_LOCKED);
    }

    // Called by weekly wp_cron. After MAX_FAILS consecutive failures, locks Pro.
    public static function cron_ping(): void {
        $key = self::get_key();
        if (!$key) return;

        $ok = self::_ping_remote($key);
        if ($ok) {
            update_option(self::OPT_FAILS, 0, false);
            delete_option(self::OPT_LOCKED);
            self::_cache_clear();
            return;
        }

        $fails = (int) get_option(self::OPT_FAILS, 0) + 1;
        update_option(self::OPT_FAILS, $fails, false);
        if ($fails >= self::MAX_FAILS) {
            update_option(self::OPT_LOCKED, 1, false);
            self::_cache_clear();
        }
    }

    // --- Key validation ---

    public static function _validate_local(string $key): bool {
        if (empty($key)) return false;

        // Format: PIWP-{base64url_payload}-{32_char_hmac}
        $parts = explode('-', $key, 3);
        if (count($parts) !== 3 || $parts[0] !== 'PIWP') return false;

        $payload_b64 = $parts[1];
        $sig         = $parts[2];

        $expected = substr(hash_hmac('sha256', $payload_b64, self::_hmac_secret()), 0, 32);
        if (!hash_equals($expected, strtolower($sig))) return false;

        $payload_raw = base64_decode(strtr($payload_b64, '-_', '+/'));
        if (!$payload_raw) return false;

        $p = json_decode($payload_raw, true);
        if (!isset($p['url'], $p['exp'], $p['email'])) return false;

        if ((int) $p['exp'] < time()) return false;

        // Wildcard "*" = unlimited/lifetime tiers; server enforces site limits
        // via activation tracking. Otherwise require exact site URL match.
        $key_url = trim((string) $p['url']);
        if ($key_url !== '*') {
            $site = rtrim(strtolower(get_site_url()), '/');
            if ($site !== rtrim(strtolower($key_url), '/')) return false;
        }

        return true;
    }

    private static function _ping_remote(string $key): bool {
        $resp = wp_remote_post(self::PING_URL, [
            'timeout' => 12,
            'body'    => [
                'license_key' => $key,
                'site_url'    => get_site_url(),
                'plugin_v'    => PHPINFOWP_VERSION,
            ],
        ]);

        if (is_wp_error($resp)) return false;
        if (wp_remote_retrieve_response_code($resp) !== 200) return false;

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return !empty($body['valid']);
    }

    public static function schedule_remote_check_event(): void {
        if (!wp_next_scheduled('phpinfowp_license_ping')) {
            wp_schedule_event(time() + WEEK_IN_SECONDS, 'weekly', 'phpinfowp_license_ping');
        }
    }

    // --- Key generator (server-side helper, called from licensing plugin) ---

    public static function generate_key(string $email, string $site_url, int $expiry_ts): string {
        $payload = json_encode([
            'email' => $email,
            'url'   => rtrim(strtolower($site_url), '/'),
            'exp'   => $expiry_ts,
            'iat'   => time(),
        ]);
        $b64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $sig = substr(hash_hmac('sha256', $b64, self::_hmac_secret()), 0, 32);
        return "PIWP-{$b64}-{$sig}";
    }
}
