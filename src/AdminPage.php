<?php

declare(strict_types=1);

namespace SzepeViktor\CookieConsentCmp;

use SzepeViktor\CookieConsentCmp\Frontend\ConsentApiBridge;

use function __;
use function add_action;
use function add_options_page;
use function add_settings_field;
use function add_settings_section;
use function checked;
use function current_user_can;
use function do_settings_sections;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function esc_textarea;
use function register_setting;
use function selected;
use function settings_fields;
use function submit_button;

final class AdminPage
{
    public const MENU_SLUG = 'cookie-consent-cmp';
    public const PAGE_SLUG = 'cookie-consent-cmp-page';
    public const OPTION_GROUP = 'cookie_consent_cmp';
    public const BANNER_SECTION = 'cookie-consent-cmp-banner';
    public const DISPLAY_SECTION = 'cookie-consent-cmp-display';
    public const INTEGRATIONS_SECTION = 'cookie-consent-cmp-integrations';

    private Options $options;

    private ConsentApiBridge $consentApiBridge;

    public function __construct(Options $options, ConsentApiBridge $consentApiBridge)
    {
        $this->options = $options;
        $this->consentApiBridge = $consentApiBridge;
    }

    public function boot(): void
    {
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'addFields']);
    }

    public function addSettingsPage(): void
    {
        add_options_page(
            __('Cookie Consent CMP', 'cookie-consent-cmp'),
            __('Cookie Consent CMP', 'cookie-consent-cmp'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderSettingsPage']
        );
    }

    public function addFields(): void
    {
        $this->registerSettings();
        $this->addSections();
        $this->addBannerFields();
        $this->addIntegrationFields();
        $this->addDisplayFields();
    }

    private function registerSettings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            Options::OPTION_NAME,
            [
                'type' => 'array',
                'default' => $this->options->defaults(),
                'sanitize_callback' => [$this->options, 'sanitize'],
            ]
        );
    }

    private function addSections(): void
    {
        add_settings_section(
            self::BANNER_SECTION,
            __('Banner copy', 'cookie-consent-cmp'),
            [$this, 'renderBannerSection'],
            self::PAGE_SLUG
        );
        add_settings_section(
            self::DISPLAY_SECTION,
            __('Display', 'cookie-consent-cmp'),
            [$this, 'renderDisplaySection'],
            self::PAGE_SLUG
        );
        add_settings_section(
            self::INTEGRATIONS_SECTION,
            __('Integrations', 'cookie-consent-cmp'),
            [$this, 'renderIntegrationsSection'],
            self::PAGE_SLUG
        );
    }

    private function addBannerFields(): void
    {
        $this->addTextField(
            'notice_title',
            __('Notice title', 'cookie-consent-cmp'),
            self::BANNER_SECTION
        );
        $this->addTextareaField(
            'notice_description',
            __('Notice description', 'cookie-consent-cmp'),
            self::BANNER_SECTION
        );
        $this->addTextField(
            'modal_title',
            __('Modal title', 'cookie-consent-cmp'),
            self::BANNER_SECTION
        );
        $this->addTextareaField(
            'modal_description',
            __('Modal description', 'cookie-consent-cmp'),
            self::BANNER_SECTION
        );
    }

    private function addDisplayFields(): void
    {
        add_settings_field(
            'cookie-consent-cmp-modal-style',
            __('Modal style', 'cookie-consent-cmp'),
            [$this, 'renderModalStyleField'],
            self::PAGE_SLUG,
            self::DISPLAY_SECTION
        );
        add_settings_field(
            'cookie-consent-cmp-enable-floating',
            __('Floating privacy button', 'cookie-consent-cmp'),
            [$this, 'renderCheckboxField'],
            self::PAGE_SLUG,
            self::DISPLAY_SECTION,
            [
                'name' => 'enable_floating',
                'label' => __('Show a floating button that reopens the privacy settings.', 'cookie-consent-cmp'),
            ]
        );
    }

    private function addIntegrationFields(): void
    {
        $this->addTextField(
            'gtm_id',
            __('Google Tag Manager ID', 'cookie-consent-cmp'),
            self::INTEGRATIONS_SECTION
        );
        $this->addTextField(
            'clarity_project_id',
            __('Microsoft Clarity project ID', 'cookie-consent-cmp'),
            self::INTEGRATIONS_SECTION
        );
        $this->addTextField(
            'hotjar_id',
            __('Hotjar site ID', 'cookie-consent-cmp'),
            self::INTEGRATIONS_SECTION
        );
        $this->addNumberField(
            'hotjar_version',
            __('Hotjar script version', 'cookie-consent-cmp'),
            self::INTEGRATIONS_SECTION
        );
        $this->addTextField(
            'meta_pixel_id',
            __('Meta Pixel ID', 'cookie-consent-cmp'),
            self::INTEGRATIONS_SECTION
        );
        $this->addTextField(
            'linkedin_partner_id',
            __('LinkedIn partner ID', 'cookie-consent-cmp'),
            self::INTEGRATIONS_SECTION
        );
        $this->addYouTubeField();
    }

    private function addYouTubeField(): void
    {
        add_settings_field(
            'cookie-consent-cmp-enable-youtube',
            __('YouTube blocking', 'cookie-consent-cmp'),
            [$this, 'renderCheckboxField'],
            self::PAGE_SLUG,
            self::INTEGRATIONS_SECTION,
            [
                'name' => 'enable_youtube',
                'label' => __(
                    'Block and replace YouTube embeds until marketing consent is granted.',
                    'cookie-consent-cmp'
                ),
            ]
        );
    }

    public function renderSettingsPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $compatibilityNotice = $this->compatibilityNotice();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Cookie Consent CMP', 'cookie-consent-cmp'); ?></h1>
            <p>
                <?php
                esc_html_e(
                    'Configure the consent texts and vendor IDs used by the frontend Klaro banner.',
                    'cookie-consent-cmp'
                );
                ?>
            </p>

            <?php if ($compatibilityNotice !== '') : ?>
                <div class="notice notice-warning inline">
                    <p><?php printf('%s', esc_html($compatibilityNotice)); ?></p>
                </div>
            <?php endif; ?>

            <form action="options.php" method="POST">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    private function compatibilityNotice(): string
    {
        if (! $this->consentApiBridge->is_api_available()) {
            return sprintf(
                '%s %s %s',
                __('WP Consent API was not detected.', 'cookie-consent-cmp'),
                __(
                    'The CMP will still render, but the WordPress compatibility bridge will stay inactive',
                    'cookie-consent-cmp'
                ),
                __('until the API plugin is available.', 'cookie-consent-cmp')
            );
        }

        if (! $this->consentApiBridge->has_consent_type_conflict()) {
            return '';
        }

        return sprintf(
            '%s %s %s',
            __('Another plugin already provides the WP Consent API consent type.', 'cookie-consent-cmp'),
            __(
                'Cookie Consent CMP preserves that value; verify that only one consent management platform',
                'cookie-consent-cmp'
            ),
            __('controls the site.', 'cookie-consent-cmp')
        );
    }

    public function renderBannerSection(): void
    {
        esc_html_e('Set the text displayed in the consent notice and preferences dialog.', 'cookie-consent-cmp');
    }

    public function renderIntegrationsSection(): void
    {
        esc_html_e('Enter only the services that this site uses.', 'cookie-consent-cmp');
    }

    public function renderDisplaySection(): void
    {
        esc_html_e('Control how visitors can access their privacy settings.', 'cookie-consent-cmp');
    }

    /**
     * @param array{name: string} $args
     */
    public function renderTextField(array $args): void
    {
        $this->renderInput($args['name'], 'regular-text', 'text');
    }

    /**
     * @param array{name: string} $args
     */
    public function renderNumberField(array $args): void
    {
        $this->renderInput($args['name'], 'small-text', 'number', ' min="1"');
    }

    /**
     * @param array{name: string} $args
     */
    public function renderTextareaField(array $args): void
    {
        $name = $args['name'];
        $options = $this->options->all();

        printf(
            '<textarea class="large-text" id="%1$s" name="%2$s[%3$s]" rows="4">%4$s</textarea>',
            esc_attr($this->fieldId($name)),
            esc_attr(Options::OPTION_NAME),
            esc_attr($name),
            esc_textarea((string) $options[$name])
        );
    }

    /**
     * @param array{name: string, label: string} $args
     */
    public function renderCheckboxField(array $args): void
    {
        $name = $args['name'];
        $options = $this->options->all();

        printf(
            '<label for="%1$s"><input id="%1$s" name="%2$s[%3$s]" type="checkbox" value="1" %4$s> %5$s</label>',
            esc_attr($this->fieldId($name)),
            esc_attr(Options::OPTION_NAME),
            esc_attr($name),
            checked((bool) $options[$name], true, false),
            esc_html($args['label'])
        );
    }

    public function renderModalStyleField(): void
    {
        $options = $this->options->all();
        $styles = [
            Options::MODAL_STYLE_KLARO_DEFAULT => __('Klaro’s default', 'cookie-consent-cmp'),
            Options::MODAL_STYLE_VIKTOR_DEFAULT => __('Viktor’s default', 'cookie-consent-cmp'),
            Options::MODAL_STYLE_LIGHT => __('Light', 'cookie-consent-cmp'),
            Options::MODAL_STYLE_DARK => __('Dark', 'cookie-consent-cmp'),
            Options::MODAL_STYLE_TWENTY_TWENTY_FIVE => __('Twenty Twenty-Five', 'cookie-consent-cmp'),
        ];

        printf(
            '<select id="%1$s" name="%2$s[modal_style]">',
            esc_attr($this->fieldId('modal_style')),
            esc_attr(Options::OPTION_NAME)
        );

        foreach ($styles as $value => $label) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($value),
                selected((string) $options['modal_style'], $value, false),
                esc_html($label)
            );
        }

        echo '</select>';
        printf(
            '<p class="description">%s</p>',
            esc_html(
                sprintf(
                    '%s %s',
                    __('Klaro’s default applies no custom modal theme;', 'cookie-consent-cmp'),
                    __(
                        'the component stylesheet only positions and styles plugin controls.',
                        'cookie-consent-cmp'
                    )
                )
            )
        );
    }

    private function addTextField(string $name, string $title, string $section): void
    {
        $this->addField($name, $title, $section, 'renderTextField');
    }

    private function addTextareaField(string $name, string $title, string $section): void
    {
        $this->addField($name, $title, $section, 'renderTextareaField');
    }

    private function addNumberField(string $name, string $title, string $section): void
    {
        $this->addField($name, $title, $section, 'renderNumberField');
    }

    private function addField(string $name, string $title, string $section, string $callback): void
    {
        add_settings_field(
            $this->fieldId($name),
            $title,
            [$this, $callback],
            self::PAGE_SLUG,
            $section,
            ['name' => $name]
        );
    }

    private function renderInput(string $name, string $class, string $type, string $attributes = ''): void
    {
        $options = $this->options->all();

        printf(
            '<input class="%1$s" id="%2$s" name="%3$s[%4$s]" type="%5$s" value="%6$s"%7$s>',
            esc_attr($class),
            esc_attr($this->fieldId($name)),
            esc_attr(Options::OPTION_NAME),
            esc_attr($name),
            esc_attr($type),
            esc_attr((string) $options[$name]),
            $attributes
        );
    }

    private function fieldId(string $name): string
    {
        return sprintf('cookie-consent-cmp-%s', str_replace('_', '-', $name));
    }
}
