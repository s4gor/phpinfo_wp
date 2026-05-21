<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Report {

    const OPT_BRANDING = 'phpinfowp_report_branding';

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    public static function get_branding(): array {
        return wp_parse_args(get_option(self::OPT_BRANDING, []), [
            'enabled'     => false,
            'company'     => '',
            'tagline'     => '',
            'footer_note' => '',
            'accent'      => '#777BB3',
        ]);
    }

    public static function save_branding(array $b): void {
        if (!self::_pro()) return;
        $accent = trim((string)($b['accent'] ?? ''));
        if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $accent)) $accent = '#777BB3';
        update_option(self::OPT_BRANDING, [
            'enabled'     => !empty($b['enabled']),
            'company'     => sanitize_text_field($b['company'] ?? ''),
            'tagline'     => sanitize_text_field($b['tagline'] ?? ''),
            'footer_note' => sanitize_textarea_field($b['footer_note'] ?? ''),
            'accent'      => $accent,
        ], false);
    }

    public static function build(): array {
        if (!self::_pro()) return [];

        $eol     = Phpinfo_WP_EOL::status();
        $grader  = Phpinfo_WP_Config_Grader::run();
        $headers = Phpinfo_WP_Security_Headers::get_cached();
        $ssl     = Phpinfo_WP_SSL::check_all();
        $opcache = Phpinfo_WP_OPcache::is_available() ? Phpinfo_WP_OPcache::status() : null;
        $db      = Phpinfo_WP_DB_Health::server_info();
        $autoload= Phpinfo_WP_DB_Health::autoload_size();
        $db_size = Phpinfo_WP_DB_Health::db_size();
        $cron    = Phpinfo_WP_Cron_Monitor::summary();
        $compat  = Phpinfo_WP_Compat::get_result();

        return [
            'site'        => get_bloginfo('name'),
            'url'         => get_site_url(),
            'generated_at'=> time(),
            'php'         => PHP_VERSION,
            'wp'          => get_bloginfo('version'),
            'eol'         => $eol,
            'grader'      => $grader,
            'headers'     => $headers,
            'ssl'         => $ssl,
            'opcache'     => $opcache,
            'db'          => $db,
            'autoload'    => $autoload,
            'db_size'     => $db_size,
            'cron'        => $cron,
            'compat'      => $compat,
            'branding'    => self::get_branding(),
        ];
    }
}
