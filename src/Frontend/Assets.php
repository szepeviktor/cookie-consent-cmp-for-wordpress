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

        $this->enqueueStyles($modalStyle);
        $this->enqueueScripts($klaroConfig);
    }

    private function enqueueStyles(string $modalStyle): void
    {
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
                    sprintf(
                        'assets/css/modal-styles/%s',
                        self::MODAL_STYLE_STYLESHEETS[$modalStyle]
                    ),
                    Config::get('filePath')
                ),
                ['cookie-consent-cmp-components'],
                Config::get('version')
            );
        }
    }

    /**
     * @param array<string, mixed> $klaroConfig
     */
    private function enqueueScripts(array $klaroConfig): void
    {
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
            sprintf('window.klaroConfig = %s;', wp_json_encode($klaroConfig)),
            'before'
        );

        if (! $this->consent_api_bridge->is_api_available()) {
            return;
        }

        wp_enqueue_script(
            'cookie-consent-cmp-consent-api-bridge',
            plugins_url('assets/js/wp-consent-api-bridge.js', Config::get('filePath')),
            ['cookie-consent-cmp-klaro', 'wp-consent-api'],
            Config::get('version'),
            true
        );
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

        if ((bool) $options['enable_youtube']) {
            $attributes['data-youtube-service'] = 'youtube';
        }

        if ((bool) $options['enable_floating']) {
            $attributes['data-floating'] = 'true';
        }

        if ($attributes === []) {
            return $tag;
        }

        $htmlAttributes = [];

        foreach ($attributes as $name => $value) {
            $htmlAttributes[] = sprintf(' %s="%s"', esc_attr($name), esc_attr($value));
        }

        return str_replace(
            '<script ',
            sprintf('<script%s ', implode('', $htmlAttributes)),
            $tag
        );
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
                $lang => $this->buildTranslations($options),
            ],
            'services' => $this->build_services($lang),
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildTranslations(array $options): array
    {
        return [
            'consentNotice' => [
                'title' => (string) $options['notice_title'],
                'description' => (string) $options['notice_description'],
            ],
            'consentModal' => [
                'title' => (string) $options['modal_title'],
                'description' => (string) $options['modal_description'],
            ],
            'purposes' => $this->buildPurposeTranslations(),
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
            'service' => $this->buildServiceTranslations(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildPurposeTranslations(): array
    {
        return [
            'functional' => __('Functional', 'cookie-consent-cmp'),
            'preferences' => __('Preferences', 'cookie-consent-cmp'),
            'statistics-anonymous' => __('Anonymous statistics', 'cookie-consent-cmp'),
            'statistics' => __('Statistics', 'cookie-consent-cmp'),
            'marketing' => __('Marketing', 'cookie-consent-cmp'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildServiceTranslations(): array
    {
        return [
            'disableAll' => [
                'title' => __('Enable or disable all services', 'cookie-consent-cmp'),
                'description' => __(
                    'Use this switch to change all optional services at once.',
                    'cookie-consent-cmp'
                ),
            ],
            'optOut' => [
                'title' => __('(opt-out)', 'cookie-consent-cmp'),
                'description' => __(
                    'This service loads by default, but can be disabled later.',
                    'cookie-consent-cmp'
                ),
            ],
            'required' => [
                'title' => __('(required)', 'cookie-consent-cmp'),
                'description' => __(
                    'This service is required for the site to function.',
                    'cookie-consent-cmp'
                ),
            ],
            'purposes' => __('Purposes', 'cookie-consent-cmp'),
            'purpose' => __('Purpose', 'cookie-consent-cmp'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function build_services(string $lang): array
    {
        $options = $this->options->all();
        $services = $this->buildCoreServices($lang);

        if ($options['gtm_id'] !== '') {
            $services[] = $this->buildGoogleTagManagerService($lang);
        }

        if ($options['clarity_project_id'] !== '') {
            $services[] = $this->buildMicrosoftClarityService($lang);
        }

        if ($options['hotjar_id'] !== '') {
            $services[] = $this->buildHotjarService($lang);
        }

        if ($options['meta_pixel_id'] !== '') {
            $services[] = $this->buildMetaPixelService($lang);
        }

        if ($options['linkedin_partner_id'] !== '') {
            $services[] = $this->buildLinkedInService($lang);
        }

        if ((bool) $options['enable_polylang']) {
            $polylangService = $this->buildPolylangService($lang);

            if ($polylangService !== null) {
                $services[] = $polylangService;
            }
        }

        if ((bool) $options['enable_woocommerce']) {
            $services[] = $this->buildWooCommerceFunctionalService($lang);
            $services[] = $this->buildWooCommerceAttributionService($lang);
        }

        if ((bool) $options['enable_klaviyo']) {
            $services[] = $this->buildKlaviyoService($lang);
        }

        if ((bool) $options['enable_youtube']) {
            $services[] = $this->buildYouTubeService($lang);
        }

        return $services;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCoreServices(string $lang): array
    {
        return [
            $this->buildKlaroService($lang),
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
    }

    /**
     * @return array<string, mixed>
     */
    private function buildKlaroService(string $lang): array
    {
        return [
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
                $this->buildCookieInfo(
                    'klaro',
                    __('365 days', 'cookie-consent-cmp'),
                    __('Stores the visitor’s consent choices.', 'cookie-consent-cmp')
                ),
            ],
            'translations' => [
                $lang => [
                    'title' => __('Cookie consent settings', 'cookie-consent-cmp'),
                    'description' => __('Stores the visitor’s consent choice.', 'cookie-consent-cmp'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGoogleTagManagerService(string $lang): array
    {
        return $this->buildOptionalService(
            'google-tag-manager',
            __('Google Tag Manager', 'cookie-consent-cmp'),
            'statistics',
            ['_ga', '^_ga_.*', '_gid', '^_gat.*'],
            [
                $this->buildCookieInfo(
                    '_ga',
                    __('2 years', 'cookie-consent-cmp'),
                    __('Distinguishes visitors for analytics reporting.', 'cookie-consent-cmp')
                ),
                $this->buildCookieInfo(
                    '_gid',
                    __('24 hours', 'cookie-consent-cmp'),
                    __('Distinguishes visitors for daily analytics reporting.', 'cookie-consent-cmp')
                ),
            ],
            __('Loads analytics tags managed through Google Tag Manager.', 'cookie-consent-cmp'),
            $lang
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMicrosoftClarityService(string $lang): array
    {
        return $this->buildOptionalService(
            'microsoft-clarity',
            __('Microsoft Clarity', 'cookie-consent-cmp'),
            'statistics',
            ['_clck', '_clsk'],
            [
                $this->buildCookieInfo(
                    '_clck',
                    __('1 year', 'cookie-consent-cmp'),
                    __('Persists the Clarity visitor identifier and preferences.', 'cookie-consent-cmp')
                ),
                $this->buildCookieInfo(
                    '_clsk',
                    __('1 day', 'cookie-consent-cmp'),
                    __('Groups Clarity page views into a recording session.', 'cookie-consent-cmp')
                ),
            ],
            __('Measures how visitors use the site through session analytics.', 'cookie-consent-cmp'),
            $lang
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHotjarService(string $lang): array
    {
        return $this->buildOptionalService(
            'hotjar',
            __('Hotjar', 'cookie-consent-cmp'),
            'statistics',
            $this->buildHotjarCookies(),
            $this->buildHotjarCookieInfo(),
            __('Measures visitor behavior and collects usability feedback.', 'cookie-consent-cmp'),
            $lang
        );
    }

    /**
     * @return array<int, string>
     */
    private function buildHotjarCookies(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildHotjarCookieInfo(): array
    {
        return [
            $this->buildCookieInfo(
                '_hjClosedSurveyInvites',
                __('1 year', 'cookie-consent-cmp'),
                __('Prevents a dismissed Hotjar survey invitation from reappearing.', 'cookie-consent-cmp')
            ),
            $this->buildCookieInfo(
                '_hjDonePolls',
                __('1 year', 'cookie-consent-cmp'),
                __('Prevents a completed Hotjar poll from reappearing.', 'cookie-consent-cmp')
            ),
            $this->buildCookieInfo(
                '_hjMinimizedPolls',
                __('1 year', 'cookie-consent-cmp'),
                __('Keeps a minimized Hotjar poll minimized.', 'cookie-consent-cmp')
            ),
            $this->buildCookieInfo(
                '_hjShownFeedbackMessage',
                __('1 day', 'cookie-consent-cmp'),
                __('Prevents repeated display of Hotjar feedback messaging.', 'cookie-consent-cmp')
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMetaPixelService(string $lang): array
    {
        return $this->buildOptionalService(
            'meta-pixel',
            __('Meta Pixel', 'cookie-consent-cmp'),
            'marketing',
            ['_fbp', '_fbc'],
            [
                $this->buildCookieInfo(
                    '_fbp',
                    __('90 days', 'cookie-consent-cmp'),
                    __('Identifies browsers for Meta advertising measurement.', 'cookie-consent-cmp')
                ),
                $this->buildCookieInfo(
                    '_fbc',
                    __('90 days', 'cookie-consent-cmp'),
                    __('Stores the Meta advertising click identifier.', 'cookie-consent-cmp')
                ),
            ],
            __('Measures advertising performance and visitor actions for Meta.', 'cookie-consent-cmp'),
            $lang
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLinkedInService(string $lang): array
    {
        return $this->buildOptionalService(
            'linkedin-insight-tag',
            __('LinkedIn Insight Tag', 'cookie-consent-cmp'),
            'marketing',
            ['li_fat_id', 'li_giant'],
            [
                $this->buildCookieInfo(
                    'li_fat_id',
                    __('30 days', 'cookie-consent-cmp'),
                    __('Stores the LinkedIn advertising click identifier.', 'cookie-consent-cmp')
                ),
                $this->buildCookieInfo(
                    'li_giant',
                    __('7 days', 'cookie-consent-cmp'),
                    __('Supports LinkedIn conversion attribution.', 'cookie-consent-cmp')
                ),
            ],
            __('Measures LinkedIn campaign performance and website conversions.', 'cookie-consent-cmp'),
            $lang
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPolylangService(string $lang): ?array
    {
        $cookieName = defined('PLL_COOKIE') ? constant('PLL_COOKIE') : 'pll_language';

        if (! is_string($cookieName) || $cookieName === '') {
            return null;
        }

        return [
            'name' => 'polylang',
            'title' => __('Polylang', 'cookie-consent-cmp'),
            'purposes' => ['preferences'],
            'default' => true,
            'required' => true,
            'optOut' => false,
            'onlyOnce' => true,
            'cookies' => [$cookieName],
            'wpConsentCategory' => 'preferences',
            'wpConsentCookies' => [
                $this->buildCookieInfo(
                    $cookieName,
                    __('1 year', 'cookie-consent-cmp'),
                    __(
                        'Stores the visitor’s last browsed language for Polylang and Polylang for WooCommerce.',
                        'cookie-consent-cmp'
                    )
                ),
            ],
            'translations' => [
                $lang => [
                    'title' => __('Polylang', 'cookie-consent-cmp'),
                    'description' => __(
                        'Remembers the selected language for multilingual content and translated WooCommerce flows.',
                        'cookie-consent-cmp'
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWooCommerceFunctionalService(string $lang): array
    {
        return [
            'name' => 'woocommerce',
            'title' => __('WooCommerce', 'cookie-consent-cmp'),
            'purposes' => ['functional'],
            'default' => true,
            'required' => true,
            'optOut' => false,
            'onlyOnce' => true,
            'cookies' => $this->buildWooCommerceFunctionalCookies(),
            'wpConsentCategory' => 'functional',
            'wpConsentCookies' => $this->buildWooCommerceFunctionalCookieInfo(),
            'translations' => [
                $lang => [
                    'title' => __('WooCommerce', 'cookie-consent-cmp'),
                    'description' => __(
                        'Keeps the shopping cart, checkout, customer session, and store notices working.',
                        'cookie-consent-cmp'
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildWooCommerceFunctionalCookies(): array
    {
        return [
            'woocommerce_cart_hash',
            'woocommerce_items_in_cart',
            '^wp_woocommerce_session_.*',
            '^wc_cart_hash_.*',
            '^wc_fragments_.*',
            'wc_cart_created',
            'woocommerce_recently_viewed',
            '^store_notice.*',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildWooCommerceFunctionalCookieInfo(): array
    {
        return [
            $this->buildCookieInfo(
                'woocommerce_cart_hash',
                __('Session', 'cookie-consent-cmp'),
                __('Helps WooCommerce detect cart changes.', 'cookie-consent-cmp')
            ),
            $this->buildCookieInfo(
                'woocommerce_items_in_cart',
                __('Session', 'cookie-consent-cmp'),
                __('Helps WooCommerce keep cart data synchronized.', 'cookie-consent-cmp')
            ),
            $this->buildCookieInfo(
                'wp_woocommerce_session_*',
                __('2 days', 'cookie-consent-cmp'),
                __('Stores a unique customer session identifier for cart and checkout data.', 'cookie-consent-cmp')
            ),
            $this->buildCookieInfo(
                'woocommerce_recently_viewed',
                __('Session', 'cookie-consent-cmp'),
                __('Stores products viewed by the visitor.', 'cookie-consent-cmp')
            ),
            $this->buildCookieInfo(
                'store_notice*',
                __('Session', 'cookie-consent-cmp'),
                __('Remembers dismissed WooCommerce store notices.', 'cookie-consent-cmp')
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWooCommerceAttributionService(string $lang): array
    {
        return $this->buildOptionalService(
            'woocommerce-attribution',
            __('WooCommerce source attribution', 'cookie-consent-cmp'),
            'statistics',
            $this->buildWooCommerceAttributionCookies(),
            $this->buildWooCommerceAttributionCookieInfo(),
            __(
                'Stores first-party source attribution data for WooCommerce order reporting.',
                'cookie-consent-cmp'
            ),
            $lang
        );
    }

    /**
     * @return array<int, string>
     */
    private function buildWooCommerceAttributionCookies(): array
    {
        return [
            'sbjs_current',
            'sbjs_current_add',
            'sbjs_first',
            'sbjs_first_add',
            'sbjs_migrations',
            'sbjs_session',
            'sbjs_udata',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildWooCommerceAttributionCookieInfo(): array
    {
        return [
            $this->buildCookieInfo(
                'sbjs_current',
                __('6 months', 'cookie-consent-cmp'),
                __(
                    'Stores the visitor’s current traffic source for WooCommerce order attribution.',
                    'cookie-consent-cmp'
                )
            ),
            $this->buildCookieInfo(
                'sbjs_current_add',
                __('6 months', 'cookie-consent-cmp'),
                __(
                    'Stores additional current traffic source details for WooCommerce order attribution.',
                    'cookie-consent-cmp'
                )
            ),
            $this->buildCookieInfo(
                'sbjs_first',
                __('6 months', 'cookie-consent-cmp'),
                __(
                    'Stores the visitor’s first traffic source for WooCommerce order attribution.',
                    'cookie-consent-cmp'
                )
            ),
            $this->buildCookieInfo(
                'sbjs_first_add',
                __('6 months', 'cookie-consent-cmp'),
                __(
                    'Stores additional first traffic source details for WooCommerce order attribution.',
                    'cookie-consent-cmp'
                )
            ),
            $this->buildCookieInfo(
                'sbjs_migrations',
                __('6 months', 'cookie-consent-cmp'),
                __('Tracks Sourcebuster cookie format migrations.', 'cookie-consent-cmp')
            ),
            $this->buildCookieInfo(
                'sbjs_session',
                __('30 minutes', 'cookie-consent-cmp'),
                __('Stores the visitor’s current source attribution session.', 'cookie-consent-cmp')
            ),
            $this->buildCookieInfo(
                'sbjs_udata',
                __('6 months', 'cookie-consent-cmp'),
                __(
                    'Stores visitor user-agent and page attribution details for WooCommerce order reporting.',
                    'cookie-consent-cmp'
                )
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildKlaviyoService(string $lang): array
    {
        return [
            'name' => 'klaviyo',
            'title' => __('Klaviyo', 'cookie-consent-cmp'),
            'purposes' => ['marketing'],
            'default' => true,
            'required' => true,
            'optOut' => false,
            'onlyOnce' => true,
            'cookies' => ['__kla_id'],
            'wpConsentCookies' => [
                $this->buildCookieInfo(
                    '__kla_id',
                    __('2 years', 'cookie-consent-cmp'),
                    __(
                        'Stores Klaviyo visitor identity for email marketing, attribution, and WooCommerce tracking.',
                        'cookie-consent-cmp'
                    )
                ),
            ],
            'translations' => [
                $lang => [
                    'title' => __('Klaviyo', 'cookie-consent-cmp'),
                    'description' => __(
                        'Klaviyo tracking is loaded by the installed Klaviyo WooCommerce plugin and supports email marketing attribution, forms, and WooCommerce customer activity tracking.',
                        'cookie-consent-cmp'
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildYouTubeService(string $lang): array
    {
        return $this->buildOptionalService(
            'youtube',
            __('YouTube', 'cookie-consent-cmp'),
            'marketing',
            ['VISITOR_INFO1_LIVE', 'VISITOR_PRIVACY_METADATA', 'YSC', 'PREF'],
            $this->buildYouTubeCookieInfo(),
            __('Loads embedded videos provided by YouTube.', 'cookie-consent-cmp'),
            $lang
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildYouTubeCookieInfo(): array
    {
        $domain = 'https://www.youtube.com';

        return [
            $this->buildCookieInfo(
                'VISITOR_INFO1_LIVE',
                __('180 days', 'cookie-consent-cmp'),
                __('Measures bandwidth and player interface selection.', 'cookie-consent-cmp'),
                $domain
            ),
            $this->buildCookieInfo(
                'VISITOR_PRIVACY_METADATA',
                __('180 days', 'cookie-consent-cmp'),
                __('Stores the visitor’s YouTube privacy state.', 'cookie-consent-cmp'),
                $domain
            ),
            $this->buildCookieInfo(
                'YSC',
                __('Session', 'cookie-consent-cmp'),
                __('Maintains YouTube video-view session data.', 'cookie-consent-cmp'),
                $domain
            ),
            $this->buildCookieInfo(
                'PREF',
                __('8 months', 'cookie-consent-cmp'),
                __('Stores YouTube playback and display preferences.', 'cookie-consent-cmp'),
                $domain
            ),
        ];
    }

    /**
     * @param array<int, string> $cookies
     * @param array<int, array<string, string>> $cookieInfo
     * @return array<string, mixed>
     */
    private function buildOptionalService(
        string $name,
        string $title,
        string $purpose,
        array $cookies,
        array $cookieInfo,
        string $description,
        string $lang
    ): array {
        return [
            'name' => $name,
            'title' => $title,
            'purposes' => [$purpose],
            'default' => false,
            'required' => false,
            'optOut' => false,
            'onlyOnce' => true,
            'cookies' => $cookies,
            'wpConsentCookies' => $cookieInfo,
            'translations' => [
                $lang => [
                    'title' => $title,
                    'description' => $description,
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildCookieInfo(
        string $name,
        string $expires,
        string $function,
        string $domain = ''
    ): array {
        $cookieInfo = [
            'name' => $name,
            'expires' => $expires,
            'function' => $function,
            'type' => 'HTTP',
        ];

        if ($domain !== '') {
            $cookieInfo['domain'] = $domain;
        }

        return $cookieInfo;
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
            'name' => sprintf('wp-consent-category-%s', $category),
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
