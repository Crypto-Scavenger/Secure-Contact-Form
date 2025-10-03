<?php
/**
 * Uninstall handler for Secure Contact Form
 *
 * @package SecureContactForm
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Get table names
$settings_table = $wpdb->prefix . 'scf_settings';
$consents_table = $wpdb->prefix . 'scf_ip_consents';
$rate_limit_table = $wpdb->prefix . 'scf_rate_limit';

// Check cleanup preference
$cleanup = $wpdb->get_var( $wpdb->prepare(
	"SELECT setting_value FROM %i WHERE setting_key = %s",
	$settings_table,
	'cleanup_on_uninstall'
) );

if ( '1' === $cleanup ) {
	// Drop all tables
	$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $settings_table ) );
	$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $consents_table ) );
	$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $rate_limit_table ) );
	
	// Clear object cache
	wp_cache_flush();
}
