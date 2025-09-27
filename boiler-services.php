<?php
/**
 * Plugin Name: Boiler Services Management
 * Description: A custom plugin to manage boiler services, technicians, and requests.
 * Version: 1.2.0
 * Author: Jules AI Assistant
 * Author URI: https://example.com
 * Text Domain: boiler-services
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'BOILER_SERVICES_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// Simple autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Boiler\\Services\\';
    $base_dir = __DIR__ . '/boiler-services/includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', strtolower($relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Main plugin ignition
function boiler_services_manager() {
	return Boiler\Services\Core::instance();
}

boiler_services_manager();


// Activation/Deactivation hooks
register_activation_hook( __FILE__, function() {
    Boiler\Services\Roles::on_activation();
    Boiler\Services\Database::create_tables();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function() {
    Boiler\Services\Roles::on_deactivation();
    flush_rewrite_rules();
} );