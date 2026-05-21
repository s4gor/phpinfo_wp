<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_DB_Health {

    // MySQL EOL dates — https://endoflife.date/mysql
    private static array $mysql_eol = [
        '5.6'  => '2021-02-28',
        '5.7'  => '2023-10-31',
        '8.0'  => '2026-04-30',
        '8.1'  => '2024-01-31',
        '8.2'  => '2024-04-30',
        '8.3'  => '2024-07-31',
        '8.4'  => '2032-04-30',
    ];
    // MariaDB EOL dates — https://endoflife.date/mariadb
    private static array $mariadb_eol = [
        '10.3' => '2023-05-25',
        '10.4' => '2024-06-18',
        '10.5' => '2025-06-24',
        '10.6' => '2026-07-06',
        '10.7' => '2023-02-09',
        '10.8' => '2023-05-20',
        '10.9' => '2023-08-22',
        '10.10'=> '2023-11-17',
        '10.11'=> '2028-02-16',
        '11.0' => '2024-06-06',
        '11.1' => '2024-08-21',
        '11.2' => '2024-11-21',
        '11.3' => '2025-02-11',
        '11.4' => '2029-05-29',
    ];

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    public static function server_info(): array {
        if (!self::_pro()) return [];
        global $wpdb;
        $raw = $wpdb->db_version();
        $full = $wpdb->get_var('SELECT VERSION()');
        $is_maria = $full && (stripos($full, 'mariadb') !== false);

        // Match X.Y
        preg_match('/^(\d+)\.(\d+)/', $raw, $m);
        $minor = $m ? "{$m[1]}.{$m[2]}" : $raw;

        $table = $is_maria ? self::$mariadb_eol : self::$mysql_eol;
        $eol   = $table[$minor] ?? null;
        $days  = $eol ? (int) round((strtotime($eol) - time()) / DAY_IN_SECONDS) : null;

        $status = 'unknown';
        if ($eol) {
            if ($days < 0) $status = 'eol';
            elseif ($days < 90) $status = 'warning';
            else $status = 'ok';
        }

        return [
            'engine'  => $is_maria ? 'MariaDB' : 'MySQL',
            'version' => $raw,
            'full'    => $full,
            'minor'   => $minor,
            'eol'     => $eol,
            'days'    => $days,
            'status'  => $status,
        ];
    }

    public static function autoload_size(): array {
        if (!self::_pro()) return [];
        global $wpdb;
        $bytes = (int) $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"
        );
        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload = 'yes'"
        );

        $status = 'ok';
        if ($bytes > 1048576)      $status = 'warning';
        if ($bytes > 3145728)      $status = 'fail';

        $top = $wpdb->get_results(
            "SELECT option_name, LENGTH(option_value) AS size
             FROM {$wpdb->options}
             WHERE autoload = 'yes'
             ORDER BY size DESC LIMIT 10"
        );

        return [
            'bytes'  => $bytes,
            'count'  => $count,
            'status' => $status,
            'top'    => $top,
        ];
    }

    public static function transients(): array {
        if (!self::_pro()) return [];
        global $wpdb;
        $all     = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%'"
        );
        $expired = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_timeout_%' AND option_value < %d",
            time()
        ));
        return [
            'total'   => $all,
            'expired' => $expired,
        ];
    }

    public static function purge_expired_transients(): int {
        if (!self::_pro()) return 0;
        global $wpdb;
        $now = time();
        $names = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_timeout_%' AND option_value < %d",
            $now
        ));
        $deleted = 0;
        foreach ($names as $timeout_name) {
            $key = str_replace('_transient_timeout_', '', $timeout_name);
            if (delete_option($timeout_name))           $deleted++;
            if (delete_option('_transient_' . $key))    $deleted++;
        }
        return (int) ($deleted / 2);
    }

    public static function tables(): array {
        if (!self::_pro()) return [];
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, DATA_FREE, ENGINE
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = %s
             ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC",
            DB_NAME
        ));
        $out = [];
        foreach ($rows as $r) {
            $total = (int) $r->DATA_LENGTH + (int) $r->INDEX_LENGTH;
            $out[] = [
                'name'   => $r->TABLE_NAME,
                'rows'   => (int) $r->TABLE_ROWS,
                'data'   => (int) $r->DATA_LENGTH,
                'index'  => (int) $r->INDEX_LENGTH,
                'free'   => (int) $r->DATA_FREE,
                'total'  => $total,
                'engine' => $r->ENGINE,
                'overhead_pct' => $total > 0 ? round((int)$r->DATA_FREE / $total * 100, 1) : 0,
            ];
        }
        return $out;
    }

    public static function db_size(): array {
        if (!self::_pro()) return [];
        $tables = self::tables();
        $data  = 0;
        $index = 0;
        $free  = 0;
        foreach ($tables as $t) {
            $data  += $t['data'];
            $index += $t['index'];
            $free  += $t['free'];
        }
        return [
            'total'   => $data + $index,
            'data'    => $data,
            'index'   => $index,
            'free'    => $free,
            'tables'  => count($tables),
        ];
    }
}
