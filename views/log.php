
<?php

defined('ABSPATH') or die('Unauthorized Access');

$root_dir = get_home_path();
$content_dir = WP_CONTENT_DIR;


if(!file_exists($content_dir . '/logs')) {
    wp_mkdir_p($content_dir . '/logs/phpinfo-WP');
}


if(file_exists("$content_dir/logs/phpinfo-WP")) $phpinfo_log_dir = "$content_dir/logs/phpinfo-WP";


if(file_exists($phpinfo_log_dir) && !file_exists("$phpinfo_log_dir/log.txt")) {
	$log_file = fopen("$phpinfo_log_dir/log.txt", "wb");
	fwrite($log_file,'');
	fclose($log_file);
}

$this->thankyou();

?>

<div id="phpinfo-log">
  <?php echo file_get_contents("$phpinfo_log_dir/log.txt"); ?>
</div>