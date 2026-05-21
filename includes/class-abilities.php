<?php
defined('ABSPATH') or die('Unauthorized Access');

// WP 7.0 Abilities API integration. Exposes the audit data this plugin
// already collects (PHP version + EOL, config grade, directives, extensions)
// as named server abilities so AI assistants and other plugins on the site
// can introspect server health through a standard interface instead of
// scraping the phpinfo screen.
//
// All abilities require manage_options because they expose server config —
// the same gate every visible page of this plugin uses.

class Phpinfo_WP_Abilities {

    public static function register(): void {
        // Abilities API ships in WP 7.0. On older cores the hook never fires,
        // so the rest of the plugin keeps working unchanged.
        add_action('wp_abilities_api_init', [self::class, 'register_abilities']);
    }

    public static function register_abilities(): void {
        if (!function_exists('wp_register_ability_category') || !function_exists('wp_register_ability')) {
            return;
        }

        wp_register_ability_category('phpinfowp/audit', [
            'label'       => __('Server Health Audit', 'phpinfo-wp'),
            'description' => __('Inspect PHP version, server configuration, extensions, and audit grade collected by phpinfo() WP.', 'phpinfo-wp'),
        ]);

        wp_register_ability('phpinfowp/get-php-version', [
            'label'               => __('Get PHP Version & EOL Status', 'phpinfo-wp'),
            'description'         => __('Returns the running PHP version, its end-of-life date, days remaining, and an ok/warning/eol status flag.', 'phpinfo-wp'),
            'category'            => 'phpinfowp/audit',
            'input_schema'        => ['type' => 'object', 'properties' => new stdClass()],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'version' => ['type' => 'string'],
                    'minor'   => ['type' => 'string'],
                    'eol'     => ['type' => ['string', 'null']],
                    'days'    => ['type' => ['integer', 'null']],
                    'status'  => ['type' => 'string', 'enum' => ['ok', 'warning', 'eol', 'unknown']],
                ],
            ],
            'execute_callback'    => [self::class, 'do_get_php_version'],
            'permission_callback' => [self::class, 'can_read_audit'],
            'meta'                => ['show_in_rest' => true, 'annotations' => ['readonly' => true]],
        ]);

        wp_register_ability('phpinfowp/get-config-grade', [
            'label'               => __('Get Config Grade', 'phpinfo-wp'),
            'description'         => __('Returns the overall A–F config grade, numeric score, and counts of passing/warning/failing checks.', 'phpinfo-wp'),
            'category'            => 'phpinfowp/audit',
            'input_schema'        => ['type' => 'object', 'properties' => new stdClass()],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'grade'  => ['type' => 'string'],
                    'score'  => ['type' => 'integer'],
                    'passes' => ['type' => 'integer'],
                    'warns'  => ['type' => 'integer'],
                    'fails'  => ['type' => 'integer'],
                    'total'  => ['type' => 'integer'],
                ],
            ],
            'execute_callback'    => [self::class, 'do_get_config_grade'],
            'permission_callback' => [self::class, 'can_read_audit'],
            'meta'                => ['show_in_rest' => true, 'annotations' => ['readonly' => true]],
        ]);

        wp_register_ability('phpinfowp/get-config-issues', [
            'label'               => __('List Failing Config Checks', 'phpinfo-wp'),
            'description'         => __('Returns each failing or warning config check with its directive key, current value, recommended value, and severity. Pro-only — free tier returns an empty list.', 'phpinfo-wp'),
            'category'            => 'phpinfowp/audit',
            'input_schema'        => ['type' => 'object', 'properties' => new stdClass()],
            'output_schema'       => [
                'type'  => 'array',
                'items' => [
                    'type'       => 'object',
                    'properties' => [
                        'key'         => ['type' => 'string'],
                        'label'       => ['type' => 'string'],
                        'category'    => ['type' => 'string'],
                        'value'       => ['type' => 'string'],
                        'recommended' => ['type' => 'string'],
                        'status'      => ['type' => 'string', 'enum' => ['warn', 'fail']],
                        'note'        => ['type' => 'string'],
                    ],
                ],
            ],
            'execute_callback'    => [self::class, 'do_get_config_issues'],
            'permission_callback' => [self::class, 'can_read_audit'],
            'meta'                => ['show_in_rest' => true, 'annotations' => ['readonly' => true]],
        ]);

        wp_register_ability('phpinfowp/get-directive', [
            'label'               => __('Get PHP Directive Value', 'phpinfo-wp'),
            'description'         => __('Returns the current value of a named php.ini directive (e.g. memory_limit, upload_max_filesize).', 'phpinfo-wp'),
            'category'            => 'phpinfowp/audit',
            'input_schema'        => [
                'type'       => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'php.ini directive name'],
                ],
                'required'   => ['name'],
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'name'  => ['type' => 'string'],
                    'value' => ['type' => ['string', 'null']],
                    'set'   => ['type' => 'boolean'],
                ],
            ],
            'execute_callback'    => [self::class, 'do_get_directive'],
            'permission_callback' => [self::class, 'can_read_audit'],
            'meta'                => ['show_in_rest' => true, 'annotations' => ['readonly' => true]],
        ]);

        wp_register_ability('phpinfowp/list-extensions', [
            'label'               => __('List Loaded PHP Extensions', 'phpinfo-wp'),
            'description'         => __('Returns the names of all PHP extensions currently loaded on the server, sorted alphabetically.', 'phpinfo-wp'),
            'category'            => 'phpinfowp/audit',
            'input_schema'        => ['type' => 'object', 'properties' => new stdClass()],
            'output_schema'       => [
                'type'  => 'array',
                'items' => ['type' => 'string'],
            ],
            'execute_callback'    => [self::class, 'do_list_extensions'],
            'permission_callback' => [self::class, 'can_read_audit'],
            'meta'                => ['show_in_rest' => true, 'annotations' => ['readonly' => true]],
        ]);

        wp_register_ability('phpinfowp/get-audit-summary', [
            'label'               => __('Get Server Audit Summary', 'phpinfo-wp'),
            'description'         => __('Returns a high-level rollup of server health: PHP version, EOL status, config grade, and counts of issues. The single best ability to call for a "how is my server doing" check.', 'phpinfo-wp'),
            'category'            => 'phpinfowp/audit',
            'input_schema'        => ['type' => 'object', 'properties' => new stdClass()],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'php'    => ['type' => 'object'],
                    'config' => ['type' => 'object'],
                ],
            ],
            'execute_callback'    => [self::class, 'do_get_audit_summary'],
            'permission_callback' => [self::class, 'can_read_audit'],
            'meta'                => ['show_in_rest' => true, 'annotations' => ['readonly' => true]],
        ]);
    }

    public static function can_read_audit($input = null) {
        return current_user_can('manage_options');
    }

    public static function do_get_php_version($input = null): array {
        $s = Phpinfo_WP_EOL::status();
        return [
            'version' => PHP_VERSION,
            'minor'   => $s['minor'] ?? '',
            'eol'     => $s['eol']   ?? null,
            'days'    => $s['days']  ?? null,
            'status'  => $s['status']?? 'unknown',
        ];
    }

    public static function do_get_config_grade($input = null): array {
        return Phpinfo_WP_Config_Grader::summary();
    }

    public static function do_get_config_issues($input = null): array {
        $run = Phpinfo_WP_Config_Grader::run();
        if (empty($run['checks'])) return [];
        $issues = [];
        foreach ($run['checks'] as $c) {
            if ($c['status'] === 'pass') continue;
            $issues[] = [
                'key'         => (string) $c['key'],
                'label'       => (string) $c['label'],
                'category'    => (string) $c['category'],
                'value'       => (string) $c['value'],
                'recommended' => (string) $c['good'],
                'status'      => (string) $c['status'],
                'note'        => (string) $c['note'],
            ];
        }
        return $issues;
    }

    public static function do_get_directive($input): array {
        $name = is_array($input) ? ($input['name'] ?? '') : (string) $input;
        $name = preg_replace('/[^a-zA-Z0-9_.]/', '', (string) $name);
        if ($name === '') {
            return ['name' => '', 'value' => null, 'set' => false];
        }
        $raw = ini_get($name);
        return [
            'name'  => $name,
            'value' => $raw === false ? null : (string) $raw,
            'set'   => $raw !== false && $raw !== '',
        ];
    }

    public static function do_list_extensions($input = null): array {
        $exts = get_loaded_extensions();
        sort($exts, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($exts);
    }

    public static function do_get_audit_summary($input = null): array {
        return [
            'php'    => self::do_get_php_version(),
            'config' => self::do_get_config_grade(),
        ];
    }
}
