<?php

namespace Boiler\Services;

class Database {

    public static function create_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'expert_request_responses';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            request_id bigint(20) UNSIGNED NOT NULL,
            expert_id bigint(20) UNSIGNED NOT NULL,
            response varchar(10) NOT NULL DEFAULT '',
            response_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_response (request_id, expert_id),
            KEY request_id (request_id),
            KEY expert_id (expert_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        // We can add the notifications table here as well, as per prompt 15
        $notifications_table_name = $wpdb->prefix . 'bs_notifications';
        $sql_notifications = "CREATE TABLE $notifications_table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            message text NOT NULL,
            link varchar(255) DEFAULT '' NOT NULL,
            type varchar(20) DEFAULT 'info' NOT NULL,
            is_read tinyint(1) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY is_read (is_read)
        ) $charset_collate;";
        dbDelta($sql_notifications);
    }
}