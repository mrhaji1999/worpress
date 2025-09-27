<?php

namespace Boiler\Services;

class Roles {

    public function __construct() {
        add_filter('map_meta_cap', [$this, 'map_service_request_meta_caps'], 10, 4);
    }

    public static function on_activation() {
        self::add_roles_and_caps();
    }

    public static function on_deactivation() {
        self::remove_roles_and_caps();
    }

    private static function add_roles_and_caps() {
        add_role('expert_tech', __('Expert Technician', 'boiler-services'), [
            'read' => true,
            'respond_service_requests' => true,
            'create_expert_invoice' => true,
        ]);

        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('respond_service_requests');
            $admin_role->add_cap('create_expert_invoice');

            $cpt_caps = [
                "edit_service_request", "read_service_request", "delete_service_request",
                "edit_service_requests", "edit_others_service_requests", "publish_service_requests",
                "read_private_service_requests", "delete_service_requests", "delete_private_service_requests",
                "delete_published_service_requests", "delete_others_service_requests",
                "edit_private_service_requests", "edit_published_service_requests",
            ];
            foreach ($cpt_caps as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }

    private static function remove_roles_and_caps() {
        remove_role('expert_tech');

        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->remove_cap('respond_service_requests');
            $admin_role->remove_cap('create_expert_invoice');

            $cpt_caps = [
                "edit_service_request", "read_service_request", "delete_service_request",
                "edit_service_requests", "edit_others_service_requests", "publish_service_requests",
                "read_private_service_requests", "delete_service_requests", "delete_private_service_requests",
                "delete_published_service_requests", "delete_others_service_requests",
                "edit_private_service_requests", "edit_published_service_requests",
            ];
            foreach ($cpt_caps as $cap) {
                $admin_role->remove_cap($cap);
            }
        }
    }

    public function map_service_request_meta_caps($caps, $cap, $user_id, $args) {
        $cpt_meta_caps = [
            'edit_service_request',
            'read_service_request',
            'delete_service_request'
        ];

        if ( ! in_array( $cap, $cpt_meta_caps, true ) ) {
            return $caps;
        }

        $post_id = $args[0];
        $post = get_post( $post_id );

        if ( ! $post || 'service_request' !== $post->post_type ) {
            return $caps;
        }

        // Admins can do anything.
        if ( user_can( $user_id, 'manage_options' ) ) {
            return []; // Grant all capabilities for admins.
        }

        $post_author_id = (int) $post->post_author;

        switch ( $cap ) {
            case 'read_service_request':
                // Author can read their own request.
                if ( $user_id === $post_author_id ) {
                    return [];
                }
                // Assigned expert can read the request.
                $assigned_expert_id = (int) get_post_meta( $post_id, 'assigned_expert_id', true );
                if ( $user_id === $assigned_expert_id && user_can( $user_id, 'expert_tech' ) ) {
                    return [];
                }
                break;

            case 'edit_service_request':
                // Author can edit only in specific statuses.
                if ( $user_id === $post_author_id ) {
                    $allowed_statuses = [ 'drafting', 'awaiting_customer_selection' ];
                    if ( in_array( $post->post_status, $allowed_statuses, true ) ) {
                        return [];
                    }
                }
                break;

            case 'delete_service_request':
                // Author can delete only in specific statuses.
                if ( $user_id === $post_author_id ) {
                     $allowed_statuses = [ 'drafting', 'cancelled' ];
                     if ( in_array( $post->post_status, $allowed_statuses, true ) ) {
                        return [];
                    }
                }
                break;
        }

        // For all other cases, deny access.
        return ['do_not_allow'];
    }
}