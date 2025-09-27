<?php

namespace Boiler\Services;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Service_Requests_List_Table extends \WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => __('Service Request', 'boiler-services'),
            'plural'   => __('Service Requests', 'boiler-services'),
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'title'         => __('Request', 'boiler-services'),
            'author'        => __('Customer', 'boiler-services'),
            'status'        => __('Status', 'boiler-services'),
            'assigned_expert' => __('Assigned Expert', 'boiler-services'),
            'city'          => __('City', 'boiler-services'),
            'priority'      => __('Priority', 'boiler-services'),
            'date'          => __('Date', 'boiler-services'),
        ];
    }

    protected function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        echo '<div class="alignleft actions">';

        // Status Filter
        $current_status = $_GET['status_filter'] ?? '';
        $statuses = get_post_stati(['show_in_admin_all_list' => true], 'objects');
        echo '<select name="status_filter">';
        echo '<option value="">' . __('All Statuses', 'boiler-services') . '</option>';
        foreach ($statuses as $status) {
            if ($status->name === 'auto-draft') continue;
            printf('<option value="%s"%s>%s</option>', esc_attr($status->name), selected($current_status, $status->name, false), esc_html($status->label));
        }
        echo '</select>';

        // Category Filter
        wp_dropdown_categories([
            'show_option_all' => __('All Categories', 'boiler-services'),
            'taxonomy'        => 'service_category',
            'name'            => 'category_filter',
            'selected'        => $_GET['category_filter'] ?? 0,
            'hierarchical'    => true,
            'show_count'      => false,
            'hide_empty'      => true,
        ]);

        submit_button(__('Filter'), 'button', 'filter_action', false);

        // Add Export Button
        $export_url = add_query_arg([
            'action' => 'export_requests_csv',
            '_wpnonce' => wp_create_nonce('export_requests_nonce'),
            'status_filter' => $_GET['status_filter'] ?? '',
            'category_filter' => $_GET['category_filter'] ?? '',
        ]);
        echo ' <a href="' . esc_url($export_url) . '" class="button">' . esc_html__('Export CSV', 'boiler-services') . '</a>';

        echo '</div>';
    }

    public function prepare_items() {
        $this->_column_headers = [$this->get_columns(), [], []];

        $per_page = $this->get_items_per_page('requests_per_page', 20);
        $current_page = $this->get_pagenum();

        $args = [
            'post_type'      => 'service_request',
            'posts_per_page' => $per_page,
            'offset'         => ($current_page - 1) * $per_page,
            'post_status'    => 'any',
        ];

        // Apply filters
        if (!empty($_GET['status_filter'])) {
            $args['post_status'] = sanitize_key($_GET['status_filter']);
        }
        if (!empty($_GET['category_filter'])) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'service_category',
                    'field'    => 'term_id',
                    'terms'    => (int) $_GET['category_filter'],
                ],
            ];
        }

        $query = new \WP_Query($args);

        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page'    => $per_page,
            'total_pages' => $query->max_num_pages,
        ]);

        $this->items = $query->posts;
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'date':
                return date_i18n(get_option('date_format'), strtotime($item->post_date));
            case 'author':
                $author = get_userdata($item->post_author);
                return $author ? esc_html($author->display_name) : __('Guest', 'boiler-services');
            case 'status':
                $status_obj = get_post_status_object($item->post_status);
                return $status_obj ? esc_html($status_obj->label) : esc_html($item->post_status);
            case 'assigned_expert':
                $expert_id = get_post_meta($item->ID, 'assigned_expert_id', true);
                if ($expert_id) {
                    $expert = get_userdata($expert_id);
                    return $expert ? esc_html($expert->display_name) : '—';
                }
                return '—';
            case 'city':
                return esc_html(get_post_meta($item->ID, 'billing_city', true) ?: '—');
            case 'priority':
                 return esc_html(get_post_meta($item->ID, 'priority', true) ?: '—');
            default:
                return '—';
        }
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="request_ids[]" value="%d" />', $item->ID);
    }

    public function column_title($item) {
        $view_url = add_query_arg(['post' => $item->ID, 'action' => 'edit'], admin_url('post.php'));
        $title = sprintf('<a href="%s"><strong>#%d: %s</strong></a>', esc_url($view_url), $item->ID, esc_html($item->post_title));

        $actions = [
            'edit' => sprintf('<a href="%s">%s</a>', esc_url($view_url), __('Edit')),
            'timeline' => sprintf('<a href="#" class="view-timeline" data-id="%d">%s</a>', $item->ID, __('View Timeline', 'boiler-services')),
            'delete' => sprintf('<a href="%s" class="submitdelete">%s</a>', get_delete_post_link($item->ID, '', true), __('Trash')),
        ];
        return $title . $this->row_actions($actions);
    }
}