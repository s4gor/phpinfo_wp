<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Compat {

    const OPT_RESULT = 'phpinfowp_compat_result';
    const TARGETS    = ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'];
    const MAX_FILE_SIZE = 1048576;
    const MAX_FILES_PER_RUN = 5000;

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    // Free vs Pro split: scan engine is FREE (capture market). Pro gates the
    // pre-update interception, scheduled scans, and email alerts on new issues.
    const FREE_MAX_FILES = 1500;

    private static function rules(): array {
        return [
            // ---- Removed in PHP 7.0 ----
            ['pattern' => '/\bmysql_(connect|query|fetch_array|fetch_assoc|fetch_row|num_rows|real_escape_string|close|select_db|pconnect|free_result|insert_id|error|errno|escape_string|result|fetch_object|num_fields|set_charset|unbuffered_query)\s*\(/i',
             'name' => 'mysql_*()', 'in' => '5.5', 'out' => '7.0', 'severity' => 'removed',
             'fix' => 'Use mysqli_* or PDO'],
            ['pattern' => '/\b(ereg|eregi|ereg_replace|eregi_replace)\s*\(/',
             'name' => 'ereg_*()', 'in' => '5.3', 'out' => '7.0', 'severity' => 'removed',
             'fix' => 'Use preg_* equivalents'],
            ['pattern' => '/\bsplit\s*\(/',
             'name' => 'split()', 'in' => '5.3', 'out' => '7.0', 'severity' => 'removed',
             'fix' => 'Use preg_split() or explode()'],
            ['pattern' => '/\bsql_regcase\s*\(/',
             'name' => 'sql_regcase()', 'in' => '5.3', 'out' => '7.0', 'severity' => 'removed',
             'fix' => 'No replacement — build the pattern manually'],

            // ---- Removed in PHP 7.2 ----
            ['pattern' => '/\bmcrypt_(encrypt|decrypt|create_iv|get_iv_size|get_block_size|get_key_size|module_open|module_close|generic|generic_init|generic_deinit|list_algorithms|list_modes)\s*\(/',
             'name' => 'mcrypt_*()', 'in' => '7.1', 'out' => '7.2', 'severity' => 'removed',
             'fix' => 'Use openssl_encrypt/decrypt or sodium_crypto_*'],

            // ---- Removed in PHP 8.0 ----
            ['pattern' => '/\bcreate_function\s*\(/',
             'name' => 'create_function()', 'in' => '7.2', 'out' => '8.0', 'severity' => 'removed',
             'fix' => 'Use anonymous functions (closures)'],
            ['pattern' => '/\beach\s*\(\s*\$/',
             'name' => 'each()', 'in' => '7.2', 'out' => '8.0', 'severity' => 'removed',
             'fix' => 'Use foreach()'],
            ['pattern' => '/\bget_magic_quotes_(gpc|runtime)\s*\(/',
             'name' => 'get_magic_quotes_*()', 'in' => '7.4', 'out' => '8.0', 'severity' => 'removed',
             'fix' => 'Always returns false in 7.4+; remove the call'],
            ['pattern' => '/\bmoney_format\s*\(/',
             'name' => 'money_format()', 'in' => '7.4', 'out' => '8.0', 'severity' => 'removed',
             'fix' => 'Use NumberFormatter from the intl extension'],
            ['pattern' => '/\bimage2wbmp\s*\(/',
             'name' => 'image2wbmp()', 'in' => '7.3', 'out' => '8.0', 'severity' => 'removed',
             'fix' => 'Use imagewbmp()'],
            ['pattern' => '/\bconvert_cyr_string\s*\(/',
             'name' => 'convert_cyr_string()', 'in' => '7.4', 'out' => '8.0', 'severity' => 'removed',
             'fix' => 'Use iconv() or mb_convert_encoding()'],
            ['pattern' => '/\bhebrevc\s*\(/',
             'name' => 'hebrevc()', 'in' => '7.4', 'out' => '8.0', 'severity' => 'removed',
             'fix' => 'Use hebrev() + nl2br()'],
            ['pattern' => '/\(\s*real\s*\)\s*\$/',
             'name' => '(real) cast', 'in' => '7.4', 'out' => '8.0', 'severity' => 'removed',
             'fix' => 'Use (float) instead'],
            ['pattern' => '/\bis_real\s*\(/',
             'name' => 'is_real()', 'in' => '7.4', 'out' => '8.0', 'severity' => 'removed',
             'fix' => 'Use is_float()'],
            ['pattern' => '/\brestore_include_path\s*\(\s*\$/',
             'name' => 'restore_include_path() with args', 'in' => '7.4', 'out' => '8.0', 'severity' => 'removed',
             'fix' => 'Call with no arguments'],

            // ---- Deprecated in PHP 8.1 ----
            ['pattern' => '/\bstrftime\s*\(/',
             'name' => 'strftime()', 'in' => '8.1', 'out' => null, 'severity' => 'deprecated',
             'fix' => 'Use IntlDateFormatter::format() or date_format()'],
            ['pattern' => '/\bgmstrftime\s*\(/',
             'name' => 'gmstrftime()', 'in' => '8.1', 'out' => null, 'severity' => 'deprecated',
             'fix' => 'Use IntlDateFormatter with UTC timezone'],
            ['pattern' => '/\bdate_sun(rise|set)\s*\(/',
             'name' => 'date_sunrise()/date_sunset()', 'in' => '8.1', 'out' => null, 'severity' => 'deprecated',
             'fix' => 'Use date_sun_info()'],
            ['pattern' => '/\bmhash(_(keygen_s2k|count|get_block_size|get_hash_name))?\s*\(/',
             'name' => 'mhash_*()', 'in' => '8.1', 'out' => null, 'severity' => 'deprecated',
             'fix' => 'Use hash_* functions'],

            // ---- Deprecated in PHP 8.2 ----
            ['pattern' => '/\butf8_encode\s*\(/',
             'name' => 'utf8_encode()', 'in' => '8.2', 'out' => null, 'severity' => 'deprecated',
             'fix' => 'Use mb_convert_encoding($s, "UTF-8", "ISO-8859-1")'],
            ['pattern' => '/\butf8_decode\s*\(/',
             'name' => 'utf8_decode()', 'in' => '8.2', 'out' => null, 'severity' => 'deprecated',
             'fix' => 'Use mb_convert_encoding($s, "ISO-8859-1", "UTF-8")'],
            ['pattern' => '/"[^"]*\$\{[a-zA-Z_]/',
             'name' => '${var} string interpolation', 'in' => '8.2', 'out' => null, 'severity' => 'deprecated',
             'fix' => 'Use {$var} syntax'],

            // ---- Deprecated in PHP 8.3 ----
            ['pattern' => '/\bget_class\s*\(\s*\)/',
             'name' => 'get_class() with no args', 'in' => '8.3', 'out' => null, 'severity' => 'deprecated',
             'fix' => 'Use get_class($this) or self::class'],
            ['pattern' => '/\bget_parent_class\s*\(\s*\)/',
             'name' => 'get_parent_class() with no args', 'in' => '8.3', 'out' => null, 'severity' => 'deprecated',
             'fix' => 'Use get_parent_class($this) or parent::class'],

            // ---- Always-flag risks ----
            ['pattern' => '/<\?(?!php|=|xml)/',
             'name' => 'Short open tag <?', 'in' => '7.4', 'out' => null, 'severity' => 'deprecated',
             'fix' => 'Use <?php — short_open_tag may be off'],
        ];
    }

    public static function targets(): array { return self::TARGETS; }

    public static function get_result(): ?array {
        $r = get_option(self::OPT_RESULT, null);
        return is_array($r) ? $r : null;
    }

    public static function clear(): void {
        delete_option(self::OPT_RESULT);
    }

    public static function scan(string $target = '8.2'): array {
        @set_time_limit(120);
        $started = microtime(true);

        $rules = self::filter_rules($target);
        if (!$rules) return ['error' => 'Target version invalid.'];

        // Free tier scans up to FREE_MAX_FILES; Pro raises the cap to MAX_FILES_PER_RUN.
        $max_files = self::_pro() ? self::MAX_FILES_PER_RUN : self::FREE_MAX_FILES;

        $issues_by_owner = [];
        $files_scanned   = 0;
        $files_skipped   = 0;
        $owners_seen     = [];

        $roots = [
            'plugins'    => WP_PLUGIN_DIR,
            'themes'     => get_theme_root(),
            'mu-plugins' => defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins',
        ];

        foreach ($roots as $type => $root) {
            if (!is_dir($root)) continue;
            try {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
            } catch (Throwable $e) { continue; }

            foreach ($it as $file) {
                if ($files_scanned + $files_skipped >= $max_files) break 2;
                if (!$file->isFile()) continue;
                if (strtolower($file->getExtension()) !== 'php') continue;
                if ($file->getSize() > self::MAX_FILE_SIZE) { $files_skipped++; continue; }

                $abs = $file->getPathname();
                $rel = ltrim(str_replace($root, '', $abs), '/\\');
                $owner = self::owner_of($type, $rel);
                if ($owner === null) continue;

                $content = @file_get_contents($abs);
                if ($content === false) { $files_skipped++; continue; }
                if (strpos($content, '<?') === false) { $files_scanned++; continue; }

                $files_scanned++;
                $owners_seen[$owner] = true;

                foreach ($rules as $rule) {
                    if (preg_match_all($rule['pattern'], $content, $m, PREG_OFFSET_CAPTURE)) {
                        foreach ($m[0] as $hit) {
                            $line = substr_count(substr($content, 0, $hit[1]), "\n") + 1;
                            $issues_by_owner[$owner][] = [
                                'file'     => $rel,
                                'line'     => $line,
                                'match'    => trim(self::_snippet($content, $hit[1])),
                                'name'     => $rule['name'],
                                'fix'      => $rule['fix'],
                                'severity' => $rule['severity'],
                                'in'       => $rule['in'],
                                'out'      => $rule['out'],
                            ];
                        }
                    }
                }
            }
        }

        $total = 0;
        foreach ($issues_by_owner as $list) $total += count($list);

        $result = [
            'target'        => $target,
            'total'         => $total,
            'owners'        => count($owners_seen),
            'with_issues'   => count($issues_by_owner),
            'files'         => $files_scanned,
            'skipped'       => $files_skipped,
            'max_files'     => $max_files,
            'truncated'     => ($files_scanned + $files_skipped) >= $max_files,
            'duration'      => round(microtime(true) - $started, 2),
            'scanned_at'    => time(),
            'issues'        => $issues_by_owner,
            'is_pro_result' => self::_pro(),
        ];

        update_option(self::OPT_RESULT, $result, false);
        return $result;
    }

    private static function filter_rules(string $target): array {
        $t = (float) $target;
        if ($t < 7.0 || $t > 8.4) return [];

        $rules = [];
        foreach (self::rules() as $r) {
            $in  = (float) $r['in'];
            $out = $r['out'] ? (float) $r['out'] : null;
            // If removed and target reaches the removal version, include
            if ($out !== null && $t >= $out) { $rules[] = $r; continue; }
            // If deprecated and target reaches the deprecation, include
            if ($r['severity'] === 'deprecated' && $t >= $in) { $rules[] = $r; continue; }
        }
        return $rules;
    }

    private static function owner_of(string $type, string $rel): ?string {
        $parts = preg_split('#[\\\\/]+#', $rel);
        if (!$parts) return null;
        $first = $parts[0];
        if ($first === '' || str_starts_with($first, '.')) return null;
        // Single-file plugins/mu-plugins
        if ($type !== 'themes' && count($parts) === 1) {
            return $type . '/' . pathinfo($first, PATHINFO_FILENAME);
        }
        return $type . '/' . $first;
    }

    private static function _snippet(string $content, int $offset, int $len = 60): string {
        $start = max(0, $offset - 10);
        return substr($content, $start, $len);
    }

    /**
     * Pre-update warning — hooks into the WP plugin update screen so users
     * see a clear "would break your site" warning before clicking Update.
     * The information itself is public (WP.org plugin metadata), so we keep
     * this free. Pro gates the automatic scheduled scanning + email alerts.
     */
    public static function register_update_warnings(): void {
        add_action('admin_init', function() {
            $updates = get_site_transient('update_plugins');
            if (!$updates || empty($updates->response)) return;
            foreach ($updates->response as $file => $info) {
                add_action('in_plugin_update_message-' . $file, [self::class, 'render_update_warning'], 10, 2);
            }
        });
    }

    public static function render_update_warning($plugin_data, $response): void {
        $req_php = '';
        if (is_object($response) && !empty($response->requires_php)) {
            $req_php = (string) $response->requires_php;
        } elseif (is_array($response) && !empty($response['requires_php'])) {
            $req_php = (string) $response['requires_php'];
        }

        if (!$req_php) return;
        if (version_compare(PHP_VERSION, $req_php, '>=')) return;

        $is_pro = self::_pro();
        $upgrade_url = $is_pro
            ? admin_url('admin.php?page=phpinfowp-compat')
            : 'https://exeebit.com/phpinfo-wp#pricing';
        $cta = $is_pro ? 'Run full compatibility scan →' : 'Get pre-upgrade auto-scan with Pro →';

        printf(
            '<br><span style="display:inline-block;margin-top:8px;padding:6px 10px;background:#fcf0f1;border-left:3px solid #d63638;color:#7a1a1a;font-weight:600">' .
            '⚠ This update requires PHP %s but your site runs PHP %s — installing it will likely break the plugin. ' .
            '<a href="%s" style="color:#7a1a1a;text-decoration:underline" target="_blank">%s</a>' .
            '</span>',
            esc_html($req_php),
            esc_html(PHP_VERSION),
            esc_url($upgrade_url),
            esc_html($cta)
        );
    }
}
