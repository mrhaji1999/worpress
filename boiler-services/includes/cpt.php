<?php

namespace Boiler\Services;

class CPT {

    public function __construct() {
        add_action('init', [$this, 'register_all']);
    }

    public function register_all() {
        $this->register_service_post_type();
        $this->register_service_request_cpt();
        $this->register_service_taxonomies();
        $this->register_custom_post_statuses();
    }

    private function register_service_post_type() {
        $labels = [
            'name'                  => _x( 'Services', 'Post Type General Name', 'boiler-services' ),
			'singular_name'         => _x( 'Service', 'Post Type Singular Name', 'boiler-services' ),
			'menu_name'             => __( 'Boiler Services', 'boiler-services' ),
        ];
		$args   = [
			'label'                 => __( 'Service', 'boiler-services' ),
			'labels'                => $labels,
			'supports'              => [ 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields' ],
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 5,
			'menu_icon'             => 'dashicons-hammer',
			'can_export'            => true,
			'has_archive'           => 'services',
			'publicly_queryable'    => true,
			'capability_type'       => 'post',
			'show_in_rest'          => true,
            'rewrite'               => ['slug' => 'services', 'with_front' => true],
		];
		register_post_type( 'service', $args );
    }

    private function register_service_request_cpt() {
        $labels = [
            'name'                  => _x( 'Service Requests', 'Post Type General Name', 'boiler-services' ),
            'singular_name'         => _x( 'Service Request', 'Post Type Singular Name', 'boiler-services' ),
            'menu_name'             => __( 'Service Requests', 'boiler-services' ),
        ];
        $args = [
            'label'                 => __( 'Service Request', 'boiler-services' ),
            'labels'                => $labels,
            'supports'              => [ 'title', 'author', 'custom-fields' ],
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 6,
            'menu_icon'             => 'dashicons-clipboard',
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'service_request',
            'map_meta_cap'          => true,
            'show_in_rest'          => true,
        ];
        register_post_type( 'service_request', $args );
    }

    private function register_service_taxonomies() {
        // Service Category
        register_taxonomy( 'service_category', [ 'service' ], [
            'labels' => ['name' => __('Service Categories', 'boiler-services'), 'singular_name' => __('Service Category', 'boiler-services')],
            'hierarchical' => true, 'public' => true, 'show_ui' => true, 'show_admin_column' => true, 'show_in_rest' => true,
            'rewrite' => ['slug' => 'service-category'],
        ]);

        // Boiler Type
        register_taxonomy( 'boiler_type', [ 'service' ], [
            'labels' => ['name' => __('Boiler Types', 'boiler-services'), 'singular_name' => __('Boiler Type', 'boiler-services')],
            'hierarchical' => true, 'public' => true, 'show_ui' => true, 'show_admin_column' => true, 'show_in_rest' => true,
            'rewrite' => ['slug' => 'boiler-type'],
        ]);

        // Service Tag
        register_taxonomy( 'service_tag', [ 'service' ], [
            'labels' => ['name' => __('Service Tags', 'boiler-services'), 'singular_name' => __('Service Tag', 'boiler-services')],
            'hierarchical' => false, 'public' => true, 'show_ui' => true, 'show_admin_column' => true, 'show_in_rest' => true,
            'rewrite' => ['slug' => 'service-tag'],
        ]);
    }

    private function register_custom_post_statuses() {
        $statuses = [
            'drafting' => __('Drafting', 'boiler-services'),
            'awaiting_experts' => __('Awaiting Experts', 'boiler-services'),
            'awaiting_customer_selection' => __('Awaiting Customer Selection', 'boiler-services'),
            'assigned' => __('Assigned', 'boiler-services'),
            'completed' => __('Completed', 'boiler-services'),
            'cancelled' => __('Cancelled', 'boiler-services'),
        ];

        foreach ($statuses as $status => $label) {
            register_post_status($status, [
                'label'                     => _x( $label, 'post status', 'boiler-services' ),
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( "$label <span class=\"count\">(%s)</span>", "$label <span class=\"count\">(%s)</span>", 'boiler-services' ),
            ]);
        }
    }
}