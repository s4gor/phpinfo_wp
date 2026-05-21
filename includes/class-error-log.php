<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Error_Log {

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    public static function find_path(): ?string {
        // Path discovery is free-safe; reading log contents (tail/clear) stays Pro.
        // 1. WP_DEBUG_LOG — can be true (uses default) or an explicit path
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG !== false) {
            if (is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== '1' && WP_DEBUG_LOG !== 'true') {
                $p = WP_DEBUG_LOG;
            } else {
                $p = WP_CONTENT_DIR . '/debug.log';
            }
            if (file_exists($p)) return $p;
        }

        // 2. php.ini error_log directive
        $ini = ini_get('error_log');
        if ($ini && file_exists($ini)) return $ini;

        // 3. Common fallbacks
        $candidates = [
            WP_CONTENT_DIR . '/debug.log',
            ABSPATH . 'error_log',
            ABSPATH . 'php_errors.log',
            dirname(ABSPATH) . '/error_log',
        ];
        foreach ($candidates as $c) {
            if (file_exists($c)) return $c;
        }

        return null;
    }

    // Returns last $lines lines, newest first
    public static function tail(string $path, int $lines = 150): array {
        if (!self::_pro()) return [];
        if (!file_exists($path) || !is_readable($path)) return [];

        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $total = $file->key();

        $start  = max(0, $total - $lines);
        $result = [];

        $file->seek($start);
        while (!$file->eof()) {
            $line = rtrim((string) $file->current(), "\r\n");
            if ($line !== '') $result[] = $line;
            $file->next();
        }

        return array_reverse($result);
    }

    public static function size(string $path): int {
        return file_exists($path) ? (int) filesize($path) : 0;
    }

    public static function clear(string $path): bool {
        if (!self::_pro()) return false;
        if (!file_exists($path) || !is_writable($path)) return false;
        return (bool) file_put_contents($path, '');
    }

    public static function format_bytes(int $bytes): string {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    // Counts PHP error log lines stamped with today's date.
    // Free-safe: returns 0 if no log discovered. Reads only the tail (~512 KB) to stay cheap on huge logs.
    public static function today_count(?string $path = null): int {
        $path = $path ?? self::find_path();
        if (!$path || !is_readable($path)) return 0;

        $size = (int) @filesize($path);
        if ($size <= 0) return 0;

        $chunk  = 512 * 1024;
        $offset = max(0, $size - $chunk);

        $fh = @fopen($path, 'rb');
        if (!$fh) return 0;
        @fseek($fh, $offset);
        $data = @fread($fh, $chunk);
        @fclose($fh);
        if ($data === false || $data === '') return 0;

        // PHP error log lines start with: "[DD-Mon-YYYY HH:MM:SS TZ] ..."
        // Use local date — error_log writes timestamps in PHP's configured timezone.
        $today = date('d-M-Y');
        $count = preg_match_all('/^\[' . preg_quote($today, '/') . ' /m', $data);
        return (int) $count;
    }

    // Classifies a log line for color-coding
    public static function classify(string $line): string {
        $l = strtolower($line);
        if (str_contains($l, 'fatal error') || str_contains($l, 'uncaught'))          return 'log-fatal';
        if (str_contains($l, 'parse error'))                                            return 'log-fatal';
        if (str_contains($l, 'warning'))                                                return 'log-warning';
        if (str_contains($l, 'notice') || str_contains($l, 'deprecated'))              return 'log-notice';
        if (str_contains($l, 'wp_debug') || str_contains($l, '[debug]'))               return 'log-debug';
        return 'log-default';
    }
}
