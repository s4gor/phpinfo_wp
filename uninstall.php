<?php

/**
 *
 *
 *@package
 *
 */

defined("WP_UNINSTALL_PLUGIN") or die();

unlink('../htaccess.txt');
unlink('../htaccess-phpinfo.txt');
