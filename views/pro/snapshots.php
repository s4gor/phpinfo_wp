<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) { require __DIR__ . '/upgrade.php'; return; }

$message  = '';
$msg_type = 'success';
$diff     = null;
$snap_a   = null;
$snap_b   = null;

// Handle actions
if (isset($_POST['phpinfowp_snap_action']) && check_admin_referer('phpinfowp_snap_nonce')) {
    $action = sanitize_text_field($_POST['phpinfowp_snap_action']);

    if ($action === 'take') {
        $label = sanitize_text_field($_POST['snap_label'] ?? '');
        $id    = Phpinfo_WP_Snapshots::take($label ?: 'Manual snapshot');
        $message = "Snapshot #$id saved.";
    } elseif ($action === 'delete' && !empty($_POST['snap_id'])) {
        Phpinfo_WP_Snapshots::delete((int) $_POST['snap_id']);
        $message = 'Snapshot deleted.';
        $msg_type = 'info';
    } elseif ($action === 'diff' && !empty($_POST['snap_a']) && !empty($_POST['snap_b'])) {
        $snap_a = Phpinfo_WP_Snapshots::get((int) $_POST['snap_a']);
        $snap_b = Phpinfo_WP_Snapshots::get((int) $_POST['snap_b']);
        if ($snap_a && $snap_b) {
            $diff = Phpinfo_WP_Snapshots::diff($snap_a->snapshot_data, $snap_b->snapshot_data);
        }
    }
}

$snapshots   = Phpinfo_WP_Snapshots::list(50);
$view_snap   = null;
$view_snap_id = (int) ($_GET['snap_view'] ?? 0);
if ($view_snap_id > 0) {
    $view_snap = Phpinfo_WP_Snapshots::get($view_snap_id);
}
?>

<div class="phpinfowp-pro-page">
    <h1>Config Snapshots <span class="phpinfowp-pro-badge">PRO</span></h1>
    <p style="color:#666;margin-top:-10px">Track php.ini changes over time. Automatic weekly snapshots run via WP Cron.</p>

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <div style="display:flex;gap:32px;align-items:flex-start;flex-wrap:wrap">

        <!-- Take snapshot -->
        <div class="phpinfowp-snap-card">
            <h3 style="margin-top:0">Take Snapshot Now</h3>
            <form method="post">
                <?php wp_nonce_field('phpinfowp_snap_nonce'); ?>
                <input type="hidden" name="phpinfowp_snap_action" value="take">
                <input type="text" name="snap_label" placeholder="Label (optional)" class="regular-text" style="margin-bottom:8px;display:block">
                <button type="submit" class="button button-primary">Take Snapshot</button>
            </form>
        </div>

        <!-- Diff picker -->
        <?php if (count($snapshots) >= 2): ?>
        <div class="phpinfowp-snap-card">
            <h3 style="margin-top:0">Compare Two Snapshots</h3>
            <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
                <?php wp_nonce_field('phpinfowp_snap_nonce'); ?>
                <input type="hidden" name="phpinfowp_snap_action" value="diff">
                <div>
                    <label style="display:block;font-size:12px;margin-bottom:3px">From (older)</label>
                    <select name="snap_a" class="phpinfowp-snap-select">
                        <?php foreach ($snapshots as $s): ?>
                            <option value="<?php echo esc_attr($s->id); ?>"><?php echo esc_html("#{$s->id} {$s->label} — {$s->created_at}"); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;margin-bottom:3px">To (newer)</label>
                    <select name="snap_b" class="phpinfowp-snap-select">
                        <?php foreach ($snapshots as $i => $s): ?>
                            <option value="<?php echo esc_attr($s->id); ?>" <?php selected($i, 0); ?>><?php echo esc_html("#{$s->id} {$s->label} — {$s->created_at}"); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="button button-secondary" style="margin-bottom:1px">Compare &rarr;</button>
            </form>
        </div>
        <?php endif; ?>

    </div>

    <!-- Diff result -->
    <?php if ($diff !== null): ?>
        <div style="margin-top:24px">
            <h2 style="margin-bottom:8px">
                Diff: <em><?php echo esc_html("#{$snap_a->id} {$snap_a->label}"); ?></em>
                &rarr; <em><?php echo esc_html("#{$snap_b->id} {$snap_b->label}"); ?></em>
            </h2>
            <?php if (empty($diff)): ?>
                <div class="notice notice-success inline"><p>No changes detected between these two snapshots.</p></div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped phpinfowp-diff-table">
                    <thead><tr><th style="width:120px">Change</th><th>Directive</th><th>Old Value</th><th>New Value</th></tr></thead>
                    <tbody>
                    <?php foreach ($diff as $item): ?>
                        <tr class="diff-<?php echo esc_attr($item['type']); ?>">
                            <td><span class="diff-badge diff-badge-<?php echo $item['type']; ?>"><?php echo strtoupper($item['type']); ?></span></td>
                            <td><code><?php echo esc_html($item['key']); ?></code></td>
                            <td><?php echo $item['old'] !== null ? '<code>' . esc_html($item['old']) . '</code>' : '—'; ?></td>
                            <td><?php echo $item['new'] !== null ? '<code>' . esc_html($item['new']) . '</code>' : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Snapshot viewer -->
    <?php if ($view_snap): ?>
        <div style="margin-top:24px;padding:20px;background:#f9f9f9;border:1px solid #ddd;border-radius:6px;max-width:720px">
            <h2 style="margin-top:0">
                Snapshot #<?php echo esc_html($view_snap->id); ?>: <?php echo esc_html($view_snap->label); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-snapshots')); ?>" class="button button-small" style="margin-left:12px;vertical-align:middle">← Back</a>
            </h2>
            <p style="font-size:12px;color:#666;margin-top:-12px"><?php echo esc_html($view_snap->created_at); ?> UTC</p>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th style="width:280px">Directive</th><th>Value</th></tr></thead>
                <tbody>
                <?php foreach ($view_snap->snapshot_data as $key => $val): ?>
                    <tr>
                        <td><code><?php echo esc_html($key); ?></code></td>
                        <td><?php echo $val !== null ? '<code>' . esc_html($val) . '</code>' : '<em style="color:#999">not set</em>'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Snapshot list -->
    <h2 style="margin-top:32px">Saved Snapshots (<?php echo count($snapshots); ?>)</h2>
    <?php if (empty($snapshots)): ?>
        <p style="color:#666">No snapshots yet. Take one above — weekly auto-snapshots will accumulate here over time.</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th style="width:50px">#</th><th>Label</th><th>Created</th><th style="width:160px">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($snapshots as $s): ?>
                <tr <?php if ($view_snap_id === (int)$s->id) echo 'style="background:#f0f4ff"'; ?>>
                    <td><?php echo esc_html($s->id); ?></td>
                    <td><?php echo esc_html($s->label); ?></td>
                    <td><?php echo esc_html($s->created_at); ?> UTC</td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-snapshots&snap_view=' . $s->id)); ?>"
                           class="button button-small">View</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this snapshot?')">
                            <?php wp_nonce_field('phpinfowp_snap_nonce'); ?>
                            <input type="hidden" name="phpinfowp_snap_action" value="delete">
                            <input type="hidden" name="snap_id" value="<?php echo esc_attr($s->id); ?>">
                            <button type="submit" class="button button-small">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
