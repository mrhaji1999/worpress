<?php

namespace Boiler\Services;

class Forms {

    // Flags to ensure scripts are enqueued only once per page load.
    private static $expert_reg_scripts_enqueued = false;
    private static $request_scripts_enqueued = false;
    private static $expert_dash_scripts_enqueued = false;

    public function __construct() {
        add_action('init', [$this, 'register_shortcodes']);
    }

    public function register_shortcodes() {
        add_shortcode('customer_dashboard', [$this, 'render_customer_dashboard']);
        add_shortcode('expert_dashboard', [$this, 'render_expert_dashboard']);
        add_shortcode('expert_profile_form', [$this, 'render_expert_profile_form']);
        add_shortcode('expert_registration_form', [$this, 'render_expert_registration_form']);
        add_shortcode('service_request_form', [$this, 'render_service_request_form']);
    }

    private function enqueue_request_scripts() {
        if (self::$request_scripts_enqueued) {
            return;
        }

        $cities_data = get_transient('boiler_iran_cities_data');
        if (false === $cities_data) {
            $json_path = BOILER_SERVICES_PLUGIN_PATH . 'iran-cities.json';
            if (file_exists($json_path)) {
                $json_content = file_get_contents($json_path);
                $cities_data = json_decode($json_content, true);
                if (is_null($cities_data)) $cities_data = []; // Handle JSON error
            } else {
                $cities_data = []; // Handle file not found
            }
            set_transient('boiler_iran_cities_data', $cities_data, MONTH_IN_SECONDS);
        }

        wp_localize_script('jquery', 'boilerServicesRequest', [
            'rest_url_step'         => esc_url_raw(rest_url('boiler-services/v1/request/save_step')),
            'rest_url_get_services' => esc_url_raw(rest_url('boiler-services/v1/services-by-category/')),
            'rest_url_select_expert'=> esc_url_raw(rest_url('boiler-services/v1/request/')),
            'nonce'                 => wp_create_nonce('wp_rest'),
            'form_steps'            => self::get_service_request_steps_config(),
            'cities_data'           => $cities_data,
            'i18n'                  => ['error_occurred' => __('An unexpected error occurred.', 'boiler-services')]
        ]);

        self::$request_scripts_enqueued = true;
    }

    public static function render_expert_dashboard_page() {
        echo '<div class="wrap"><h1>' . esc_html__('Expert Dashboard', 'boiler-services') . '</h1>';
        echo do_shortcode('[expert_dashboard]');
        echo '</div>';
    }

    public function render_customer_dashboard($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . sprintf(__('You must be logged in to view your dashboard. <a href="%s">Login</a>', 'boiler-services'), wp_login_url(get_permalink())) . '</p>';
        }
        $this->enqueue_request_scripts();
        return "<!-- Customer Dashboard HTML -->";
    }

    public function render_expert_dashboard($atts) {
        if (!current_user_can('expert_tech')) {
            return '<p>' . __('You do not have permission to view this dashboard.', 'boiler-services') . '</p>';
        }

        if (!self::$expert_dash_scripts_enqueued) {
            wp_localize_script('jquery', 'boilerServicesExpert', [
                'rest_url_respond' => esc_url_raw(rest_url('boiler-services/v1/request/')),
                'rest_url_invoice' => esc_url_raw(rest_url('boiler-services/v1/request/')),
                'nonce'            => wp_create_nonce('wp_rest'),
                'i18n'             => ['error_occurred' => __('An unexpected error occurred.', 'boiler-services')]
            ]);
            self::$expert_dash_scripts_enqueued = true;
        }
        return "<!-- Expert Dashboard HTML & JS -->";
    }

    public function render_expert_registration_form($atts) {
        if (!self::$expert_reg_scripts_enqueued) {
            wp_enqueue_media();
            wp_localize_script('jquery', 'boilerServicesRegister', [
                'rest_url_step_save' => esc_url_raw(rest_url('boiler-services/v1/register/step')),
                'rest_url_draft_get' => esc_url_raw(rest_url('boiler-services/v1/register/draft/')),
                'rest_url_submit'    => esc_url_raw(rest_url('boiler-services/v1/register/complete')),
                'nonce'              => wp_create_nonce('wp_rest'),
                'form_steps'         => self::get_registration_steps_config(),
                'i18n'               => ['error_occurred' => __('An unexpected error occurred.', 'boiler-services')]
            ]);
            self::$expert_reg_scripts_enqueued = true;
        }
        return "<!-- Expert Registration Form HTML & JS -->";
    }

    public function render_service_request_form($atts) {
        $this->enqueue_request_scripts();
        return "<!-- Service Request Form HTML & JS -->";
    }

    public function render_expert_profile_form($atts) {
        if (!is_user_logged_in()) {
             return '<p>' . __('You must be logged in to view your profile.', 'boiler-services') . '</p>';
        }
        wp_enqueue_media();
        return "<!-- Expert Profile Form HTML & JS -->";
    }

    public static function get_registration_steps_config() {
        // Returns array of registration steps and fields
        return [];
    }

    public static function get_service_request_steps_config() {
        // Returns array of service request steps and fields
        return [];
    }
}