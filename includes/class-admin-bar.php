<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * Admin bar indicator — turns the pill from a static "PHP X.Y" label into a
 * live health scoreboard. Aggregates signals from existing classes; never
 * triggers expensive operations (HTTP/SSL handshakes) — only reads cached data.
 *
 * Free pill : letter grade + most-urgent free issue
 * Pro pill  : adds SSL, cron, headers, snapshot drift, OPcache regression
 */
class Phpinfo_WP_Admin_Bar {

    const OPT_LAST_UNHEALTHY = 'phpinfowp_last_unhealthy_ts';
    const OPT_OPCACHE_BASELINE = 'phpinfowp_opcache_baseline_hit';

    private const COLOR_GOOD = '#00a32a';
    private const COLOR_WARN = '#dba617';
    private const COLOR_BAD  = '#d63638';

    public static function render(WP_Admin_Bar $bar): void {
        if (!current_user_can('manage_options')) return;

        $status = self::status();
        self::persist_streak_state($status);

        $bar->add_node([
            'id'    => 'phpinfowp-indicator',
            'title' => self::pill_title($status),
            'href'  => self::primary_href($status),
            'meta'  => ['class' => 'phpinfowp-adminbar', 'title' => self::pill_tooltip($status)],
        ]);

        // Issues — only shown when there are any
        foreach ($status['issues'] as $i => $issue) {
            $bar->add_node([
                'parent' => 'phpinfowp-indicator',
                'id'     => 'phpinfowp-issue-' . $i,
                'title'  => self::issue_row($issue),
                'href'   => $issue['href'],
            ]);
        }

        if ($status['issues']) {
            $bar->add_group([
                'parent' => 'phpinfowp-indicator',
                'id'     => 'phpinfowp-server',
            ]);
        }

        // Server section — PHP version, memory peak, OPcache hit rate (Pro)
        $bar->add_node([
            'parent' => $status['issues'] ? 'phpinfowp-server' : 'phpinfowp-indicator',
            'id'     => 'phpinfowp-php',
            'title'  => 'PHP ' . PHP_VERSION,
            'href'   => admin_url('admin.php?page=phpinfowp-viewer'),
        ]);

        $bar->add_node([
            'parent' => $status['issues'] ? 'phpinfowp-server' : 'phpinfowp-indicator',
            'id'     => 'phpinfowp-mem',
            'title'  => self::memory_label($status['memory']),
            'href'   => admin_url('admin.php?page=phpinfowp-info'),
        ]);

        if ($status['is_pro'] && $status['opcache']) {
            $oc = $status['opcache'];
            $arrow = $oc['delta'] < -5 ? ' ↓' : '';
            $bar->add_node([
                'parent' => $status['issues'] ? 'phpinfowp-server' : 'phpinfowp-indicator',
                'id'     => 'phpinfowp-opcache',
                'title'  => 'OPcache: ' . $oc['hit_rate'] . '%' . $arrow,
                'href'   => admin_url('admin.php?page=phpinfowp-opcache'),
            ]);
        }

        // Streak footer — only shown when we have a streak worth bragging about
        if ($status['streak_days'] >= 3) {
            $bar->add_node([
                'parent' => $status['issues'] ? 'phpinfowp-server' : 'phpinfowp-indicator',
                'id'     => 'phpinfowp-streak',
                'title'  => '✓ Healthy for ' . $status['streak_days'] . ' days',
                'href'   => admin_url('admin.php?page=phpinfowp-config-grader'),
            ]);
        }

        if (!$status['is_pro']) {
            $bar->add_node([
                'parent' => $status['issues'] ? 'phpinfowp-server' : 'phpinfowp-indicator',
                'id'     => 'phpinfowp-upgrade',
                'title'  => '★ Unlock SSL · headers · cron alerts',
                'href'   => 'https://exeebit.com/phpinfo-wp#pricing',
                'meta'   => ['target' => '_blank'],
            ]);
        }
    }

