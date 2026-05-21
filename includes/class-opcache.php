<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_OPcache {

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    public static function is_available(): bool {
        return function_exists('opcache_get_status') && function_exists('opcache_get_configuration');
    }

    public static function status(): ?array {
        if (!self::_pro()) return null;
        if (!self::is_available()) return null;

        $status = @opcache_get_status(false);
        $config = @opcache_get_configuration();

        if (!$status || !$config) return null;

        $mem      = $status['memory_usage'] ?? [];
        $used     = (int) ($mem['used_memory'] ?? 0);
        $free     = (int) ($mem['free_memory'] ?? 0);
        $wasted   = (int) ($mem['wasted_memory'] ?? 0);
        $total    = $used + $free + $wasted;
        $hit_rate = null;

        $stats = $status['opcache_statistics'] ?? [];
        if (isset($stats['hits'], $stats['misses']) && ($stats['hits'] + $stats['misses']) > 0) {
            $hit_rate = round($stats['hits'] / ($stats['hits'] + $stats['misses']) * 100, 2);
        }

        return [
            'enabled'          => (bool) ($status['opcache_enabled'] ?? false),
            'full'             => (bool) ($status['cache_full'] ?? false),
            'hit_rate'         => $hit_rate,
            'hits'             => (int) ($stats['hits'] ?? 0),
            'misses'           => (int) ($stats['misses'] ?? 0),
            'cached_scripts'   => (int) ($stats['num_cached_scripts'] ?? 0),
            'max_scripts'      => (int) ($config['directives']['opcache.max_accelerated_files'] ?? 0),
            'memory_used'      => $used,
            'memory_free'      => $free,
            'memory_wasted'    => $wasted,
            'memory_total'     => $total,
            'memory_pct'       => $total > 0 ? round($used / $total * 100, 1) : 0,
            'wasted_pct'       => (float) ($mem['current_wasted_percentage'] ?? 0),
            'start_time'       => (int) ($stats['start_time'] ?? 0),
            'last_restart'     => (int) ($stats['last_restart_time'] ?? 0),
            'directives'       => $config['directives'] ?? [],
        ];
    }

    public static function reset(): bool {
        if (!self::_pro()) return false;
        if (!function_exists('opcache_reset')) return false;
        return @opcache_reset();
    }

    public static function format_bytes(int $bytes): string {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    public static function hit_rate_class(float $rate): string {
        if ($rate >= 90) return 'grade-a';
        if ($rate >= 70) return 'grade-b';
        if ($rate >= 50) return 'grade-c';
        return 'grade-f';
    }
}
