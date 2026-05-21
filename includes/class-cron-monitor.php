<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Cron_Monitor {

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    public static function disabled(): bool {
        return defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
    }

    public static function alt_cron(): bool {
        return defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON;
    }

    // Returns flat list of scheduled events with metadata
    public static function events(): array {
        if (!self::_pro()) return [];

        $crons = _get_cron_array();
        if (!is_array($crons)) return [];

        $now = time();
        $out = [];
        $schedules = wp_get_schedules();

        foreach ($crons as $ts => $hooks) {
            foreach ($hooks as $hook => $events) {
                foreach ($events as $key => $event) {
                    $interval_label = $event['schedule']
                        ? ($schedules[$event['schedule']]['display'] ?? $event['schedule'])
                        : 'One-time';

                    $diff = $ts - $now;
                    $overdue = $diff < -120; // 2 min grace
                    $imminent = $diff <= 60 && $diff >= -120;

                    $out[] = [
                        'hook'         => $hook,
                        'timestamp'    => (int) $ts,
                        'schedule'     => $event['schedule'] ?: 'one-time',
                        'schedule_label' => $interval_label,
                        'interval'     => $event['interval'] ?? null,
                        'args'         => $event['args'] ?? [],
                        'diff'         => $diff,
                        'overdue'      => $overdue,
                        'imminent'     => $imminent,
                        'has_callback' => has_action($hook),
                    ];
                }
            }
        }

        usort($out, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
        return $out;
    }

    public static function summary(): array {
        if (!self::_pro()) return [];

        $events  = self::events();
        $overdue = 0;
        $orphan  = 0; // events with no registered callback
        $imminent= 0;

        foreach ($events as $e) {
            if ($e['overdue'])      $overdue++;
            if (!$e['has_callback']) $orphan++;
            if ($e['imminent'])     $imminent++;
        }

        return [
            'total'     => count($events),
            'overdue'   => $overdue,
            'orphan'    => $orphan,
            'imminent'  => $imminent,
            'disabled'  => self::disabled(),
            'alt_cron'  => self::alt_cron(),
        ];
    }

    public static function run_now(string $hook): bool {
        if (!self::_pro()) return false;
        if (!$hook || !has_action($hook)) return false;
        $ts = wp_next_scheduled($hook);
        if ($ts === false) return false;
        wp_unschedule_event($ts, $hook);
        wp_schedule_single_event(time() - 1, $hook);
        spawn_cron();
        return true;
    }

    public static function delete(string $hook, int $ts): bool {
        if (!self::_pro()) return false;
        return (bool) wp_unschedule_event($ts, $hook);
    }
}
