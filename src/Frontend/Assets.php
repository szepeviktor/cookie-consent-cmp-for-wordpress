<?php

declare(strict_types=1);

namespace SzepeViktor\CookieConsentCmp\Frontend;

use SzepeViktor\CookieConsentCmp\Config;
use SzepeViktor\CookieConsentCmp\Options;

final class Assets
{
    private const MODAL_STYLE_STYLESHEETS = [
        Options::MODAL_STYLE_VIKTOR_DEFAULT => 'viktor-default.css',
        Options::MODAL_STYLE_LIGHT => 'light.css',
        Options::MODAL_STYLE_DARK => 'dark.css',
        Options::MODAL_STYLE_TWENTY_TWENTY_FIVE => 'twenty-twenty-five.css',
    ];

    private Options $options;

    private ConsentApiBridge $consent_api_bridge;

    public function __construct(Options $options, ConsentApiBridge $consent_api_bridge)
    {
        $this->options = $options;
        $this->consent_api_bridge = $consent_api_bridge;
    }

    public function register(): void
    {
        $lang = strtolower(substr(determine_locale(), 0, 2));
        $this->consent_api_bridge->register_services($this->build_services($lang));

        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 100);
        add_filter('script_loader_tag', [$this, 'filter_bootstrap_tag'], 10, 3);
    }

    public function enqueue(): void
    {
        $options = $this->options->all();
        $modalStyle = (string) $options['modal_style'];
        $klaroConfig = $this->build_klaro_config();

        wp_enqueue_style(
            'cookie-consent-cmp-klaro',
            plugins_url('assets/css/klaro.css', Config::get('filePath')),
            [],
            Config::get('version')
        );

        wp_enqueue_style(
            'cookie-consent-cmp-components',
            plugins_url('assets/css/components.css', Config::get('filePath')),
            ['cookie-consent-cmp-klaro'],
            Config::get('version')
        );

        if (isset(self::MODAL_STYLE_STYLESHEETS[$modalStyle])) {
            wp_enqueue_style(
                'cookie-consent-cmp-modal-style',
                plugins_url(
                    'assets/css/modal-styles/' . self::MODAL_STYLE_STYLESHEETS[$modalStyle],
                    Config::get('filePath')
                ),
                ['cookie-consent-cmp-components'],
                Config::get('version')
            );
        }

        wp_enqueue_script(
            'cookie-consent-cmp-bootstrap',
            plugins_url('assets/js/cmp-bootstrap.js', Config::get('filePath')),
            [],
            Config::get('version'),
            false
        );

        wp_enqueue_script(
            'cookie-consent-cmp-klaro',
            plugins_url('assets/js/klaro.js', Config::get('filePath')),
            [],
            Config::get('version'),
            false
        );

        wp_script_add_data('cookie-consent-cmp-klaro', 'defer', true);

        wp_add_inline_script(
            'cookie-consent-cmp-klaro',
            'window.klaroConfig = ' . wp_json_encode($klaroConfig) . ';',
            'before'
        );

        if ($this->consent_api_bridge->is_api_available()) {
            wp_enqueue_script(
                'cookie-consent-cmp-consent-api-bridge',
                plugins_url('assets/js/wp-consent-api-bridge.js', Config::get('filePath')),
                ['cookie-consent-cmp-klaro', 'wp-consent-api'],
                Config::get('version'),
                true
            );
        }
    }

    public function filter_bootstrap_tag(string $tag, string $handle, string $src): string
    {
        $attributes = [];
        $options = $this->options->all();

        if ($handle !== 'cookie-consent-cmp-bootstrap') {
            return $tag;
        }

        if ($options['gtm_id'] !== '') {
            $attributes['data-gtm-id'] = (string) $options['gtm_id'];
        }

        if ($options['clarity_project_id'] !== '') {
            $attributes['data-clarity-project-id'] = (string) $options['clarity_project_id'];
        }

        if ($options['hotjar_id'] !== '') {
            $attributes['data-hotjar-id'] = (string) $options['hotjar_id'];
            $attributes['data-hotjar-version'] = (string) $options['hotjar_version'];
        }

        if ($options['meta_pixel_id'] !== '') {
            $attributes['data-meta-pixel-id'] = (string) $options['meta_pixel_id'];
        }

        if ($options['linkedin_partner_id'] !== '') {
            $attributes['data-linkedin-partner-id'] = (string) $options['linkedin_partner_id'];
        }

        if (! empty($options['enable_youtube'])) {
            $attributes['data-youtube-service'] = 'youtube';
        }

        if (! empty($options['enable_floating'])) {
            $attributes['data-floating'] = 'true';
        }

        if (empty($attributes)) {
            return $tag;
        }

        $html = '';

        foreach ($attributes as $name => $value) {
            $html .= sprintf(' %s="%s"', esc_attr($name), esc_attr($value));
        }

        return str_replace('<script ', '<script' . $html . ' ', $tag);
    }

    /**
     * @return array<string, mixed>
     */
    private function build_klaro_config(): array
    {
        $options = $this->options->all();
        $lang = strtolower(substr(determine_locale(), 0, 2));

        return [
            'version' => 1,
            'elementID' => 'klaro',
            'storageMethod' => 'cookie',
            'storageName' => 'klaro',
            'cookieExpiresAfterDays' => 365,
            'default' => false,
            'mustConsent' => false,
            'acceptAll' => true,
            'hideDeclineAll' => false,
            'hideLearnMore' => false,
            'noticeAsModal' => false,
            'showNoticeTitle' => true,
            'htmlTexts' => true,
            'embedded' => false,
            'groupByPurpose' => true,
            'lang' => $lang,
            'translations' => [
                $lang => [
                    'consentNotice' => [
                        'title' => (string) $options['notice_title'],
                        'description' => (string) $options['notice_description'],
                    ],
                    'consentModal' => [
                        'title' => (string) $options['modal_title'],
                        'description' => (string) $options['modal_description'],
                    ],
                    'purposes' => [
                        'functional' => __('Functional', 'cookie-consent-cmp'),
                        'statistics' => __('Statistics', 'cookie-consent-cmp'),
                        'marketing' => __('Marketing', 'cookie-consent-cmp'),
                    ],
                    'purposeItem' => [
                        'service' => __('service', 'cookie-consent-cmp'),
                        'services' => __('services', 'cookie-consent-cmp'),
                    ],
                    'ok' => __('OK', 'cookie-consent-cmp'),
                    'save' => __('Save', 'cookie-consent-cmp'),
                    'acceptAll' => __('Accept all', 'cookie-consent-cmp'),
                    'declineAll' => __('Decline all', 'cookie-consent-cmp'),
                    'decline' => __('Decline', 'cookie-consent-cmp'),
                    'close' => __('Close', 'cookie-consent-cmp'),
                    'service' => [
                        'disableAll' => [
                            'title' => __('Enable or disable all services', 'cookie-consent-cmp'),
                            'description' => __('Use this switch to change all optional services at once.', 'cookie-consent-cmp'),
                        ],
                        'optOut' => [
                            'title' => __('(opt-out)', 'cookie-consent-cmp'),
                            'description' => __('This service loads by default, but can be disabled later.', 'cookie-consent-cmp'),
                        ],
                        'required' => [
                            'title' => __('(required)', 'cookie-consent-cmp'),
                            'description' => __('This service is required for the site to function.', 'cookie-consent-cmp'),
                        ],
                        'purposes' => __('Purposes', 'cookie-consent-cmp'),
                        'purpose' => __('Purpose', 'cookie-consent-cmp'),
                    ],
                ],
            ],
            'services' => $this->build_services($lang),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function build_services(string $lang): array
    {
        $options = $this->options->all();
        $services = [
            [
                'name' => 'klaro',
                'title' => __('Cookie consent settings', 'cookie-consent-cmp'),
                'purposes' => ['functional'],
                'default' => true,
                'required' => true,
                'optOut' => false,
                'onlyOnce' => true,
                'cookies' => ['klaro'],
                'translations' => [
                    $lang => [
                        'title' => __('Cookie consent settings', 'cookie-consent-cmp'),
                        'description' => __('Stores the visitor’s consent choice.', 'cookie-consent-cmp'),
                    ],
                ],
            ],
        ];

        if ($options['gtm_id'] !== '') {
            $services[] = [
                'name' => 'google-tag-manager',
                'title' => __('Google Tag Manager', 'cookie-consent-cmp'),
                'purposes' => ['statistics'],
                'default' => false,
                'required' => false,
                'optOut' => false,
                'onlyOnce' => true,
                'cookies' => [
                    '_ga',
                    '^_ga_.*',
                    '_gid',
                    '^_gat.*',
                ],
                'translations' => [
                    $lang => [
                        'title' => __('Google Tag Manager', 'cookie-consent-cmp'),
                        'description' => __('Loads analytics tags managed through Google Tag Manager.', 'cookie-consent-cmp'),
                    ],
                ],
            ];
        }

        if ($options['clarity_project_id'] !== '') {
            $services[] = [
                'name' => 'microsoft-clarity',
                'title' => __('Microsoft Clarity', 'cookie-consent-cmp'),
                'purposes' => ['statistics'],
                'default' => false,
                'required' => false,
                'optOut' => false,
                'onlyOnce' => true,
                'cookies' => [
                    '_clck',
                    '_clsk',
                ],
                'translations' => [
                    $lang => [
                        'title' => __('Microsoft Clarity', 'cookie-consent-cmp'),
                        'description' => __('Measures how visitors use the site through session analytics.', 'cookie-consent-cmp'),
                    ],
                ],
            ];
        }

        if ($options['hotjar_id'] !== '') {
            $services[] = [
                'name' => 'hotjar',
                'title' => __('Hotjar', 'cookie-consent-cmp'),
                'purposes' => ['statistics'],
                'default' => false,
                'required' => false,
                'optOut' => false,
                'onlyOnce' => true,
                'cookies' => [
                    '_hjCookieTest',
                    '_hjLocalStorageTest',
                    '_hjSessionStorageTest',
                    '_hjTLDTest',
                    '^_hjSessionUser_.*',
                    '^_hjSession_.*',
                    '_hjClosedSurveyInvites',
                    '_hjDonePolls',
                    '_hjMinimizedPolls',
                    '_hjShownFeedbackMessage',
                    '_hjSessionTooLarge',
                    '_hjSessionRejected',
                    '_hjHasCachedUserAttributes',
                    '_hjUserAttributesHash',
                ],
                'translations' => [
                    $lang => [
                        'title' => __('Hotjar', 'cookie-consent-cmp'),
                        'description' => __('Measures visitor behavior and collects usability feedback.', 'cookie-consent-cmp'),
                    ],
                ],
            ];
        }

        if ($options['meta_pixel_id'] !== '') {
            $services[] = [
                'name' => 'meta-pixel',
                'title' => __('Meta Pixel', 'cookie-consent-cmp'),
                'purposes' => ['marketing'],
                'default' => false,
                'required' => false,
                'optOut' => false,
                'onlyOnce' => true,
                'cookies' => [
                    '_fbp',
                    '_fbc',
                ],
                'translations' => [
                    $lang => [
                        'title' => __('Meta Pixel', 'cookie-consent-cmp'),
                        'description' => __('Measures advertising performance and visitor actions for Meta.', 'cookie-consent-cmp'),
                    ],
                ],
            ];
        }

        if ($options['linkedin_partner_id'] !== '') {
            $services[] = [
                'name' => 'linkedin-insight-tag',
                'title' => __('LinkedIn Insight Tag', 'cookie-consent-cmp'),
                'purposes' => ['marketing'],
                'default' => false,
                'required' => false,
                'optOut' => false,
                'onlyOnce' => true,
                'cookies' => [
                    'li_fat_id',
                    'li_giant',
                ],
                'translations' => [
                    $lang => [
                        'title' => __('LinkedIn Insight Tag', 'cookie-consent-cmp'),
                        'description' => __('Measures LinkedIn campaign performance and website conversions.', 'cookie-consent-cmp'),
                    ],
                ],
            ];
        }

        if (! empty($options['enable_youtube'])) {
            $services[] = [
                'name' => 'youtube',
                'title' => __('YouTube', 'cookie-consent-cmp'),
                'purposes' => ['marketing'],
                'default' => false,
                'required' => false,
                'optOut' => false,
                'onlyOnce' => true,
                'cookies' => [
                    'VISITOR_INFO1_LIVE',
                    'VISITOR_PRIVACY_METADATA',
                    'YSC',
                    'PREF',
                ],
                'translations' => [
                    $lang => [
                        'title' => __('YouTube', 'cookie-consent-cmp'),
                        'description' => __('Loads embedded videos provided by YouTube.', 'cookie-consent-cmp'),
                    ],
                ],
            ];
        }

        return $services;
    }
}