    /**
     * Builds the unified status payload from all data sources.
     * Reads cached data only — no fresh HTTP / SSL / OPcache reset calls.
     */
    public static function status(): array {
        $is_pro  = Phpinfo_WP_License::is_valid();
        $grader  = Phpinfo_WP_Config_Grader::summary();
        $eol     = Phpinfo_WP_EOL::status();
        $issues  = [];

        // --- EOL (free) ---
        if ($eol['status'] === 'eol') {
            $issues[] = [
                'level'   => 'critical',
                'label'   => 'PHP ' . $eol['minor'] . ' EOL',
                'detail'  => 'EOL ' . $eol['eol'] . ' — past',
                'href'    => admin_url('admin.php?page=phpinfowp-eol'),
                'sort'    => 0,
            ];
        } elseif ($eol['status'] === 'warning') {
            $issues[] = [
                'level'   => 'warning',
                'label'   => 'EOL ' . $eol['days'] . 'd',
                'detail'  => 'PHP ' . $eol['minor'] . ' EOL ' . $eol['eol'],
                'href'    => admin_url('admin.php?page=phpinfowp-eol'),
                'sort'    => 10,
            ];
        }

        // --- Errors today (free count, viewer is Pro) ---
        $err_count = Phpinfo_WP_Error_Log::today_count();
        if ($err_count > 0) {
            $issues[] = [
                'level'  => $err_count >= 100 ? 'critical' : 'warning',
                'label'  => $err_count . ' error' . ($err_count === 1 ? '' : 's') . ' today',
                'detail' => $err_count . ' PHP error log entries today',
                'href'   => admin_url('admin.php?page=phpinfowp-error-log'),
                'sort'   => 30,
            ];
        }

        // --- Pro signals ---
        $opcache = null;
        if ($is_pro) {
            // SSL — read cached transients only, don't trigger fresh checks
            $site_host = parse_url(get_site_url(), PHP_URL_HOST) ?: '';
            $hosts     = array_unique(array_filter(array_merge(
                [$site_host],
                Phpinfo_WP_SSL::get_extra_domains()
            )));
            foreach ($hosts as $h) {
                $cached = get_transient('phpinfowp_ssl_' . md5($h));
                if (!$cached || !empty($cached['error'])) continue;
                $d = (int) ($cached['days'] ?? 999);
                if ($d < 0) {
                    $issues[] = [
                        'level' => 'critical', 'label' => 'SSL expired (' . $h . ')',
                        'detail' => $h . ' expired ' . abs($d) . 'd ago',
                        'href' => admin_url('admin.php?page=phpinfowp-ssl'),
                        'sort' => 1,
                    ];
                } elseif ($d < 7) {
                    $issues[] = [
                        'level' => 'critical', 'label' => 'SSL ' . $d . 'd (' . $h . ')',
                        'detail' => $h . ' expires in ' . $d . ' days',
                        'href' => admin_url('admin.php?page=phpinfowp-ssl'),
                        'sort' => 2,
                    ];
                } elseif ($d < 30) {
                    $issues[] = [
                        'level' => 'warning', 'label' => 'SSL ' . $d . 'd',
                        'detail' => $h . ' expires in ' . $d . ' days',
                        'href' => admin_url('admin.php?page=phpinfowp-ssl'),
                        'sort' => 15,
                    ];
                }
            }

            // Cron health
            $cron = Phpinfo_WP_Cron_Monitor::summary();
            if ($cron) {
                if (!empty($cron['disabled'])) {
                    $issues[] = [
                        'level' => 'critical', 'label' => 'WP-Cron disabled',
                        'detail' => 'DISABLE_WP_CRON is true — scheduled tasks will not run',
                        'href' => admin_url('admin.php?page=phpinfowp-cron'),
                        'sort' => 3,
                    ];
                } elseif (!empty($cron['overdue'])) {
                    $issues[] = [
                        'level' => 'warning', 'label' => $cron['overdue'] . ' cron overdue',
                        'detail' => $cron['overdue'] . ' scheduled events past due',
                        'href' => admin_url('admin.php?page=phpinfowp-cron'),
                        'sort' => 20,
                    ];
                }
                if (!empty($cron['orphan'])) {
                    $issues[] = [
                        'level' => 'warning', 'label' => $cron['orphan'] . ' orphan cron',
                        'detail' => $cron['orphan'] . ' events with no registered callback',
                        'href' => admin_url('admin.php?page=phpinfowp-cron'),
                        'sort' => 25,
                    ];
                }
            }

            // Security headers — cached only
            $hdr_cache = get_transient('phpinfowp_sec_headers');
            if (is_array($hdr_cache) && isset($hdr_cache['grade'])) {
                $g = $hdr_cache['grade'];
                if (in_array($g, ['F', 'D'], true)) {
                    $issues[] = [
                        'level' => 'warning', 'label' => 'Headers ' . $g,
                        'detail' => 'Security headers score: ' . ($hdr_cache['score'] ?? '?') . '/100',
                        'href' => admin_url('admin.php?page=phpinfowp-security-headers'),
                        'sort' => 22,
                    ];
                }
            }

            // OPcache — cheap to read, surface regressions
            $oc = Phpinfo_WP_OPcache::status();
            if ($oc && $oc['enabled'] && $oc['hit_rate'] !== null) {
                $baseline = (float) get_option(self::OPT_OPCACHE_BASELINE, 0);
                if ($baseline === 0.0 || $oc['hit_rate'] > $baseline) {
                    update_option(self::OPT_OPCACHE_BASELINE, $oc['hit_rate'], false);
                    $baseline = $oc['hit_rate'];
                }
                $delta = $oc['hit_rate'] - $baseline;
                $opcache = ['hit_rate' => $oc['hit_rate'], 'baseline' => $baseline, 'delta' => $delta];

                if (!empty($oc['full'])) {
                    $issues[] = [
                        'level' => 'critical', 'label' => 'OPcache full',
                        'detail' => 'Cache is full — increase opcache.memory_consumption',
                        'href' => admin_url('admin.php?page=phpinfowp-opcache'),
                        'sort' => 4,
                    ];
                } elseif ($delta < -15) {
                    $issues[] = [
                        'level' => 'warning', 'label' => 'OPcache ↓ ' . round($delta) . '%',
                        'detail' => 'Hit rate ' . $oc['hit_rate'] . '% vs baseline ' . $baseline . '%',
                        'href' => admin_url('admin.php?page=phpinfowp-opcache'),
                        'sort' => 28,
                    ];
                }
            }

            // Config drift since last snapshot
            $latest = Phpinfo_WP_Snapshots::get_latest();
            if ($latest) {
                $prev = Phpinfo_WP_Snapshots::get_previous_to((int) $latest->id);
                if ($prev) {
                    $diff = Phpinfo_WP_Snapshots::diff($prev->snapshot_data, $latest->snapshot_data);
                    $n = count($diff);
                    if ($n > 0) {
                        $issues[] = [
                            'level' => 'warning', 'label' => $n . ' config change' . ($n === 1 ? '' : 's'),
                            'detail' => 'php.ini drift since last weekly snapshot',
                            'href' => admin_url('admin.php?page=phpinfowp-snapshots'),
                            'sort' => 26,
                        ];
                    }
                }
            }
        }

        // Sort issues — critical first, then by their `sort` rank
        usort($issues, function($a, $b) {
            $rank = ['critical' => 0, 'warning' => 1];
            $ra = $rank[$a['level']] ?? 9;
            $rb = $rank[$b['level']] ?? 9;
            if ($ra !== $rb) return $ra <=> $rb;
            return ($a['sort'] ?? 99) <=> ($b['sort'] ?? 99);
        });

        $crit_count = 0;
        $warn_count = 0;
        foreach ($issues as $i) {
            if ($i['level'] === 'critical') $crit_count++;
            elseif ($i['level'] === 'warning') $warn_count++;
        }

        $overall = $crit_count > 0 ? 'critical' : ($warn_count > 0 ? 'warning' : 'good');

        return [
            'is_pro'      => $is_pro,
            'grade'       => $grader['grade'] ?? '?',
            'score'       => $grader['score'] ?? 0,
            'eol'         => $eol,
            'issues'      => $issues,
            'crit_count'  => $crit_count,
            'warn_count'  => $warn_count,
            'overall'     => $overall,
            'memory'      => self::memory_payload(),
            'opcache'     => $opcache,
            'streak_days' => self::streak_days(),
        ];
    }

