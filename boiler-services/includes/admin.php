<?php

namespace Boiler\Services;

class Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_dashboard_pages']);
        add_action('admin_menu', [$this, 'customize_admin_menu'], 99);
        add_action('admin_init', [$this, 'redirect_expert_from_dashboard']);

        // Custom columns
        add_filter('manage_service_posts_columns', [$this, 'add_service_admin_columns']);
        add_action('manage_service_posts_custom_column', [$this, 'render_service_admin_columns'], 10, 2);
        add_filter('manage_users_columns', [$this, 'add_expert_user_columns']);
        add_action('manage_users_custom_column', [$this, 'render_expert_user_columns'], 10, 3);

        // Filters and Actions
        add_action('restrict_manage_posts', [$this, 'add_taxonomy_filters_to_admin_list']);
        add_filter('user_row_actions', [$this, 'add_expert_user_row_actions'], 10, 2);
        add_action('admin_init', [$this, 'handle_expert_quick_actions']);
        add_action('admin_init', [$this, 'handle_csv_export']);
        add_action('admin_notices', [$this, 'show_expert_action_admin_notices']);

        // Timeline AJAX and assets
        add_action('wp_ajax_get_request_timeline', [$this, 'get_request_timeline_ajax']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_reports_page_assets']);
        add_action('admin_footer', [$this, 'add_timeline_modal_html']);

        // Post Edit Screen
        add_action('admin_footer-post.php', [$this, 'add_custom_statuses_to_edit_screen']);
        add_action('admin_footer-post-new.php', [$this, 'add_custom_statuses_to_edit_screen']);
    }

    public function enqueue_reports_page_assets($hook) {
        // Only load on our specific admin page.
        if ($hook !== 'service_request_page_boiler-services-reports') {
            return;
        }
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        wp_enqueue_script(
            'boiler-services-reports',
            $plugin_url . 'assets/js/admin-reports.js',
            ['jquery'],
            '1.0.0',
            true
        );
        wp_localize_script('boiler-services-reports', 'boilerAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('get_request_timeline_nonce'),
        ]);
        wp_enqueue_style(
            'boiler-services-reports-css',
             $plugin_url . 'assets/css/admin-reports.css'
        );
    }

    public function get_request_timeline_ajax() {
        check_ajax_referer('get_request_timeline_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
        }

        $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        if (!$request_id || get_post_type($request_id) !== 'service_request') {
            wp_send_json_error(['message' => 'Invalid request ID.'], 400);
        }

        $timeline = get_post_meta($request_id, '_service_request_timeline', true);
        if (empty($timeline) || !is_array($timeline)) {
            wp_send_json_success(['html' => '<p>' . __('No timeline events found.', 'boiler-services') . '</p>']);
        }

        $html = '<ul class="timeline-list">';
        foreach ($timeline as $event) {
            $html .= sprintf(
                '<li><strong>%s:</strong> %s <em>(%s by %s)</em></li>',
                esc_html(date_i18n(get_option('date_format') . ' H:i', $event['timestamp'])),
                esc_html($event['message']),
                esc_html($event['type']),
                esc_html($event['actor_name'])
            );
        }
        $html .= '</ul>';

        wp_send_json_success(['html' => $html]);
    }

    public function add_timeline_modal_html() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'service_request_page_boiler-services-reports') {
            echo '<div id="timeline-modal" style="display:none;"><div id="timeline-modal-content"></div><a id="timeline-modal-close">&times;</a></div>';
        }
    }

    public function add_dashboard_pages() {
        // Expert-specific dashboard (low-level menu)
        if (current_user_can('expert_tech') && !current_user_can('manage_options')) {
             add_menu_page(
                __('Expert Dashboard', 'boiler-services'),
                __('Expert Dashboard', 'boiler-services'),
                'read', // Basic capability
                'expert-dashboard',
                [Forms::class, 'render_expert_dashboard_page'],
                'dashicons-dashboard',
                30
            );
        }

        // Admin management page
        add_submenu_page(
            'edit.php?post_type=service_request',
            __('Manage & Reports', 'boiler-services'),
            __('Manage & Reports', 'boiler-services'),
            'manage_options',
            'boiler-services-reports',
            [$this, 'render_reports_page']
        );
    }

    public function render_reports_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Service Requests Management & Reports', 'boiler-services') . '</h1>';

        $list_table = new Service_Requests_List_Table();
        $list_table->prepare_items();

        echo '<form method="get">'; // Use GET to keep filters in URL
        echo '<input type="hidden" name="post_type" value="service_request" />';
        echo '<input type="hidden" name="page" value="' . esc_attr($_REQUEST['page']) . '" />';

        $list_table->display();

        echo '</form>';
        echo '</div>';
    }

    public function handle_csv_export() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'export_requests_csv') {
            return;
        }

        if (!wp_verify_nonce($_GET['_wpnonce'], 'export_requests_nonce')) {
            wp_die(__('Security check failed.', 'boiler-services'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export data.', 'boiler-services'));
        }

        $args = [
            'post_type'      => 'service_request',
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ];

        if (!empty($_GET['status_filter'])) {
            $args['post_status'] = sanitize_key($_GET['status_filter']);
        }
        if (!empty($_GET['category_filter'])) {
            $args['tax_query'] = [[
                'taxonomy' => 'service_category',
                'field'    => 'term_id',
                'terms'    => (int) $_GET['category_filter'],
            ]];
        }

        $query = new \WP_Query($args);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=service-requests-' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');

        fputcsv($output, ['ID', 'Title', 'Customer', 'Status', 'Assigned Expert', 'City', 'Priority', 'Date', 'Final Price']);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $request_id = get_the_ID();
                $author = get_the_author();
                $expert_id = get_post_meta($request_id, 'assigned_expert_id', true);
                $expert = $expert_id ? get_userdata($expert_id)->display_name : 'N/A';

                // Placeholder for final price - requires Woo integration logic
                $price = '0.00';

                fputcsv($output, [
                    $request_id,
                    get_the_title(),
                    $author,
                    get_post_status(),
                    $expert,
                    get_post_meta($request_id, 'billing_city', true),
                    get_post_meta($request_id, 'priority', true),
                    get_the_date(),
                    $price,
                ]);
            }
        }

        fclose($output);
        exit;
    }

    public function customize_admin_menu() {
        if (in_array('expert_tech', (array) wp_get_current_user()->roles)) {
            global $menu;
            $allowed = ['profile.php', 'expert-dashboard', 'separator1', 'separator2', 'separator-last'];
            foreach ($menu as $key => $item) {
                if (!in_array($item[2], $allowed)) {
                    remove_menu_page($item[2]);
                }
            }
            remove_menu_page('index.php');
        }
    }

    public function redirect_expert_from_dashboard() {
        if (in_array('expert_tech', (array) wp_get_current_user()->roles)) {
            if (is_admin() && !defined('DOING_AJAX') && basename($_SERVER['PHP_SELF']) === 'index.php') {
                wp_redirect(admin_url('admin.php?page=expert-dashboard'));
                exit;
            }
        }
    }

    public function add_expert_user_columns($columns) {
        $columns['expert_status'] = __('Status', 'boiler-services');
        $columns['expert_grade'] = __('Grade', 'boiler-services');
        $columns['expert_coverage_cities'] = __('Coverage Cities', 'boiler-services');
        return $columns;
    }

    public function render_expert_user_columns($value, $column_name, $user_id) {
        $user = get_userdata($user_id);
        if (!$user || !in_array('expert_tech', (array) $user->roles)) {
            return $value;
        }

        // Get all meta at once to avoid multiple DB calls per user.
        $meta = get_user_meta($user_id);

        switch ($column_name) {
            case 'expert_status':
                $status = $meta['expert_status'][0] ?? '—';
                return esc_html($status);
            case 'expert_grade':
                $grade = $meta['expert_grade'][0] ?? '—';
                return esc_html($grade);
            case 'expert_coverage_cities':
                $cities = $meta['expert_coverage_cities'][0] ?? '—';
                // Assuming cities are stored as a comma-separated string.
                return esc_html($cities);
        }
        return $value;
    }

    public function add_expert_user_row_actions($actions, $user) {
        if (!current_user_can('manage_options') || !in_array('expert_tech', (array) $user->roles)) {
            return $actions;
        }

        $statuses = ['pending', 'approved', 'vacation', 'suspended'];
        $current_status = get_user_meta($user->ID, 'expert_status', true);

        foreach ($statuses as $status) {
            if ($status === $current_status) continue;
            $url = wp_nonce_url(
                admin_url('users.php?action=set_expert_status&user_id=' . $user->ID . '&expert_status=' . $status),
                'set_expert_status_' . $user->ID
            );
            $actions['set_status_' . $status] = '<a href="' . $url . '">' . sprintf(__('Set as %s', 'boiler-services'), ucfirst($status)) . '</a>';
        }

        $grades = ['A', 'B', 'C'];
        $current_grade = get_user_meta($user->ID, 'expert_grade', true);
        foreach ($grades as $grade) {
             if ($grade === $current_grade) continue;
             $url = wp_nonce_url(
                admin_url('users.php?action=set_expert_grade&user_id=' . $user->ID . '&expert_grade=' . $grade),
                'set_expert_grade_' . $user->ID
            );
            $actions['set_grade_' . $grade] = '<a href="' . $url . '">' . sprintf(__('Set Grade %s', 'boiler-services'), $grade) . '</a>';
        }

        return $actions;
    }

    public function handle_expert_quick_actions() {
        if (!current_user_can('manage_options') || !isset($_REQUEST['action']) || !isset($_REQUEST['user_id'])) {
            return;
        }

        $user_id = (int) $_REQUEST['user_id'];

        if ($_REQUEST['action'] === 'set_expert_status') {
            check_admin_referer('set_expert_status_' . $user_id);
            $status = sanitize_key($_REQUEST['expert_status']);
            if (in_array($status, ['pending', 'approved', 'vacation', 'suspended'])) {
                update_user_meta($user_id, 'expert_status', $status);
                wp_redirect(remove_query_arg(['action', 'user_id', 'expert_status', '_wpnonce'], wp_get_referer()));
                exit;
            }
        }

        if ($_REQUEST['action'] === 'set_expert_grade') {
            check_admin_referer('set_expert_grade_' . $user_id);
            $grade = sanitize_key($_REQUEST['expert_grade']);
            if (in_array($grade, ['A', 'B', 'C'])) {
                update_user_meta($user_id, 'expert_grade', $grade);
                wp_redirect(remove_query_arg(['action', 'user_id', 'expert_grade', '_wpnonce'], wp_get_referer()));
                exit;
            }
        }
    }

    public function show_expert_action_admin_notices() {
        if (isset($_GET['expert_action_success'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Expert information updated successfully.', 'boiler-services') . '</p></div>';
        }
    }

    public function add_custom_statuses_to_edit_screen(){
        // JS to add custom statuses to dropdown, as implemented before
    }

    // Other admin-related methods like add_service_admin_columns, etc. go here
}