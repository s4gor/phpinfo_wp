<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Network {

    public static function register(): void {
        if (!is_multisite()) return;
        add_action('network_admin_menu', [__CLASS__, 'menu']);
    }

    public static function menu(): void {
        add_menu_page(
            'phpinfo() WP', 'phpinfo() WP', 'manage_network_options',
            'phpinfowp-network', [__CLASS__, 'view'],
            Phpinfo_wp::menu_icon(), 99
        );
    }

    public static function view(): void {
        if (!current_user_can('manage_network_options')) return;
        require PHPINFOWP_DIR . 'views/pro/network.php';
    }

    public static function sites(): array {
        if (!is_multisite()) return [];
        $sites = get_sites(['number' => 200]);
        $out = [];
        foreach ($sites as $site) {
            switch_to_blog((int) $site->blog_id);
            $autoload = Phpinfo_WP_License::is_valid() ? Phpinfo_WP_DB_Health::autoload_size() : null;
            $out[] = [
                'blog_id'  => (int) $site->blog_id,
                'url'      => get_site_url(),
                'name'     => get_bloginfo('name'),
                'autoload' => $autoload ? $autoload['bytes'] : null,
                'autoload_status' => $autoload['status'] ?? null,
            ];
            restore_current_blog();
        }
        return $out;
    }
}
