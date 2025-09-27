<?php

namespace Boiler\Services;

class Forms {

    // Flags to ensure scripts are enqueued only once per page load.
    private static $dashboard_assets_enqueued = false;
    private static $expert_reg_scripts_enqueued = false;
    private static $request_scripts_enqueued = false;

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

    private function enqueue_dashboard_assets() {
        if (self::$dashboard_assets_enqueued) {
            return;
        }

        $plugin_url = plugin_dir_url(dirname(__FILE__));
        wp_enqueue_script(
            'boiler-services-dashboard',
            $plugin_url . 'assets/js/dashboard.js',
            ['jquery'],
            '1.0.1',
            true
        );
        wp_localize_script('boiler-services-dashboard', 'boilerDashboard', [
            'urls' => [
                'get'       => esc_url_raw(rest_url('boiler-services/v1/notifications')),
                'mark_read' => esc_url_raw(rest_url('boiler-services/v1/notifications/mark-read')),
            ],
            'nonce'    => wp_create_nonce('wp_rest'),
            'i18n' => [
                'no_notifications' => __('No new notifications.', 'boiler-services'),
                'error_loading'    => __('Error loading notifications.', 'boiler-services'),
            ]
        ]);
        wp_enqueue_style(
            'boiler-services-dashboard-css',
             $plugin_url . 'assets/css/dashboard.css'
        );
        self::$dashboard_assets_enqueued = true;
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
        $this->enqueue_dashboard_assets();

        $requests = new \WP_Query([
            'post_type' => 'service_request',
            'author' => get_current_user_id(),
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);

        ob_start();
        ?>
        <div class="boiler-dashboard-wrap">
            <div class="boiler-dashboard-grid">
                <div class="dashboard-card notifications-card">
                    <h2><?php esc_html_e('Notifications', 'boiler-services'); ?><span id="notifications-badge" style="display:none;"></span></h2>
                    <div id="notifications-container"><p><?php esc_html_e('Loading...', 'boiler-services'); ?></p></div>
                </div>
                 <div class="dashboard-card">
                    <h2><?php esc_html_e('My Service Requests', 'boiler-services'); ?></h2>
                    <div class="requests-list">
                        <?php if ($requests->have_posts()) : ?>
                            <?php while ($requests->have_posts()) : $requests->the_post(); ?>
                                <?php $this->render_request_card_customer(get_the_ID()); ?>
                            <?php endwhile; wp_reset_postdata(); ?>
                        <?php else : ?>
                            <p><?php esc_html_e('You have not made any service requests yet.', 'boiler-services'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_expert_dashboard($atts) {
        if (!current_user_can('expert_tech')) {
            return '<p>' . __('You do not have permission to view this dashboard.', 'boiler-services') . '</p>';
        }
        $this->enqueue_dashboard_assets();
        $expert_id = get_current_user_id();

        $opportunities = new \WP_Query([ 'post_type' => 'service_request', 'post_status' => 'awaiting_experts', 'posts_per_page' => 10 ]);
        $assigned = new \WP_Query([ 'post_type' => 'service_request', 'post_status' => 'assigned', 'posts_per_page' => 10, 'meta_query' => [['key' => 'assigned_expert_id', 'value' => $expert_id]] ]);

        ob_start();
        ?>
        <div class="boiler-dashboard-wrap">
            <div class="boiler-dashboard-grid">
                 <div class="dashboard-card notifications-card">
                    <h2><?php esc_html_e('Notifications', 'boiler-services'); ?><span id="notifications-badge" style="display:none;"></span></h2>
                    <div id="notifications-container"><p><?php esc_html_e('Loading...', 'boiler-services'); ?></p></div>
                </div>
                <div class="dashboard-card">
                    <h2><?php esc_html_e('New Opportunities', 'boiler-services'); ?></h2>
                    <?php if ($opportunities->have_posts()) : while ($opportunities->have_posts()) : $opportunities->the_post(); ?>
                        <?php $this->render_request_card_expert(get_the_ID()); ?>
                    <?php endwhile; wp_reset_postdata(); else : ?>
                        <p><?php esc_html_e('No new opportunities.', 'boiler-services'); ?></p>
                    <?php endif; ?>
                </div>
                <div class="dashboard-card">
                    <h2><?php esc_html_e('My Active Jobs', 'boiler-services'); ?></h2>
                    <?php if ($assigned->have_posts()) : while ($assigned->have_posts()) : $assigned->the_post(); ?>
                        <?php $this->render_request_card_expert(get_the_ID()); ?>
                    <?php endwhile; wp_reset_postdata(); else : ?>
                        <p><?php esc_html_e('You have no active jobs.', 'boiler-services'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_request_card_customer($request_id) {
        $post = get_post($request_id);
        $status = get_post_status_object($post->post_status);
        echo '<div class="request-card"><div class="request-card-header"><div class="request-card-title">' . esc_html($post->post_title) . '</div><div class="request-card-status">' . esc_html($status->label) . '</div></div><div class="request-card-actions">';
        if ($post->post_status === 'awaiting_customer_selection') {
            printf('<a href="#" class="button button-primary">%s</a>', esc_html__('Select an Expert', 'boiler-services'));
        } else {
             printf('<a href="%s" class="button button-secondary">%s</a>', get_permalink($post->ID), esc_html__('View Details', 'boiler-services'));
        }
        echo '</div></div>';
    }

    private function render_request_card_expert($request_id) {
        $post = get_post($request_id);
        $status = get_post_status_object($post->post_status);
        echo '<div class="request-card"><div class="request-card-header"><div class="request-card-title">' . esc_html($post->post_title) . '</div><div class="request-card-status">' . esc_html($status->label) . '</div></div><div class="request-card-actions">';
        if ($post->post_status === 'awaiting_experts') {
            printf('<a href="#" class="button button-primary respond-btn" data-id="%d" data-action="accept">%s</a>', $request_id, esc_html__('Accept', 'boiler-services'));
            printf('<a href="#" class="button button-secondary respond-btn" data-id="%d" data-action="reject">%s</a>', $request_id, esc_html__('Reject', 'boiler-services'));
        } elseif ($post->post_status === 'assigned') {
             printf('<a href="#" class="button button-primary create-invoice-btn" data-id="%d">%s</a>', $request_id, esc_html__('Create Invoice', 'boiler-services'));
        }
        echo '</div></div>';
    }

    public function render_expert_profile_form($atts) { return "<!-- Stub -->"; }
    public function render_expert_registration_form($atts) { return "<!-- Stub -->"; }
    public function render_service_request_form($atts) { return "<!-- Stub -->"; }
}