<?php

defined('ABSPATH') or die('Unauthorized Access');


// path variables

$root_dir = get_home_path();
$content_dir = WP_CONTENT_DIR;

function new_request() {

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, get_site_url());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);

    $data = curl_exec($ch);

    curl_close($ch);

    return $data;
}

if(!file_exists($content_dir . '/logs')) {
    wp_mkdir_p($content_dir . '/logs/phpinfo-WP');
}


if(file_exists("$content_dir/logs/phpinfo-WP")) $phpinfo_log_dir = "$content_dir/logs/phpinfo-WP";


if(file_exists($phpinfo_log_dir) && !file_exists("$phpinfo_log_dir/log.txt")) {
	$log_file = fopen("$phpinfo_log_dir/log.txt", "wb");
	fwrite($log_file,'');
	fclose($log_file);
}


if(is_writable($root_dir)) {

    $phpinfowp_log = '';
	global $current_user;
	$user = $current_user -> user_login;

    if(isset($_POST['backup']) && $_POST['phpinfo_nonce']) {

        if(wp_verify_nonce($_POST['phpinfo_nonce'], 'phpinfo_nonce')) {

            $backup = fopen("$root_dir/.htaccess.bak", 'wb');
            $htaccess = file_get_contents("$root_dir/.htaccess");
            fwrite($backup, '#BACKED UP by phpinfo() WP' . PHP_EOL);
            fwrite($backup, $htaccess);
            fclose($backup);
            $phpinfowp_log = "htaccess file has been backed up on " . current_time('mysql') . " by $user<br />";
            echo '<script>    window.addEventListener("DOMContentLoaded", () => {
            document.getElementById("phpinfo-output").innerHTML = "FILE HAS BEEN BACKED UP!";
            document.getElementById("phpinfo-output").style.display = "block";
            });</script>';

        } else { die('Unauthorized Access'); }

    } elseif(isset($_POST['restore']) && $_POST['phpinfo_nonce']) {

        if(wp_verify_nonce($_POST['phpinfo_nonce'], 'phpinfo_nonce')) {

            $backup_file = file_get_contents("$root_dir/.htaccess.bak");
            $htaccess = fopen("$root_dir/.htaccess", 'w');

            if(!file_get_contents("$root_dir/htaccess-phpinfo.txt")) {
                $htaccess_phpinfo = fopen("$root_dir/htaccess-phpinfo.txt", 'w');
                fwrite($htaccess_phpinfo, '');
                fclose($htaccess_phpinfo);
            }
           
            fwrite($htaccess, $backup_file);
            fclose($htaccess);

            $phpinfowp_log = "htaccess file has been restored on " . current_time('mysql') . " by $user<br />";
            echo '<script>    window.addEventListener("DOMContentLoaded", () => {
            document.getElementById("phpinfo-output").innerHTML = "FILE HAS BEEN RESTORED!";
            document.getElementById("phpinfo-output").style.display = "block";
            });</script>';

        } else { die('Unauthorized Access'); }
        
    } elseif(isset($_POST['save']) && $_POST['phpinfo_nonce']) {


        if(wp_verify_nonce($_POST['phpinfo_nonce'], 'phpinfo_nonce')) {

            $custom_value = htmlspecialchars($_POST['htaccess']);

            $files = scandir($root_dir);
    
            if(!in_array('htaccess-phpinfo.txt', $files)) {
                $previous_contents = fopen("$root_dir/htaccess.txt", 'wb');
                $content = file_get_contents("$root_dir/.htaccess");
                fwrite($previous_contents, $content . PHP_EOL . PHP_EOL . '# BEGIN htaccess-phpinfo');
                fclose($previous_contents);
                $custom_file = fopen("$root_dir/htaccess-phpinfo.txt", 'wb');
                fwrite($custom_file, $custom_value);
                fclose($custom_file);
                $handle = fopen("$root_dir/htaccess-phpinfo.txt", 'r');
                $custom_values = '';
                if ($handle) {
                    while (($line = fgets($handle)) !== false) {
                        $custom_values .= 'php_value ' . $line;
                    }
                    fclose($handle);
                    $handle = fopen("$root_dir/htaccess-phpinfo-new.txt", 'wb');
                    fwrite($handle, $custom_values);
                    fclose($handle);
                }
                $htaccess = fopen("$root_dir/.htaccess", 'w');
                $new_content = file_get_contents("$root_dir/htaccess-phpinfo-new.txt");
                $previous_contents = file_get_contents("$root_dir/htaccess.txt");
                fwrite($htaccess, $previous_contents . PHP_EOL . $new_content);
                fclose($htaccess);
                unlink("$root_dir/htaccess-phpinfo-new.txt");
            } else {
                $custom_file = fopen("$root_dir/htaccess-phpinfo.txt", 'w+');
                fwrite($custom_file, $custom_value);
                fclose($custom_file);
                $handle = fopen("$root_dir/htaccess-phpinfo.txt", 'r');
                $custom_values = '';
                if ($handle) {
                    while (($line = fgets($handle)) !== false) {
                        $custom_values .= 'php_value ' . $line;
                    }
                    fclose($handle);
                    $handle = fopen("$root_dir/htaccess-phpinfo-new.txt", 'wb');
                    fwrite($handle, $custom_values);
                    fclose($handle);
                }
                $htaccess = fopen("$root_dir/.htaccess", 'w');
                $previous_contents = file_get_contents("$root_dir/htaccess.txt");
                $new_content = file_get_contents("$root_dir/htaccess-phpinfo-new.txt");
                fwrite($htaccess, $previous_contents . PHP_EOL . $new_content);
                fclose($htaccess);
                unlink("$root_dir/htaccess-phpinfo-new.txt");
            }
    
            $data = new_request();
    
            if(strpos($data, 'Internal Server Error')) {
    
                $backup_file = file_get_contents("$root_dir/.htaccess.bak");
                $htaccess = fopen("$root_dir/.htaccess", 'w');
                fwrite($htaccess, $backup_file);
                fclose($htaccess);
    
                echo '<script>    window.addEventListener("DOMContentLoaded", () => {
                document.getElementById("phpinfo-output").innerHTML = "CAN\'T SAVE. ERROR ON YOU CODE";
                document.getElementById("phpinfo-output").style.display = "block";
            });</script>';
            } else {
    
                $phpinfowp_log = "htaccess file has been edited on " . current_time('mysql') . " by $user<br />";
                echo '<script>    window.addEventListener("DOMContentLoaded", () => {
                document.getElementById("phpinfo-output").innerHTML = "FILE HAS BEEN SAVED!";
                document.getElementById("phpinfo-output").style.display = "block";
            });</script>';
            }

        } else { die('Unauthorized Access'); }

    }

	$log_file = fopen("$phpinfo_log_dir/log.txt", "a");
	fwrite($log_file, $phpinfowp_log);
	fclose($log_file);

} else {
    echo '<div id="htaccess-warning" style="font-size: 20px;">need write permissions on root directory. Can\'t perform the action</div>';
}

