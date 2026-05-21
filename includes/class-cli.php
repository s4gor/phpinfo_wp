<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!(defined('WP_CLI') && WP_CLI)) return;

/**
 * Manage phpinfo() WP from the command line.
 */
class Phpinfo_WP_CLI {

    /**
     * Show PHP / WP / EOL status summary.
     *
     * ## EXAMPLES
     *     wp phpinfowp status
     */
    public function status($args, $assoc): void {
        $eol = Phpinfo_WP_EOL::status();
        WP_CLI::log('PHP:        ' . PHP_VERSION);
        WP_CLI::log('Minor:      ' . $eol['minor']);
        WP_CLI::log('EOL:        ' . ($eol['eol'] ?? 'unknown') . ' (' . $eol['status'] . ')');
        if ($eol['days'] !== null) {
            WP_CLI::log('Days:       ' . $eol['days']);
        }
        WP_CLI::log('WordPress:  ' . get_bloginfo('version'));
        WP_CLI::log('Memory:     ' . size_format(memory_get_usage(true)) . ' / ' . ini_get('memory_limit'));
        WP_CLI::log('License:    ' . (Phpinfo_WP_License::is_valid() ? 'Active (Pro)' : 'Inactive'));
    }

    /**
     * Run the Pro config grader and print the result.
     *
     * ## EXAMPLES
     *     wp phpinfowp grade
     */
    public function grade($args, $assoc): void {
        if (!Phpinfo_WP_License::is_valid()) {
            WP_CLI::error('Pro license required.');
        }
        $r = Phpinfo_WP_Config_Grader::run();
        WP_CLI::log("Grade: {$r['grade']}  ({$r['score']}/100)");
        $failing = array_filter($r['checks'], fn($c) => $c['status'] === 'fail');
        if (!$failing) {
            WP_CLI::success('All checks passing.');
            return;
        }
        $rows = [];
        foreach ($failing as $c) {
            $rows[] = ['directive' => $c['key'], 'current' => $c['value'], 'recommended' => $c['good']];
        }
        WP_CLI\Utils\format_items('table', $rows, ['directive', 'current', 'recommended']);
    }

    /**
     * Take a config snapshot.
     *
     * ## OPTIONS
     * [--label=<label>]
     * : Optional label.
     */
    public function snapshot($args, $assoc): void {
        if (!Phpinfo_WP_License::is_valid()) WP_CLI::error('Pro license required.');
        $label = $assoc['label'] ?? 'CLI snapshot';
        $id = Phpinfo_WP_Snapshots::take($label, 0);
        if ($id) WP_CLI::success("Snapshot #{$id} captured.");
        else     WP_CLI::error('Failed to capture snapshot.');
    }

    /**
     * Check SSL certificates for the site + configured domains.
     */
    public function ssl($args, $assoc): void {
        if (!Phpinfo_WP_License::is_valid()) WP_CLI::error('Pro license required.');
        $results = Phpinfo_WP_SSL::check_all();
        $rows = [];
        foreach ($results as $r) {
            $rows[] = [
                'host'   => $r['host'] ?? '—',
                'expiry' => $r['expiry'] ?? '—',
                'days'   => $r['days'] ?? '—',
                'status' => $r['status'] ?? ($r['error'] ?? '—'),
            ];
        }
        WP_CLI\Utils\format_items('table', $rows, ['host', 'expiry', 'days', 'status']);
    }

    /**
     * Run the compatibility scanner.
     *
     * ## OPTIONS
     * [--target=<version>]
     * : Target PHP version (default 8.2).
     */
    public function compat($args, $assoc): void {
        if (!Phpinfo_WP_License::is_valid()) WP_CLI::error('Pro license required.');
        $target = $assoc['target'] ?? '8.2';
        WP_CLI::log("Scanning against PHP {$target}...");
        $r = Phpinfo_WP_Compat::scan($target);
        if (isset($r['error'])) WP_CLI::error($r['error']);
        WP_CLI::log("Files scanned: {$r['files']}  ({$r['duration']}s)");
        WP_CLI::log("Total issues:  {$r['total']}  across {$r['with_issues']} plugins/themes");
        if ($r['total'] === 0) WP_CLI::success('No compatibility issues found.');
    }

    /**
     * Show database health summary.
     */
    public function db($args, $assoc): void {
        if (!Phpinfo_WP_License::is_valid()) WP_CLI::error('Pro license required.');
        $server   = Phpinfo_WP_DB_Health::server_info();
        $autoload = Phpinfo_WP_DB_Health::autoload_size();
        $size     = Phpinfo_WP_DB_Health::db_size();
        WP_CLI::log("Engine:    {$server['engine']} {$server['version']}  ({$server['status']})");
        WP_CLI::log("EOL:       " . ($server['eol'] ?? 'unknown'));
        WP_CLI::log("DB size:   " . size_format($size['total']) . " ({$size['tables']} tables)");
        WP_CLI::log("Autoload:  " . size_format($autoload['bytes']) . " ({$autoload['count']} options, {$autoload['status']})");
    }

    /**
     * List WP-Cron events with overdue/orphan status.
     */
    public function cron($args, $assoc): void {
        if (!Phpinfo_WP_License::is_valid()) WP_CLI::error('Pro license required.');
        $events = Phpinfo_WP_Cron_Monitor::events();
        $rows = [];
        foreach ($events as $e) {
            $rows[] = [
                'hook'     => $e['hook'],
                'next_run' => wp_date('Y-m-d H:i', $e['timestamp']),
                'schedule' => $e['schedule_label'],
                'status'   => $e['overdue'] ? 'overdue' : ($e['has_callback'] ? 'ok' : 'orphan'),
            ];
        }
        WP_CLI\Utils\format_items('table', $rows, ['hook', 'next_run', 'schedule', 'status']);
    }
}

WP_CLI::add_command('phpinfowp', 'Phpinfo_WP_CLI');
