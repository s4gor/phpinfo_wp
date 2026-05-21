<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Snapshots {

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    // Only track directives that actually matter — full ini_get_all() is hundreds of noisy keys
    const TRACKED = [
        'memory_limit', 'upload_max_filesize', 'post_max_size', 'max_execution_time',
        'max_input_time', 'max_input_vars', 'max_file_uploads', 'display_errors',
        'display_startup_errors', 'error_reporting', 'error_log', 'log_errors',
        'expose_php', 'allow_url_fopen', 'allow_url_include', 'disable_functions',
        'file_uploads', 'default_socket_timeout', 'date.timezone',
        'session.gc_maxlifetime', 'session.use_strict_mode',
        'session.cookie_httponly', 'session.cookie_secure',
        'opcache.enable', 'opcache.memory_consumption', 'opcache.max_accelerated_files',
        'opcache.validate_timestamps', 'opcache.revalidate_freq',
        'zlib.output_compression', 'default_charset', 'short_open_tag',
        'mbstring.internal_encoding', 'precision', 'serialize_precision',
    ];

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'phpinfowp_snapshots';
    }

    public static function install_table(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE IF NOT EXISTS " . self::table() . " (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            label        VARCHAR(255)    NOT NULL DEFAULT '',
            created_by   BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at   DATETIME        NOT NULL,
            snapshot_data LONGTEXT       NOT NULL,
            PRIMARY KEY  (id),
            KEY          created_at (created_at)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function capture(): array {
        if (!self::_pro()) return [];
        $data = [];
        foreach (self::TRACKED as $key) {
            $val = ini_get($key);
            $data[$key] = ($val === false) ? null : $val;
        }
        // Also record PHP version itself
        $data['__php_version'] = PHP_VERSION;
        return $data;
    }

    public static function take(string $label = '', int $user_id = 0): int {
        if (!self::_pro()) return 0;
        global $wpdb;
        $data = self::capture();
        $wpdb->insert(self::table(), [
            'label'         => $label ?: 'Manual snapshot',
            'created_by'    => $user_id ?: get_current_user_id(),
            'created_at'    => current_time('mysql', true),
            'snapshot_data' => wp_json_encode($data),
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function list(int $limit = 50): array {
        if (!self::_pro()) return [];
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, label, created_by, created_at FROM " . self::table() . " ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }

    public static function get(int $id): ?object {
        if (!self::_pro()) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d LIMIT 1", $id
        ));
        if (!$row) return null;
        $row->snapshot_data = json_decode($row->snapshot_data, true) ?? [];
        return $row;
    }

    public static function delete(int $id): void {
        if (!self::_pro()) return;
        global $wpdb;
        $wpdb->delete(self::table(), ['id' => $id], ['%d']);
    }

    public static function get_latest(): ?object {
        if (!self::_pro()) return null;
        global $wpdb;
        $row = $wpdb->get_row(
            "SELECT * FROM " . self::table() . " ORDER BY created_at DESC LIMIT 1"
        );
        if (!$row) return null;
        $row->snapshot_data = json_decode($row->snapshot_data, true) ?? [];
        return $row;
    }

    public static function get_previous_to(int $id): ?object {
        if (!self::_pro()) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id < %d ORDER BY id DESC LIMIT 1", $id
        ));
        if (!$row) return null;
        $row->snapshot_data = json_decode($row->snapshot_data, true) ?? [];
        return $row;
    }

    // Returns array of ['type'=>added|removed|changed, 'key'=>..., 'old'=>..., 'new'=>...]
    public static function diff(array $old_data, array $new_data): array {
        if (!self::_pro()) return [];
        $changes = [];

        foreach ($old_data as $key => $old_val) {
            if (!array_key_exists($key, $new_data)) {
                $changes[] = ['type' => 'removed', 'key' => $key, 'old' => $old_val, 'new' => null];
            } elseif ((string) $old_val !== (string) $new_data[$key]) {
                $changes[] = ['type' => 'changed', 'key' => $key, 'old' => $old_val, 'new' => $new_data[$key]];
            }
        }
        foreach ($new_data as $key => $new_val) {
            if (!array_key_exists($key, $old_data)) {
                $changes[] = ['type' => 'added', 'key' => $key, 'old' => null, 'new' => $new_val];
            }
        }

        usort($changes, fn($a, $b) => strcmp($a['key'], $b['key']));
        return $changes;
    }

    // Auto-snapshot called by cron. Returns diff vs. previous if changes detected.
    public static function auto_snapshot(): array {
        if (!self::_pro()) return [];
        $new_id  = self::take('Auto (weekly)', 0);
        $new     = self::get($new_id);
        $prev    = self::get_previous_to($new_id);

        if (!$prev) return [];
        return self::diff($prev->snapshot_data, $new->snapshot_data);
    }

    public static function prune(int $keep = 30): void {
        if (!self::_pro()) return;
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM " . self::table() . " ORDER BY created_at DESC LIMIT %d, 9999", $keep
        ));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $wpdb->query($wpdb->prepare("DELETE FROM " . self::table() . " WHERE id IN ($placeholders)", ...$ids));
        }
    }
}