$this->thankyou();

?>


    <div id="phpinfo-htaccess" class="tabcontent" style="display: block">

        <div id="phpinfo-output" style="display: none"></div>

        <div id="htaccess-phpinfo">
            <div id="htaccess-phpinfo-des"><b>This is only for <span style="color: #777BB3;">Apache Server</span></b>. Use this form to set, change value of PHP configurations. You can change any value of PHP's configuration. All you have to do is to follow the rules how to use it. Just write the directive name without php_value tag like, upload_max_filesize. and the write the value. <b>e.g. upload_max_filesize 200M</b>. <br />To change or set another directive, in new line, write the directive, a space, then the value. To understand this thing better, see placeholder</div>

            <form action="" METHOD="post" class="editor">
                <div>
                    <input type="hidden" name="phpinfo_nonce" value="<?php echo wp_create_nonce('phpinfo_nonce'); ?>">
                    <textarea name="htaccess" id="htaccess-editor" placeholder="max_file_uploads 25 upload_max_filesize 60M"><?php
                            $files = scandir($root_dir);
                            if(in_array('htaccess-phpinfo.txt', $files)) {
                                $data = file_get_contents("$root_dir/htaccess-phpinfo.txt");
                                echo htmlspecialchars($data);
                            }
                        ?></textarea>
                </div>
                <div>
                    <button name="save" id="phpinfo-htaccess-save">Save</button>
                    <button name="backup" id="phpinfo-htaccess-backup">Backup</button>
                    <button name="restore" id="phpinfo-htaccess-restore">Restore</button>
                </div>
            </form>
        </div>

    </div>