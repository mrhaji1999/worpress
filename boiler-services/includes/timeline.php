<?php

namespace Boiler\Services;

class Timeline {

    public function __construct() {
        // Hook into status transitions to log them automatically.
        add_action('transition_post_status', [$this, 'log_status_change'], 10, 3);
    }

    /**
     * Adds a new event to a service request's timeline.
     *
     * @param int    $request_id The ID of the service request post.
     * @param string $message The message to log for the event.
     * @param string $event_type A key for the type of event (e.g., 'system', 'expert_action').
     * @param int|null $actor_id The user ID of the person performing the action.
     */
    public static function add_event($request_id, $message, $event_type = 'system', $actor_id = null) {
        if (get_post_type($request_id) !== 'service_request') {
            return;
        }

        $timeline = get_post_meta($request_id, '_service_request_timeline', true);
        if (!is_array($timeline)) {
            $timeline = [];
        }

        $actor_name = __('System', 'boiler-services');
        if (is_null($actor_id)) {
            $actor_id = get_current_user_id();
        }

        if ($actor_id) {
            $user = get_userdata($actor_id);
            if ($user) {
                $actor_name = $user->display_name;
            }
        }

        $timeline[] = [
            'timestamp'  => time(),
            'message'    => $message,
            'type'       => $event_type,
            'actor_id'   => $actor_id,
            'actor_name' => $actor_name,
        ];

        update_post_meta($request_id, '_service_request_timeline', $timeline);
    }

    /**
     * Logs post status changes for service requests.
     */
    public function log_status_change($new_status, $old_status, $post) {
        if ($post->post_type !== 'service_request' || $new_status === $old_status) {
            return;
        }

        $status_obj_new = get_post_status_object($new_status);
        $status_obj_old = get_post_status_object($old_status);
        $message = '';

        if ($old_status === 'auto-draft' && $status_obj_new) {
            $message = sprintf(__('Request created with status: %s', 'boiler-services'), $status_obj_new->label);
        } elseif($status_obj_new && $status_obj_old) {
            $message = sprintf(
                __('Status changed from %s to %s', 'boiler-services'),
                $status_obj_old->label,
                $status_obj_new->label
            );
        }

        if ($message) {
            self::add_event($post->ID, $message, 'status_change');
        }

        // Notify experts when request is ready for them
        if ($new_status === 'awaiting_experts') {
            $experts = Matching::find_matching_experts($post->ID);
            if (!empty($experts)) {
                $notification_message = sprintf(__('A new service request (#%d) matching your profile is available.', 'boiler-services'), $post->ID);
                self::add_event($post->ID, sprintf(__('Notifying %d matching experts.', 'boiler-services'), count($experts)));
                foreach ($experts as $expert_id) {
                    Notifications::add($expert_id, $notification_message, get_permalink($post->ID));
                }
            }
        }
    }
}