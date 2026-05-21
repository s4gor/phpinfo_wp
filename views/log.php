<?php
defined('ABSPATH') or die('Unauthorized Access');

$content_dir = WP_CONTENT_DIR;
$log_dir     = "$content_dir/logs/phpinfo-WP";
$log_file    = "$log_dir/log.txt";

if (!file_exists($log_dir)) wp_mkdir_p($log_dir);
if (!file_exists($log_file)) file_put_contents($log_file, '');

$notice = '';
if (isset($_POST['phpinfowp_clear_log']) && check_admin_referer('phpinfowp_clear_log_nonce')) {
    file_put_contents($log_file, '');
    $notice = 'Activity log cleared.';
}

// Parse entries — stored as "message<br />" lines, newest first
$raw     = file_get_contents($log_file);
$lines   = array_filter(array_map('trim', explode('<br />', $raw)));
$entries = array_reverse(array_values($lines));

function phpinfowp_parse_log_entry(string $raw): array {
    $action = 'unknown';
    $file   = 'unknown';
    $dt     = '';
    $user   = '';

    if (str_contains($raw, 'backed up'))  $action = 'backup';
    elseif (str_contains($raw, 'restored')) $action = 'restore';
    elseif (str_contains($raw, 'edited'))   $action = 'edit';

    if (str_contains($raw, '.user.ini'))   $file = '.user.ini';
    elseif (str_contains($raw, '.htaccess') || str_contains($raw, 'htaccess')) $file = '.htaccess';

    if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $raw, $m)) {
        $dt = $m[1];
    }
    if (preg_match('/by\s+(\S+)$/', $raw, $m)) {
        $user = $m[1];
    }

    return compact('action', 'file', 'dt', 'user', 'raw');
}

function phpinfowp_relative_time(string $dt): string {
    if (!$dt) return '—';
    $diff = time() - (int) strtotime($dt . ' UTC');
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return round($diff / 60) . 'm ago';
    if ($diff < 86400)  return round($diff / 3600) . 'h ago';
    if ($diff < 604800) return round($diff / 86400) . 'd ago';
    return gmdate('M j, Y', strtotime($dt . ' UTC'));
}

$parsed  = array_map('phpinfowp_parse_log_entry', $entries);
$counts  = ['all' => count($parsed), 'edit' => 0, 'backup' => 0, 'restore' => 0];
foreach ($parsed as $e) {
    if (isset($counts[$e['action']])) $counts[$e['action']]++;
}

$filter = sanitize_key($_GET['log_filter'] ?? 'all');
if (!in_array($filter, ['all', 'edit', 'backup', 'restore'])) $filter = 'all';
$search = sanitize_text_field($_GET['log_search'] ?? '');

$filtered = array_filter($parsed, function ($e) use ($filter, $search) {
    if ($filter !== 'all' && $e['action'] !== $filter) return false;
    if ($search && !str_contains(strtolower($e['raw']), strtolower($search))) return false;
    return true;
});
?>