    private static function pill_title(array $s): string {
        $color = self::color_for($s['overall']);

        if ($s['overall'] === 'critical') {
            // Show count if multiple criticals, else first label
            $label = $s['crit_count'] > 1
                ? '! ' . $s['crit_count'] . ' issues'
                : '! ' . $s['issues'][0]['label'];
            $extra = $s['warn_count'] > 0 ? ' +' . $s['warn_count'] : '';
            return '<span style="color:' . $color . ';font-weight:700">' . esc_html($label . $extra) . '</span>';
        }

        if ($s['overall'] === 'warning') {
            // Lead with grade, then first warning label
            $lead = $s['issues'][0]['label'];
            $extra = $s['warn_count'] > 1 ? ' (+' . ($s['warn_count'] - 1) . ')' : '';
            return '<span style="color:' . $color . ';font-weight:600">'
                 . esc_html($s['grade'] . ' · ' . $lead . $extra)
                 . '</span>';
        }

        return '<span style="color:' . $color . ';font-weight:600">'
             . esc_html($s['grade'] . ' · PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) . ' ✓'
             . '</span>';
    }

    private static function pill_tooltip(array $s): string {
        if ($s['overall'] === 'good') {
            return sprintf('Config grade %s · score %d/100 · no active issues', $s['grade'], $s['score']);
        }
        $parts = [];
        foreach ($s['issues'] as $i) $parts[] = $i['label'];
        return implode(' · ', $parts);
    }

    private static function primary_href(array $s): string {
        if ($s['issues']) return $s['issues'][0]['href'];
        return admin_url('admin.php?page=phpinfowp-config-grader');
    }

    private static function issue_row(array $issue): string {
        $color = $issue['level'] === 'critical' ? self::COLOR_BAD : self::COLOR_WARN;
        $dot   = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' . $color . ';margin-right:8px;vertical-align:middle"></span>';
        return $dot . '<strong>' . esc_html($issue['label']) . '</strong>'
             . '<span style="opacity:.7;margin-left:8px;font-size:11px">' . esc_html($issue['detail']) . '</span>';
    }

    private static function memory_label(array $m): string {
        if ($m['peak_bytes'] <= 0) {
            return 'Memory: ' . size_format(memory_get_usage(true)) . ' / ' . $m['limit_label'];
        }
        $pct = $m['pct'];
        $arrow = $pct >= 85 ? ' ⚠' : '';
        return 'Peak today: ' . size_format($m['peak_bytes']) . ' / ' . $m['limit_label']
             . ' (' . $pct . '%)' . $arrow;
    }

    private static function memory_payload(): array {
        $key   = 'phpinfowp_mem_peak_' . date('Ymd');
        $peak  = (int) get_transient($key);
        $limit_raw = ini_get('memory_limit');
        $limit_bytes = self::parse_bytes($limit_raw);
        $pct = ($peak > 0 && $limit_bytes > 0) ? (int) round($peak / $limit_bytes * 100) : 0;
        return [
            'peak_bytes'  => $peak,
            'limit_bytes' => $limit_bytes,
            'limit_label' => $limit_raw,
            'pct'         => $pct,
        ];
    }

    /**
     * Called from a shutdown hook — only writes when peak grows, so the DB
     * cost is at most one transient write per request on admin pages.
     */
    public static function record_memory_peak(): void {
        $peak = memory_get_peak_usage(true);
        $key  = 'phpinfowp_mem_peak_' . date('Ymd');
        $cur  = (int) get_transient($key);
        if ($peak > $cur) {
            set_transient($key, $peak, 36 * HOUR_IN_SECONDS);
        }
    }

    private static function streak_days(): int {
        $ts = (int) get_option(self::OPT_LAST_UNHEALTHY, 0);
        if ($ts <= 0) {
            $install = (int) get_option('phpinfowp_install_ts', 0);
            if ($install <= 0) {
                update_option('phpinfowp_install_ts', time(), false);
                return 0;
            }
            $ts = $install;
        }
        return max(0, (int) floor((time() - $ts) / DAY_IN_SECONDS));
    }

    private static function persist_streak_state(array $status): void {
        if ($status['overall'] === 'good') return;
        $existing = (int) get_option(self::OPT_LAST_UNHEALTHY, 0);
        // Only update if last unhealthy was more than 24h ago, to keep writes bounded
        if (time() - $existing > DAY_IN_SECONDS) {
            update_option(self::OPT_LAST_UNHEALTHY, time(), false);
        }
    }

    private static function color_for(string $level): string {
        return match ($level) {
            'critical' => self::COLOR_BAD,
            'warning'  => self::COLOR_WARN,
            default    => self::COLOR_GOOD,
        };
    }

    private static function parse_bytes(string $val): int {
        $val = trim($val);
        if ($val === '' || $val === '-1') return 0;
        $last = strtolower($val[strlen($val) - 1]);
        $num  = (int) $val;
        return match ($last) {
            'g' => $num * GB_IN_BYTES,
            'm' => $num * MB_IN_BYTES,
            'k' => $num * KB_IN_BYTES,
            default => $num,
        };
    }
}
