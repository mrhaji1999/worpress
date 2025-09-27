<?php

namespace Boiler\Services;

class Notifications {

    public static function add($user_id, $message, $link = '', $type = 'info') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bs_notifications';

        if (empty($user_id) || empty($message)) {
            return false;
        }

        return $wpdb->insert(
            $table_name,
            [
                'user_id'    => absint($user_id),
                'message'    => wp_kses_post($message),
                'link'       => esc_url_raw($link),
                'type'       => sanitize_key($type),
                'is_read'    => 0,
                'created_at' => current_time('mysql', 1),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
            ]
        );
    }

    public static function get_unread_count($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bs_notifications';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_name WHERE user_id = %d AND is_read = 0",
            absint($user_id)
        ));

        return (int) $count;
    }

    public static function get_notifications($user_id, $limit = 10, $is_read = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bs_notifications';

        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            absint($user_id)
        );

        if (!is_null($is_read)) {
            $sql .= $wpdb->prepare(" AND is_read = %d", (int) $is_read);
        }

        $sql .= $wpdb->prepare(" ORDER BY created_at DESC LIMIT %d", absint($limit));

        return $wpdb->get_results($sql);
    }

    public static function mark_as_read($notification_id, $user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bs_notifications';

        return $wpdb->update(
            $table_name,
            ['is_read' => 1],
            [
                'id' => absint($notification_id),
                'user_id' => absint($user_id) // Ensure users can only mark their own.
            ],
            ['%d'],
            ['%d', '%d']
        );
    }
}