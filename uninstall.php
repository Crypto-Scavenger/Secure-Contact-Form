<?php
/**
 * Uninstall script
 *
 * @package SecureContactForm
 * @since 1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Include database class
require_once plugin_dir_path( __FILE__ ) . 'includes/class-database.php';

// Run cleanup
SCF_Database::uninstall();
