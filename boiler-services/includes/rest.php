<?php

namespace Boiler\Services;

class REST {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    private function check_nonce(\WP_REST_Request $request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce) {
            return new \WP_Error('rest_nonce_missing', __('Nonce is required.', 'boiler-services'), ['status' => 401]);
        }
        $result = wp_verify_nonce($nonce, 'wp_rest');
        if (!$result) {
            return new \WP_Error('rest_nonce_invalid', __('Nonce is invalid.', 'boiler-services'), ['status' => 403]);
        }
        return true;
    }

    public function register_routes() {
        // --- Registration Endpoints ---
        register_rest_route('boiler-services/v1', '/register/step', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'save_registration_step'],
            'permission_callback' => [$this, 'check_nonce'],
        ]);
        register_rest_route('boiler-services/v1', '/register/complete', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_final_registration'],
            'permission_callback' => [$this, 'check_nonce'],
        ]);

        // --- Service Request Endpoints ---
        register_rest_route('boiler-services/v1', '/request/save_step', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'save_service_request_step'],
            'permission_callback' => [$this, 'check_nonce'],
        ]);
        register_rest_route('boiler-services/v1', '/services-by-category/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_services_by_category'],
            'permission_callback' => '__return_true', // Publicly viewable data
        ]);
        register_rest_route('boiler-services/v1', '/request/(?P<request_id>\d+)/respond', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_expert_response'],
            'permission_callback' => [$this, 'expert_response_permission_check'],
            'args' => ['response' => ['required' => true, 'validate_callback' => fn($p) => in_array($p, ['accept', 'reject'])]],
        ]);
        register_rest_route('boiler-services/v1', '/request/(?P<request_id>\d+)/select-expert', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_expert_selection'],
            'permission_callback' => [$this, 'select_expert_permission_check'],
            'args' => ['expert_id' => ['required' => true, 'validate_callback' => 'is_numeric', 'sanitize_callback' => 'absint']],
        ]);
        register_rest_route('boiler-services/v1', '/request/(?P<request_id>\d+)/create-invoice', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_create_invoice'],
            'permission_callback' => [$this, 'create_invoice_permission_check'],
            'args' => ['amount' => ['required' => true, 'validate_callback' => 'is_numeric']],
        ]);
    }

    // --- PERMISSION CALLBACKS ---

    public function expert_response_permission_check(\WP_REST_Request $request) {
        $nonce_check = $this->check_nonce($request);
        if (is_wp_error($nonce_check)) return $nonce_check;

        if (!current_user_can('respond_service_requests')) {
            return new \WP_Error('rest_forbidden', __('You do not have permission to respond to requests.', 'boiler-services'), ['status' => 403]);
        }

        $request_id = (int) $request['request_id'];
        $post = get_post($request_id);
        if (!$post || $post->post_type !== 'service_request' || $post->post_status !== 'awaiting_experts') {
            return new \WP_Error('rest_invalid_request', __('This request is not available for responses.', 'boiler-services'), ['status' => 404]);
        }

        return true;
    }

    public function select_expert_permission_check(\WP_REST_Request $request) {
        $nonce_check = $this->check_nonce($request);
        if (is_wp_error($nonce_check)) return $nonce_check;

        $request_id = (int) $request['request_id'];
        if (!current_user_can('edit_service_request', $request_id)) {
            return new \WP_Error('rest_forbidden', __('You cannot modify this request.', 'boiler-services'), ['status' => 403]);
        }

        return true;
    }

    public function create_invoice_permission_check(\WP_REST_Request $request) {
        $nonce_check = $this->check_nonce($request);
        if (is_wp_error($nonce_check)) return $nonce_check;

        if (!current_user_can('create_expert_invoice')) {
            return new \WP_Error('rest_forbidden', __('You do not have permission to create invoices.', 'boiler-services'), ['status' => 403]);
        }

        $request_id = (int) $request['request_id'];
        $assigned_expert_id = (int) get_post_meta($request_id, 'assigned_expert_id', true);

        if (get_current_user_id() !== $assigned_expert_id) {
            return new \WP_Error('rest_not_assigned', __('You are not assigned to this service request.', 'boiler-services'), ['status' => 403]);
        }

        return true;
    }

    // --- DUMMY CALLBACK IMPLEMENTATIONS ---
    // These methods contain the basic structure with sanitization and validation.

    public function save_registration_step(\WP_REST_Request $request) {
        $params = $request->get_json_params();
        $sanitized_data = [];
        foreach ($params as $key => $value) {
            $sanitized_data[sanitize_key($key)] = sanitize_text_field($value);
        }
        // In a real implementation, data would be saved to a transient or user meta.
        return new \WP_REST_Response(['status' => 'success', 'message' => __('Step data saved.', 'boiler-services'), 'data' => $sanitized_data], 200);
    }

    public function handle_final_registration(\WP_REST_Request $request) {
        // Final validation and user creation logic would go here.
        return new \WP_REST_Response(['status' => 'success', 'message' => __('Registration complete.', 'boiler-services')], 200);
    }

    public function save_service_request_step(\WP_REST_Request $request) {
        $params = $request->get_json_params();
        $sanitized_data = [];
        foreach ($params as $key => $value) {
            $sanitized_data[sanitize_key($key)] = sanitize_text_field($value);
        }
        // Logic to create/update the 'service_request' post in 'drafting' status.
        return new \WP_REST_Response(['status' => 'success', 'message' => __('Request step saved.', 'boiler-services'), 'post_id' => 0], 200);
    }

    public function get_services_by_category(\WP_REST_Request $request) {
        $category_id = (int) $request['id'];
        $query_args = [
            'post_type' => 'service',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'service_category',
                    'field'    => 'term_id',
                    'terms'    => $category_id,
                ],
            ],
            'fields' => 'ids', // More efficient
        ];
        $posts = get_posts($query_args);
        return new \WP_REST_Response($posts, 200);
    }

    public function handle_expert_response(\WP_REST_Request $request) {
        $request_id = (int) $request['request_id'];
        $response = sanitize_key($request['response']);
        $expert_id = get_current_user_id();

        // In a real implementation, you would save this to the custom DB table.

        $message = sprintf(
            __('Expert responded with: %s', 'boiler-services'),
            'accept' === $response ? __('Accepted', 'boiler-services') : __('Rejected', 'boiler-services')
        );
        Timeline::add_event($request_id, $message, 'expert_response', $expert_id);

        return new \WP_REST_Response(['success' => true, 'message' => __('Response recorded.', 'boiler-services')], 200);
    }

    public function handle_expert_selection(\WP_REST_Request $request) {
        $request_id = (int) $request['request_id'];
        $expert_id = $request['expert_id'];

        update_post_meta($request_id, 'assigned_expert_id', $expert_id);
        wp_update_post(['ID' => $request_id, 'post_status' => 'assigned']);

        $expert_user = get_userdata($expert_id);
        $message = sprintf(__('Customer selected expert: %s', 'boiler-services'), $expert_user->display_name);
        Timeline::add_event($request_id, $message, 'customer_selection', get_current_user_id());

        return new \WP_REST_Response(['success' => true, 'message' => __('Expert selected.', 'boiler-services')], 200);
    }

    public function handle_create_invoice(\WP_REST_Request $request) {
        $request_id = (int) $request['request_id'];
        $amount = floatval($request['amount']);

        // In a real implementation, you would create a Woo order here.
        // $order_id = create_woo_order_programmatically(...);
        // update_post_meta($request_id, '_order_id', $order_id);

        $message = sprintf(__('Invoice created by expert for amount: %s', 'boiler-services'), $amount);
        Timeline::add_event($request_id, $message, 'invoice_created', get_current_user_id());

        return new \WP_REST_Response(['success' => true, 'message' => __('Invoice created.', 'boiler-services'), 'order_id' => 0], 200);
    }
}