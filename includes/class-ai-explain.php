<?php
defined('ABSPATH') or die('Unauthorized Access');

// WP 7.0 AI Client integration. Adds an "Explain with AI" capability that
// turns a php.ini directive name, a failing config check, or a security
// header into a short plain-English explanation. Credentials are managed
// entirely by the core Connectors API — we never touch API keys.
//
// Falls back silently on cores without wp_ai_client_prompt() so the rest
// of the plugin keeps working on WP < 7.0.

class Phpinfo_WP_AI_Explain {

    const NONCE  = 'phpinfowp_ai_explain';
    const ACTION = 'phpinfowp_ai_explain';

    public static function register(): void {
        if (!self::available()) return;
        add_action('wp_ajax_' . self::ACTION, [self::class, 'ajax_explain']);
    }

    public static function available(): bool {
        return function_exists('wp_ai_client_prompt');
    }

    // Localized data for the JS handler. Returns an empty array when the
    // AI Client isn't available so the JS can hide the button.
    public static function js_config(): array {
        return [
            'available' => self::available(),
            'action'    => self::ACTION,
            'nonce'     => wp_create_nonce(self::NONCE),
            'ajax_url'  => admin_url('admin-ajax.php'),
            'i18n'      => [
                'explain'  => __('Explain with AI', 'phpinfo-wp'),
                'thinking' => __('Asking AI…', 'phpinfo-wp'),
                'error'    => __('AI request failed.', 'phpinfo-wp'),
            ],
        ];
    }

    public static function ajax_explain(): void {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Forbidden.', 'phpinfo-wp')], 403);
        }
        if (!self::available()) {
            wp_send_json_error(['message' => __('AI Client is not available. Requires WordPress 7.0+ with a configured AI connector.', 'phpinfo-wp')], 501);
        }

        $topic   = sanitize_key($_POST['topic']   ?? '');
        $context = sanitize_text_field(wp_unslash($_POST['context'] ?? ''));
        $value   = sanitize_text_field(wp_unslash($_POST['value']   ?? ''));

        $prompt = self::build_prompt($topic, $context, $value);
        if ($prompt === '') {
            wp_send_json_error(['message' => __('Unknown topic.', 'phpinfo-wp')], 400);
        }

        $result = wp_ai_client_prompt($prompt)->generate_text();
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 502);
        }
        wp_send_json_success(['text' => (string) $result]);
    }

    // Prompts are intentionally short, factual, and bounded — long answers
    // waste the user's connector budget and clutter the inline UI.
    private static function build_prompt(string $topic, string $context, string $value): string {
        switch ($topic) {
            case 'directive':
                return sprintf(
                    'Explain the PHP directive `%s` in the context of a WordPress site. Cover: what it does, recommended value, and the typical symptom when it is misconfigured. Use 3–4 short sentences. Plain text, no markdown.',
                    $context
                );
            case 'config_issue':
                return sprintf(
                    'A WordPress site has the PHP directive `%s` currently set to `%s`. Explain in 3–4 short sentences why this value is below the recommended setting, the user-visible symptom (e.g. uploads failing, builder breaking), and the safest way to fix it on a shared host. Plain text, no markdown.',
                    $context, $value !== '' ? $value : '(not set)'
                );
            case 'header':
                return sprintf(
                    'Explain the HTTP security header `%s` for a WordPress site. Cover: what it protects against, a sensible recommended value, and the realistic risk of leaving it off. Use 3–4 short sentences. Plain text, no markdown.',
                    $context
                );
            case 'extension':
                return sprintf(
                    'Explain the PHP extension `%s` in the context of a WordPress site. Cover: what it provides, common plugins that need it, and the symptom when it is missing. Use 3–4 short sentences. Plain text, no markdown.',
                    $context
                );
            default:
                return '';
        }
    }
}
