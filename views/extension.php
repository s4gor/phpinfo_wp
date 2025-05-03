<?php

defined('ABSPATH') or die('Unauthorized Access');

?>
<div id="phpinfo-extensions" style="margin-top:50px">
<?php

$extensions = ['bz2', 'curl', 'ffi', 'ftp', 'fileinfo', 'gd', 'gettext', 'gmp', 'intl', 'imap', 'ldap', 'mbstring', 'exif', 'mysqli', 'oci8_12c', 'oci8_19', 'odbc', 'openssl', 'pdo_firebird', 'pdo_mysql', 'pdo_oci', 'pdo_odbc', 'pdo_pgsql', 'pdo_sqlite', 'shmop', 'pgsql', 'snmp', 'soap', 'sockets', 'sodium', 'sqlite3', 'tidy', 'xsl', 'Core', 'PDO', 'Phar', 'Reflection', 'SPL', 'SimpleXML', 'apache2handler', 'bcmath', 'calendar', 'ctype', 'date', 'dom', 'filter', 'hash', 'iconv', 'json', 'libxml', 'mysqlnd', 'pcre', 'readline', 'session', 'standard', 'tokenizer', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib', 'imagick'];

asort($extensions);


function check_extension($extension) {
    $loaded_extensions = get_loaded_extensions();

    if(in_array($extension, $loaded_extensions)) {
        return "<li><input type='checkbox' id='phpinfo-extension' name='$extension' value='$extension' checked disabled><label for='$extension'>$extension</label></li>";
    } else {
        return "<li><input type='checkbox' name='$extension' value='$extension' id='phpinfo-extension' disabled><label for='$extension'>$extension</label></li>";
    }
}

echo '<ul class="phpinfo-extensions">';

foreach($extensions as $extension) {
    echo check_extension($extension);
}

echo '</ul>';

$this->thankyou();


?>
</div>
