<?php defined('ABSPATH') or die('Unauthorized Access'); ?>


<div id="phpinfo-info">
  <div id="phpinfo-WP">
    <?php
    ob_start ();
    phpinfo (INFO_ALL & ~INFO_LICENSE & ~INFO_CREDITS);
    $info = ob_get_contents ();
    ob_end_clean ();
    echo ( str_replace ( "module_Zend Optimizer", "module_Zend_Optimizer", preg_replace ( '%^.*<body>(.*)</body>.*$%ms', '$1', $info ) ) ) ;

    $this->thankyou();

    ?>
    <button id="topButton-phpinfo-WP" title="Go to top" style="display: none"><img src="<?php echo plugin_dir_url(__FILE__) . '../assets/images/top.png'; ?>" alt="Top" id="topButtonImage-phpinfo-WP"></button>
  </div>
</div>