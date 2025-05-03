<?php

defined('ABSPATH') or die('Unauthorized Access');

function custom_size($path) {
    $kb = get_dirsize($path) / 1000;
    $mb = $kb / 1000;
    $mb = number_format((float)$mb, 2, '.', '');
    if($mb > 1000) {
        $gb = $mb / 1000;
        $gb = number_format((float)$gb, 3, '.', '');
        return $gb . ' GB';
    } else {
        return $mb .  ' MB';
    }
}

$this->thankyou();

?>



<div id="phpinfo-disk-info">
  <h1 style="color: #777BB3;">Basic Information</h1>
  <ul>
      <li><b>Root Directory Size:</b> <?php echo custom_size(ABSPATH); ?></li>
      <li><b>Media Directory Size:</b> <?php echo custom_size(ABSPATH . '/wp-content/uploads'); ?></li>
      <li><b>Installed Plugins:</b> <?php echo count(get_plugins()); ?></li>
      <li><b>Active Plugins:</b> <?php echo count(get_option('active_plugins')); ?></li>
      <li><b>Installed Themes:</b> <?php echo count(wp_get_themes()); ?></li>
      <li><b>Activated Theme:</b> <?php echo wp_get_theme(); ?></li>
      <li><b>Admin Mail:</b> <?php echo get_option('admin_email'); ?></li>
      <p>More information will be available from next update.....</p>
  </ul>
</div>