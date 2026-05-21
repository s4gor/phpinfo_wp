<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) { require __DIR__ . '/upgrade.php'; return; }

if (isset($_POST['phpinfowp_purge_transients']) && check_admin_referer('phpinfowp_db_nonce')) {
    $n = Phpinfo_WP_DB_Health::purge_expired_transients();
    $purge_msg = "Purged {$n} expired transients.";
}

$server    = Phpinfo_WP_DB_Health::server_info();
$autoload  = Phpinfo_WP_DB_Health::autoload_size();
$transients= Phpinfo_WP_DB_Health::transients();
$tables    = Phpinfo_WP_DB_Health::tables();
$db_size   = Phpinfo_WP_DB_Health::db_size();

$status_color = ['ok' => '#00a32a', 'warning' => '#dba617', 'fail' => '#d63638', 'eol' => '#d63638', 'unknown' => '#888'];
$status_label = ['ok' => 'HEALTHY', 'warning' => 'WARNING', 'fail' => 'CRITICAL', 'eol' => 'END OF LIFE', 'unknown' => 'UNKNOWN'];
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>Database Health <span class="phpinfowp-pro-badge">PRO</span></h1>
            <p class="phpinfowp-page-subtitle">MySQL/MariaDB version, autoload bloat, transients, and table size</p>
        </div>
    </div>

    <?php if (isset($purge_msg)): ?>
        <div class="notice notice-success inline" style="margin:0 0 20px"><p><?php echo esc_html($purge_msg); ?></p></div>
    <?php endif; ?>

    <!-- DB server card -->
    <?php if ($server):
        $color = $status_color[$server['status']];
        $label = $status_label[$server['status']];
    ?>
    <div class="phpinfowp-dbh-server" style="border-left-color:<?php echo $color; ?>">
        <div>
            <div class="phpinfowp-dbh-server-engine"><?php echo esc_html($server['engine']); ?> <?php echo esc_html($server['version']); ?></div>
            <div class="phpinfowp-dbh-server-full"><?php echo esc_html($server['full']); ?></div>
        </div>
        <div class="phpinfowp-dbh-server-status">
            <span class="phpinfowp-dbh-badge" style="background:<?php echo $color; ?>"><?php echo $label; ?></span>
            <?php if ($server['eol']): ?>
                <div class="phpinfowp-dbh-server-eol">
                    EOL: <strong><?php echo esc_html($server['eol']); ?></strong>
                    <?php if ($server['days'] !== null): ?>
                        &middot;
                        <?php if ($server['days'] < 0): ?>
                            <span style="color:#d63638"><?php echo abs($server['days']); ?> days ago</span>
                        <?php else: ?>
                            <?php echo (int)$server['days']; ?> days
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Key metrics row -->
    <div class="phpinfowp-dbh-metrics">

        <div class="phpinfowp-dbh-metric">
            <div class="phpinfowp-dbh-metric-label">Database size</div>
            <div class="phpinfowp-dbh-metric-value"><?php echo size_format($db_size['total']); ?></div>
            <div class="phpinfowp-dbh-metric-sub"><?php echo (int)$db_size['tables']; ?> tables</div>
        </div>

        <?php
        $a_color = $status_color[$autoload['status']];
        $a_label = $status_label[$autoload['status']];
        ?>
        <div class="phpinfowp-dbh-metric" style="border-top-color:<?php echo $a_color; ?>">
            <div class="phpinfowp-dbh-metric-label">Autoload data <span class="phpinfowp-dbh-metric-badge" style="background:<?php echo $a_color; ?>"><?php echo $a_label; ?></span></div>
            <div class="phpinfowp-dbh-metric-value"><?php echo size_format($autoload['bytes']); ?></div>
            <div class="phpinfowp-dbh-metric-sub"><?php echo (int)$autoload['count']; ?> options &middot; loaded on every page</div>
        </div>

        <div class="phpinfowp-dbh-metric">
            <div class="phpinfowp-dbh-metric-label">Transients</div>
            <div class="phpinfowp-dbh-metric-value"><?php echo (int)$transients['total']; ?></div>
            <div class="phpinfowp-dbh-metric-sub">
                <?php if ($transients['expired']): ?>
                    <span style="color:#d63638"><strong><?php echo (int)$transients['expired']; ?></strong> expired</span> — clogging the table
                <?php else: ?>
                    All current
                <?php endif; ?>
            </div>
        </div>

        <div class="phpinfowp-dbh-metric">
            <div class="phpinfowp-dbh-metric-label">Overhead</div>
            <div class="phpinfowp-dbh-metric-value"><?php echo size_format($db_size['free']); ?></div>
            <div class="phpinfowp-dbh-metric-sub">Reclaimable via OPTIMIZE TABLE</div>
        </div>

    </div>

    <?php if ($transients['expired']): ?>
        <form method="post" style="margin-bottom:24px">
            <?php wp_nonce_field('phpinfowp_db_nonce'); ?>
            <button type="submit" name="phpinfowp_purge_transients" value="1" class="button button-secondary">
                <span class="dashicons dashicons-trash" style="vertical-align:middle"></span>
                Purge <?php echo (int)$transients['expired']; ?> expired transients
            </button>
        </form>
    <?php endif; ?>

    <!-- Top autoload options -->
    <?php if ($autoload['top']): ?>
        <h2 class="phpinfowp-section-heading">Top Autoload Options</h2>
        <p class="description" style="margin:0 0 12px">
            Options with <code>autoload=yes</code> load on every page request. Large autoloaded values slow the entire admin and front-end.
        </p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:60%">Option name</th>
                    <th style="width:20%">Size</th>
                    <th style="width:20%">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($autoload['top'] as $opt):
                    $opt_color = $opt->size > 102400 ? '#d63638' : ($opt->size > 10240 ? '#dba617' : '#555');
                ?>
                    <tr>
                        <td><code><?php echo esc_html($opt->option_name); ?></code></td>
                        <td style="color:<?php echo $opt_color; ?>;font-weight:600"><?php echo size_format((int)$opt->size); ?></td>
                        <td>
                            <?php if ($opt->size > 10240): ?>
                                <span style="font-size:11px;color:#666">Consider <code>autoload=no</code></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- All tables -->
    <h2 class="phpinfowp-section-heading" style="margin-top:32px">All Tables</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Table</th>
                <th style="width:90px">Rows</th>
                <th style="width:100px">Data</th>
                <th style="width:100px">Index</th>
                <th style="width:110px">Overhead</th>
                <th style="width:80px">Engine</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tables as $t):
                $ovr_color = $t['overhead_pct'] > 30 ? '#d63638' : ($t['overhead_pct'] > 10 ? '#dba617' : '#888');
            ?>
                <tr>
                    <td><code><?php echo esc_html($t['name']); ?></code></td>
                    <td><?php echo number_format($t['rows']); ?></td>
                    <td><?php echo size_format($t['data']); ?></td>
                    <td><?php echo size_format($t['index']); ?></td>
                    <td style="color:<?php echo $ovr_color; ?>">
                        <?php if ($t['free'] > 0): ?>
                            <?php echo size_format($t['free']); ?> <span style="font-size:11px">(<?php echo esc_html($t['overhead_pct']); ?>%)</span>
                        <?php else: ?>
                            <span style="color:#aaa">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span style="font-size:11px;color:#666"><?php echo esc_html($t['engine']); ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>
