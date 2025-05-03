<?php

/*
Plugin Name: phpinfo WP
Plugin URI: http://exeebit.com/wordpress-plugins/phpinfo-wp
Description: A simple plugin to look up information about PHP and manage PHP configurations and directive values.
Version: 6.1
Author: Exeebit
Author URI: http://exeebit.com
License: GPLv3
*/

/**
 *
 * @package phpinfo_wp
 *
 */

defined('ABSPATH') or die('Unauthorized Access');

if(!class_exists( 'Phpinfo_wp' )):

	class Phpinfo_wp {

		function register() {
			add_action('admin_menu', array($this, 'add_admin_pages'));
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
			add_filter('clean_url', [$this, 'script_async'], 11, 1);
			add_filter("plugin_row_meta", [$this, "meta"], 10, 2);
			add_filter( 'plugin_action_links', [$this, 'ads_action_links'], 10, 5 );
		}

		public function add_admin_pages() {
		add_menu_page( 'phpinfo() WP', 'phpinfo() WP', 
       'manage_options', 'phpinfo-wp', [$this, 'phpinfo_view'], 'dashicons-tickets', 99 );

    	add_submenu_page(
        'phpinfo-wp',
        __( '.htaccess editor', 'textdomain' ),
        __( '.htaccess editor', 'textdomain' ),
        'manage_options',
        'htaccess_editor',
        [$this, 'htaccess_view']
    );
		add_submenu_page(
        'phpinfo-wp',
        __( 'Extensions', 'textdomain' ),
        __( 'Extensions', 'textdomain' ),
        'manage_options',
        'extensions',
        [$this, 'extension_view']
    );
		add_submenu_page(
        'phpinfo-wp',
        __( 'Basic Info', 'textdomain' ),
        __( 'Basic Info', 'textdomain' ),
        'manage_options',
        'info',
        [$this, 'info_view']
    );
		add_submenu_page(
        'phpinfo-wp',
        __( 'Log', 'textdomain' ),
        __( 'Log', 'textdomain' ),
        'manage_options',
        'log',
        [$this, 'log_view']
    );
		
		}

		public function phpinfo_view() {
			require_once plugin_dir_path( __FILE__ ) . 'views/phpinfo.php';
		}

		public function htaccess_view() {
			require_once plugin_dir_path( __FILE__ ) . 'views/htaccess.php';
		}

		public function extension_view() {
			require_once plugin_dir_path( __FILE__ ) . 'views/extension.php';
		}

		public function info_view() {
			require_once plugin_dir_path( __FILE__ ) . 'views/info.php';
		}

		public function log_view() {
			require_once plugin_dir_path( __FILE__ ) . 'views/log.php';
		}

		public function activate() {
			flush_rewrite_rules();
		}

		public function deactivate() {
			flush_rewrite_rules();
		}

		public function enqueue() {
			wp_enqueue_style('phpinfo-WP', plugins_url( 'css/style.css', __FILE__ ));
			wp_enqueue_script('phpinfo-WP', plugin_dir_url(__FILE__) . 'js/scripts.js#async');
		}

		public function script_async($url) {
		    if(strpos($url, '#async') === false) {
		        return $url;
            } else {
		        return str_replace('#async', '', $url) . "' async='async";
            }
        }

        public function footer_notice(){
            echo '<span id="footer-thankyou">Thank you for using <a href="https://wordpress.org/plugins/phpinfo-wp/">phpinfo() WP</a>. <a href="http://exeebit.com/wordpress-plugins/phpinfo-wp/donate" target="_blank">Buy Me a Coffee</a><span style="color: red"> &#x2764;</span></span>';
        }

        public function thankyou() {
            add_filter("admin_footer_text", [$this, 'footer_notice']);
        }

        public function meta($links = [], $file = "") {
        	if(strpos($file, "phpinfo-wp/phpinfo-wp.php") !== false) {
            	$new_link = [
                	"donation" => '<a href="http://exeebit.com/wordpress-plugins/phpinfo-wp/donate" target="_blank">Buy Me a Coffee <span style="color: red">&#x2764;</span></a>'
            	];

            	$links = array_merge($links, $new_link);
        	}

        	return $links;

        }

		public function ads_action_links( $links, $plugin_file ) {

			$plugin = plugin_basename( __FILE__ );

			if($plugin === $plugin_file) {
				$ads_links = [
					'<a href="' . admin_url( 'admin.php?page=phpinfo-wp' ) . '">Settings</a>',
				];
				$links = array_merge($ads_links, $links);
			}
			return $links;
		}

	}

	if(class_exists( 'Phpinfo_wp' )) $phpinfo_wp = new Phpinfo_wp();
	else die('Plugin internal code conflict');

	$phpinfo_wp->register();

	register_activation_hook(__FILE__, [$phpinfo_wp, 'activate']);
	register_deactivation_hook(__FILE__, [$phpinfo_wp, 'deactivate']);

	endif;
	