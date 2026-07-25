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
                        'preferences' => __('Preferences', 'cookie-consent-cmp'),
                        'statistics-anonymous' => __('Anonymous statistics', 'cookie-consent-cmp'),
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
                'wpConsentCategory' => 'functional',
                'wpConsentCookies' => [
                    [
                        'name' => 'klaro',
                        'expires' => __('365 days', 'cookie-consent-cmp'),
                        'function' => __('Stores the visitor’s consent choices.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                    ],
                ],
                'translations' => [
                    $lang => [
                        'title' => __('Cookie consent settings', 'cookie-consent-cmp'),
                        'description' => __('Stores the visitor’s consent choice.', 'cookie-consent-cmp'),
                    ],
                ],
            ],
            $this->build_category_service(
                'preferences',
                __('Preference storage', 'cookie-consent-cmp'),
                __('Allows services that remember visitor preferences.', 'cookie-consent-cmp'),
                $lang
            ),
            $this->build_category_service(
                'statistics-anonymous',
                __('Anonymous statistics', 'cookie-consent-cmp'),
                __('Allows anonymous, first-party audience measurement.', 'cookie-consent-cmp'),
                $lang
            ),
            $this->build_category_service(
                'statistics',
                __('Statistics', 'cookie-consent-cmp'),
                __('Allows identifiable audience measurement and analytics.', 'cookie-consent-cmp'),
                $lang
            ),
            $this->build_category_service(
                'marketing',
                __('Marketing', 'cookie-consent-cmp'),
                __('Allows advertising measurement and visitor profiling.', 'cookie-consent-cmp'),
                $lang
            ),
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
                'wpConsentCookies' => [
                    [
                        'name' => '_ga',
                        'expires' => __('2 years', 'cookie-consent-cmp'),
                        'function' => __('Distinguishes visitors for analytics reporting.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                    ],
                    [
                        'name' => '_gid',
                        'expires' => __('24 hours', 'cookie-consent-cmp'),
                        'function' => __('Distinguishes visitors for daily analytics reporting.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                    ],
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
                'wpConsentCookies' => [
                    [
                        'name' => '_clck',
                        'expires' => __('1 year', 'cookie-consent-cmp'),
                        'function' => __('Persists the Clarity visitor identifier and preferences.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                    ],
                    [
                        'name' => '_clsk',
                        'expires' => __('1 day', 'cookie-consent-cmp'),
                        'function' => __('Groups Clarity page views into a recording session.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                    ],
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
                'wpConsentCookies' => [
                    [
                        'name' => '_hjClosedSurveyInvites',
                        'expires' => __('1 year', 'cookie-consent-cmp'),
                        'function' => __('Prevents a dismissed Hotjar survey invitation from reappearing.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                    ],
                    [
                        'name' => '_hjDonePolls',
                        'expires' => __('1 year', 'cookie-consent-cmp'),
                        'function' => __('Prevents a completed Hotjar poll from reappearing.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                    ],
                    [
                        'name' => '_hjMinimizedPolls',
                        'expires' => __('1 year', 'cookie-consent-cmp'),
                        'function' => __('Keeps a minimized Hotjar poll minimized.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                    ],
                    [
                        'name' => '_hjShownFeedbackMessage',
                        'expires' => __('1 day', 'cookie-consent-cmp'),
                        'function' => __('Prevents repeated display of Hotjar feedback messaging.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                    ],
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
                'wpConsentCookies' => [
                    [
                        'name' => '_fbp',
                        'expires' => __('90 days', 'cookie-consent-cmp'),
                        'function' => __('Identifies browsers for Meta advertising measurement.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                    ],
                    [
                        'name' => '_fbc',
                        'expires' => __('90 days', 'cookie-consent-cmp'),
                        'function' => __('Stores the Meta advertising click identifier.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                    ],
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
                'wpConsentCookies' => [
                    [
                        'name' => 'li_fat_id',
                        'expires' => __('30 days', 'cookie-consent-cmp'),
                        'function' => __('Stores the LinkedIn advertising click identifier.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                    ],
                    [
                        'name' => 'li_giant',
                        'expires' => __('7 days', 'cookie-consent-cmp'),
                        'function' => __('Supports LinkedIn conversion attribution.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                    ],
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
                'wpConsentCookies' => [
                    [
                        'name' => 'VISITOR_INFO1_LIVE',
                        'expires' => __('180 days', 'cookie-consent-cmp'),
                        'function' => __('Measures bandwidth and player interface selection.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                        'domain' => 'https://www.youtube.com',
                    ],
                    [
                        'name' => 'VISITOR_PRIVACY_METADATA',
                        'expires' => __('180 days', 'cookie-consent-cmp'),
                        'function' => __('Stores the visitor’s YouTube privacy state.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                        'domain' => 'https://www.youtube.com',
                    ],
                    [
                        'name' => 'YSC',
                        'expires' => __('Session', 'cookie-consent-cmp'),
                        'function' => __('Maintains YouTube video-view session data.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                        'domain' => 'https://www.youtube.com',
                    ],
                    [
                        'name' => 'PREF',
                        'expires' => __('8 months', 'cookie-consent-cmp'),
                        'function' => __('Stores YouTube playback and display preferences.', 'cookie-consent-cmp'),
                        'type' => 'HTTP',
                        'domain' => 'https://www.youtube.com',
                    ],
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

    /**
     * @return array<string, mixed>
     */
    private function build_category_service(
        string $category,
        string $title,
        string $description,
        string $lang
    ): array {
        return [
            'name' => 'wp-consent-category-' . $category,
            'title' => $title,
            'purposes' => [$category],
            'default' => false,
            'required' => false,
            'optOut' => false,
            'onlyOnce' => true,
            'cookies' => [],
            'wpConsentCategory' => $category,
            'translations' => [
                $lang => [
                    'title' => $title,
                    'description' => $description,
                ],
            ],
        ];
    }
}
