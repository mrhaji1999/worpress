<?php

namespace Boiler\Services;

class Matching {

    /**
     * Finds experts who should be notified about a new service request.
     *
     * @param int $request_id The ID of the service request.
     * @return int[] An array of expert user IDs.
     */
    public static function find_matching_experts($request_id) {
        $priority = get_post_meta($request_id, 'priority', true);
        $request_city = get_post_meta($request_id, 'billing_city', true);

        $args = [
            'role'    => 'expert_tech',
            'fields'  => 'ID',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'expert_status',
                    'value' => 'approved',
                    'compare' => '=',
                ]
            ],
        ];

        if ($priority === 'fastest' && !empty($request_city)) {
            // Find approved experts covering the specific city.
            $args['meta_query'][] = [
                'key' => 'expert_coverage_cities',
                'value' => $request_city,
                'compare' => 'LIKE', // Assumes cities are stored comma-separated.
            ];
        } elseif ($priority === 'best_experts') {
            // Find approved experts with Grade A or B.
            $args['meta_query'][] = [
                'key' => 'expert_grade',
                'value' => ['A', 'B'],
                'compare' => 'IN',
            ];
        } else {
            // Default case or fallback: notify all approved experts.
        }

        $user_query = new \WP_User_Query($args);

        return $user_query->get_results();
    }
}