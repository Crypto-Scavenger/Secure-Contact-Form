<?php
/**
 * Plugin Name: Secure Contact Form
 * Description: Secure, customizable contact form with advanced anti-spam protection and GDPR compliance
 * Version: 1.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Text Domain: secure-contact-form
 * Domain Path: /languages
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants
define( 'SCF_VERSION', '1.0.0' );
define( 'SCF_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCF_URL', plugin_dir_url( __FILE__ ) );

// Include classes
require_once SCF_DIR . 'includes/class-database.php';
require_once SCF_DIR . 'includes/class-core.php';
require_once SCF_DIR . 'includes/class-admin.php';

/**
 * Initialize plugin
 */
function scf_init() {
	$database = new SCF_Database();
	$core = new SCF_Core( $database );
	
	if ( is_admin() ) {
		new SCF_Admin( $database );
	}
}
add_action( 'plugins_loaded', 'scf_init' );

/**
 * Activation hook
 */
register_activation_hook( __FILE__, array( 'SCF_Database', 'activate' ) );

/**
 * Deactivation hook
 */
register_deactivation_hook( __FILE__, array( 'SCF_Database', 'deactivate' ) );
