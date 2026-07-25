<?php

declare(strict_types=1);

namespace SzepeViktor\CookieConsentCmp;

final class Options
{
    public const OPTION_NAME = 'cookie_consent_cmp';
    public const MODAL_STYLE_KLARO_DEFAULT = 'klaro-default';
    public const MODAL_STYLE_VIKTOR_DEFAULT = 'viktor-default';
    public const MODAL_STYLE_LIGHT = 'light';
    public const MODAL_STYLE_DARK = 'dark';
    public const MODAL_STYLE_TWENTY_TWENTY_FIVE = 'twenty-twenty-five';

    private const MODAL_STYLES = [
        self::MODAL_STYLE_KLARO_DEFAULT,
        self::MODAL_STYLE_VIKTOR_DEFAULT,
        self::MODAL_STYLE_LIGHT,
        self::MODAL_STYLE_DARK,
        self::MODAL_STYLE_TWENTY_TWENTY_FIVE,
    ];

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = get_option(self::OPTION_NAME, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        return wp_parse_args($stored, $this->defaults());
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'notice_title' => __('Privacy settings', 'cookie-consent-cmp'),
            'notice_description' => __('We use cookies for required functionality, statistics, and marketing. You can update your choices at any time.', 'cookie-consent-cmp'),
            'modal_title' => __('Privacy preferences', 'cookie-consent-cmp'),
            'modal_description' => __('Choose which categories of services may load on this site.', 'cookie-consent-cmp'),
            'modal_style' => self::MODAL_STYLE_KLARO_DEFAULT,
            'gtm_id' => '',
            'clarity_project_id' => '',
            'hotjar_id' => '',
            'hotjar_version' => 6,
            'meta_pixel_id' => '',
            'linkedin_partner_id' => '',
            'enable_youtube' => 0,
            'enable_floating' => 1,
        ];
    }

    /**
     * @param mixed $input
     * @return array<string, mixed>
     */
    public function sanitize($input): array
    {
        $defaults = $this->defaults();
        $input = is_array($input) ? $input : [];

        return [
            'notice_title' => sanitize_text_field($input['notice_title'] ?? $defaults['notice_title']),
            'notice_description' => sanitize_textarea_field($input['notice_description'] ?? $defaults['notice_description']),
            'modal_title' => sanitize_text_field($input['modal_title'] ?? $defaults['modal_title']),
            'modal_description' => sanitize_textarea_field($input['modal_description'] ?? $defaults['modal_description']),
            'modal_style' => $this->sanitizeModalStyle($input['modal_style'] ?? $defaults['modal_style']),
            'gtm_id' => sanitize_text_field($input['gtm_id'] ?? ''),
            'clarity_project_id' => sanitize_text_field($input['clarity_project_id'] ?? ''),
            'hotjar_id' => sanitize_text_field($input['hotjar_id'] ?? ''),
            'hotjar_version' => max(1, absint($input['hotjar_version'] ?? $defaults['hotjar_version'])),
            'meta_pixel_id' => sanitize_text_field($input['meta_pixel_id'] ?? ''),
            'linkedin_partner_id' => sanitize_text_field($input['linkedin_partner_id'] ?? ''),
            'enable_youtube' => empty($input['enable_youtube']) ? 0 : 1,
            'enable_floating' => empty($input['enable_floating']) ? 0 : 1,
        ];
    }

    /**
     * @param mixed $value
     */
    private function sanitizeModalStyle($value): string
    {
        if (! is_string($value) || ! in_array($value, self::MODAL_STYLES, true)) {
            return self::MODAL_STYLE_KLARO_DEFAULT;
        }

        return $value;
    }
}