<div class="phpinfowp-pro-page phpinfowp-log-page">

    <!-- Header — uses the shared design-system classes for consistent styling across pages -->
    <div class="phpinfowp-page-header">
        <div>
            <h1>Activity Log</h1>
            <p class="phpinfowp-page-subtitle">
                Tracks every PHP Config change made through phpinfo() WP &middot;
                <strong><?php echo count($parsed); ?></strong> entr<?php echo count($parsed) === 1 ? 'y' : 'ies'; ?>
            </p>
        </div>
        <?php if ($parsed): ?>
        <form method="post" onsubmit="return confirm('Clear the entire activity log? This cannot be undone.')">
            <?php wp_nonce_field('phpinfowp_clear_log_nonce'); ?>
            <input type="hidden" name="phpinfowp_clear_log" value="1">
            <button type="submit" class="button button-secondary">Clear Log</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($notice): ?>
        <div class="notice notice-success inline is-dismissible" style="margin:0 0 16px"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <!-- Filter + Search bar -->
    <div class="phpinfowp-log-controls">
        <div class="phpinfowp-log-filters">
            <?php
            $tabs = [
                'all'     => ['label' => 'All',      'count' => $counts['all']],
                'edit'    => ['label' => 'Edits',    'count' => $counts['edit']],
                'backup'  => ['label' => 'Backups',  'count' => $counts['backup']],
                'restore' => ['label' => 'Restores', 'count' => $counts['restore']],
            ];
            foreach ($tabs as $key => $tab):
                $active = $filter === $key;
                $url    = add_query_arg(['log_filter' => $key, 'log_search' => $search], admin_url('admin.php?page=phpinfowp-log'));
            ?>
                <a href="<?php echo esc_url($url); ?>"
                   class="phpinfowp-log-filter-pill <?php echo $active ? 'active' : ''; ?>">
                    <?php echo esc_html($tab['label']); ?>
                    <span class="phpinfowp-log-filter-count"><?php echo esc_html($tab['count']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div style="position:relative">
            <input type="text" id="phpinfowp-log-search-input"
                   value="<?php echo esc_attr($search); ?>"
                   placeholder="Search entries…"
                   class="regular-text"
                   style="padding-right:32px"
                   oninput="phpinfowpLogSearch(this.value)">
            <button type="button" id="phpinfowp-log-search-clear"
                    style="display:<?php echo $search ? '' : 'none'; ?>;position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#999;font-size:18px;line-height:1;padding:0"
                    onclick="phpinfowpLogSearchClear()">&times;</button>
        </div>
    </div>

    <!-- Timeline -->
    <?php if (empty($parsed)): ?>
        <div class="phpinfowp-log-empty">
            <span class="dashicons dashicons-list-view" style="font-size:40px;width:40px;height:40px;color:#c0c0c0"></span>
            <p style="margin:12px 0 4px;font-size:15px;color:#444">No activity recorded yet</p>
            <p style="margin:0;font-size:13px;color:#888">Use the <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-htaccess')); ?>">PHP Config editor</a> to start tracking changes.</p>
        </div>
    <?php elseif (empty($filtered)): ?>
        <div class="phpinfowp-log-empty">
            <span class="dashicons dashicons-search" style="font-size:40px;width:40px;height:40px;color:#c0c0c0"></span>
            <p style="margin:12px 0 4px;font-size:15px;color:#444">No entries match your filter</p>
        </div>
    <?php else: ?>
        <div class="phpinfowp-timeline" id="phpinfowp-timeline">
            <?php foreach ($filtered as $e):
                $action_meta = match($e['action']) {
                    'edit'    => ['label' => 'EDIT',    'color' => '#777BB3', 'bg' => '#f3f0ff', 'icon' => 'dashicons-edit'],
                    'backup'  => ['label' => 'BACKUP',  'color' => '#0073aa', 'bg' => '#e8f4fc', 'icon' => 'dashicons-upload'],
                    'restore' => ['label' => 'RESTORE', 'color' => '#dba617', 'bg' => '#fff8e5', 'icon' => 'dashicons-undo'],
                    default   => ['label' => 'EVENT',   'color' => '#666',    'bg' => '#f6f7f7', 'icon' => 'dashicons-info'],
                };
            ?>
                <div class="phpinfowp-timeline-entry" style="border-left-color:<?php echo $action_meta['color']; ?>"
                     data-raw="<?php echo esc_attr(strtolower($e['raw'])); ?>">

                    <div class="phpinfowp-timeline-icon" style="background:<?php echo $action_meta['bg']; ?>;color:<?php echo $action_meta['color']; ?>">
                        <span class="dashicons <?php echo $action_meta['icon']; ?>" style="font-size:16px;width:16px;height:16px;line-height:1"></span>
                    </div>

                    <div class="phpinfowp-timeline-body">
                        <div class="phpinfowp-timeline-top">
                            <span class="phpinfowp-timeline-badge" style="background:<?php echo $action_meta['color']; ?>">
                                <?php echo esc_html($action_meta['label']); ?>
                            </span>
                            <?php if ($e['file'] !== 'unknown'): ?>
                                <code class="phpinfowp-timeline-file"><?php echo esc_html($e['file']); ?></code>
                            <?php endif; ?>
                            <?php if ($e['user']): ?>
                                <span class="phpinfowp-timeline-user">
                                    <span class="dashicons dashicons-admin-users" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:2px;color:#999"></span>
                                    <?php echo esc_html($e['user']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="phpinfowp-timeline-meta">
                            <?php if ($e['dt']): ?>
                                <span title="<?php echo esc_attr($e['dt']); ?> UTC"><?php echo esc_html(phpinfowp_relative_time($e['dt'])); ?></span>
                                <span style="color:#ccc">&middot;</span>
                                <span style="color:#aaa"><?php echo esc_html($e['dt']); ?> UTC</span>
                            <?php else: ?>
                                <span style="color:#aaa"><?php echo esc_html($e['raw']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script>
function phpinfowpLogSearch(q) {
    q = q.toLowerCase().trim();
    document.getElementById('phpinfowp-log-search-clear').style.display = q ? '' : 'none';
    var entries = document.querySelectorAll('#phpinfowp-timeline .phpinfowp-timeline-entry');
    var visible = 0;
    entries.forEach(function(el) {
        var match = !q || el.dataset.raw.includes(q);
        el.style.display = match ? '' : 'none';
        if (match) visible++;
    });
}

function phpinfowpLogSearchClear() {
    document.getElementById('phpinfowp-log-search-input').value = '';
    phpinfowpLogSearch('');
}
<?php if ($search): ?>
phpinfowpLogSearch(<?php echo json_encode($search); ?>);
<?php endif; ?>
</script>
