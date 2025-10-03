<?php
/**
 * Database operations for Secure Contact Form
 *
 * @package SecureContactForm
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all database operations
 */
class SCF_Database {

	/**
	 * Settings cache
	 *
	 * @var array|null
	 */
	private $settings_cache = null;

	/**
	 * Get table names
	 */
	private function get_table_names() {
		global $wpdb;
		return array(
			'settings' => $wpdb->prefix . 'scf_settings',
			'consents' => $wpdb->prefix . 'scf_ip_consents',
			'rate_limit' => $wpdb->prefix . 'scf_rate_limit',
		);
	}

	/**
	 * Activation - create tables and defaults
	 */
	public static function activate() {
		global $wpdb;
		$instance = new self();
		$tables = $instance->get_table_names();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Settings table
		$sql = "CREATE TABLE IF NOT EXISTS {$tables['settings']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			setting_key varchar(191) NOT NULL,
			setting_value longtext,
			PRIMARY KEY (id),
			UNIQUE KEY setting_key (setting_key)
		) {$charset_collate};";
		dbDelta( $sql );

		// IP Consents table
		$sql = "CREATE TABLE IF NOT EXISTS {$tables['consents']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ip_address varchar(45) NOT NULL,
			consented_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY ip_address (ip_address),
			KEY consented_at (consented_at)
		) {$charset_collate};";
		dbDelta( $sql );

		// Rate Limit table
		$sql = "CREATE TABLE IF NOT EXISTS {$tables['rate_limit']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ip_address varchar(45) NOT NULL,
			session_id varchar(64) NOT NULL,
			submission_count int(11) NOT NULL DEFAULT 0,
			last_submission datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY ip_session (ip_address, session_id),
			KEY last_submission (last_submission)
		) {$charset_collate};";
		dbDelta( $sql );

		// Set defaults
		$instance->set_defaults();
	}

	/**
	 * Set default settings
	 */
	private function set_defaults() {
		$defaults = array(
			// Email settings
			'recipient_email_1' => get_option( 'admin_email' ),
			'recipient_email_2' => '',
			'recipient_email_3' => '',
			'email_method' => 'wp_mail',
			
			// Required fields
			'subject_label' => 'Subject',
			'subject_placeholder' => 'Enter subject',
			'message_label' => 'Message',
			'message_placeholder' => 'Enter your message',
			'privacy_label' => 'I agree to the Privacy Policy',
			'privacy_link' => '',
			
			// Optional fields
			'enable_name' => '1',
			'name_label' => 'Name',
			'name_placeholder' => 'Your name',
			'enable_email' => '1',
			'email_label' => 'Email',
			'email_placeholder' => 'your@email.com',
			'enable_phone' => '0',
			'phone_label' => 'Phone',
			'phone_placeholder' => 'Your phone number',
			'enable_dropdown' => '0',
			'dropdown_label' => 'Select an option',
			'dropdown_option_1' => '',
			'dropdown_option_2' => '',
			'dropdown_option_3' => '',
			'dropdown_option_4' => '',
			'dropdown_option_5' => '',
			
			// Anti-spam settings
			'min_submit_time' => '3',
			'enable_security_question' => '0',
			'security_question' => 'What is 2 + 2?',
			'security_answer' => '4',
			
			// Rate limiting
			'rate_limit_max' => '5',
			'rate_limit_window' => '60',
			
			// Visual settings
			'form_bg_color' => '#ffffff',
			'form_border_color' => '#dddddd',
			'form_text_color' => '#333333',
			'button_bg_color' => '#0073aa',
			'button_text_color' => '#ffffff',
			'border_radius' => '4',
			
			// Other
			'cleanup_on_uninstall' => '0',
		);

		foreach ( $defaults as $key => $value ) {
			$existing = $this->get_setting( $key );
			if ( false === $existing ) {
				$this->save_setting( $key, $value );
			}
		}
	}

	/**
	 * Deactivation cleanup
	 */
	public static function deactivate() {
		global $wpdb;
		$instance = new self();
		$tables = $instance->get_table_names();
		
		// Clean old rate limit entries (older than 24 hours)
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM %i WHERE last_submission < DATE_SUB(NOW(), INTERVAL 24 HOUR)",
			$tables['rate_limit']
		) );
	}

	/**
	 * Get setting
	 *
	 * @param string $key Setting key
	 * @param mixed $default Default value
	 * @return mixed Setting value
	 */
	public function get_setting( $key, $default = false ) {
		global $wpdb;
		$tables = $this->get_table_names();

		$value = $wpdb->get_var( $wpdb->prepare(
			"SELECT setting_value FROM %i WHERE setting_key = %s",
			$tables['settings'],
			$key
		) );

		if ( null === $value ) {
			return $default;
		}

		return maybe_unserialize( $value );
	}

	/**
	 * Get all settings
	 *
	 * @return array All settings with defaults
	 */
	public function get_all_settings() {
		if ( null !== $this->settings_cache ) {
			return $this->settings_cache;
		}

		global $wpdb;
		$tables = $this->get_table_names();

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT setting_key, setting_value FROM %i",
			$tables['settings']
		), ARRAY_A );

		$settings = array();
		
		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$settings[ $row['setting_key'] ] = maybe_unserialize( $row['setting_value'] );
			}
		}

		// Return defaults for any missing keys
		$defaults = $this->get_default_settings();
		$settings = array_merge( $defaults, $settings );

		$this->settings_cache = $settings;
		return $settings;
	}

	/**
	 * Get default settings array
	 *
	 * @return array Default settings
	 */
	private function get_default_settings() {
		return array(
			'recipient_email_1' => get_option( 'admin_email' ),
			'recipient_email_2' => '',
			'recipient_email_3' => '',
			'email_method' => 'wp_mail',
			'subject_label' => 'Subject',
			'subject_placeholder' => 'Enter subject',
			'message_label' => 'Message',
			'message_placeholder' => 'Enter your message',
			'privacy_label' => 'I agree to the Privacy Policy',
			'privacy_link' => '',
			'enable_name' => '1',
			'name_label' => 'Name',
			'name_placeholder' => 'Your name',
			'enable_email' => '1',
			'email_label' => 'Email',
			'email_placeholder' => 'your@email.com',
			'enable_phone' => '0',
			'phone_label' => 'Phone',
			'phone_placeholder' => 'Your phone number',
			'enable_dropdown' => '0',
			'dropdown_label' => 'Select an option',
			'dropdown_option_1' => '',
			'dropdown_option_2' => '',
			'dropdown_option_3' => '',
			'dropdown_option_4' => '',
			'dropdown_option_5' => '',
			'min_submit_time' => '3',
			'enable_security_question' => '0',
			'security_question' => 'What is 2 + 2?',
			'security_answer' => '4',
			'rate_limit_max' => '5',
			'rate_limit_window' => '60',
			'form_bg_color' => '#ffffff',
			'form_border_color' => '#dddddd',
			'form_text_color' => '#333333',
			'button_bg_color' => '#0073aa',
			'button_text_color' => '#ffffff',
			'border_radius' => '4',
			'cleanup_on_uninstall' => '0',
		);
	}

	/**
	 * Save setting
	 *
	 * @param string $key Setting key
	 * @param mixed $value Setting value
	 * @return bool|WP_Error Success or error
	 */
	public function save_setting( $key, $value ) {
		global $wpdb;
		$tables = $this->get_table_names();

		$result = $wpdb->replace(
			$tables['settings'],
			array(
				'setting_key' => $key,
				'setting_value' => maybe_serialize( $value ),
			),
			array( '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to save setting', 'secure-contact-form' ) );
		}

		$this->settings_cache = null;
		return true;
	}

	/**
	 * Check if IP has consented
	 *
	 * @param string $ip IP address
	 * @return bool Has consented
	 */
	public function has_ip_consented( $ip ) {
		global $wpdb;
		$tables = $this->get_table_names();

		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i WHERE ip_address = %s",
			$tables['consents'],
			$ip
		) );

		return $result > 0;
	}

	/**
	 * Save IP consent
	 *
	 * @param string $ip IP address
	 * @return bool|WP_Error Success or error
	 */
	public function save_ip_consent( $ip ) {
		global $wpdb;
		$tables = $this->get_table_names();

		$result = $wpdb->insert(
			$tables['consents'],
			array(
				'ip_address' => $ip,
				'consented_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to save consent', 'secure-contact-form' ) );
		}

		return true;
	}

	/**
	 * Check rate limit
	 *
	 * @param string $ip IP address
	 * @param string $session_id Session ID
	 * @return bool Within limit
	 */
	public function check_rate_limit( $ip, $session_id ) {
		global $wpdb;
		$tables = $this->get_table_names();
		$settings = $this->get_all_settings();

		$max = absint( $settings['rate_limit_max'] );
		$window = absint( $settings['rate_limit_window'] );

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT submission_count FROM %i 
			WHERE ip_address = %s 
			AND session_id = %s 
			AND last_submission > DATE_SUB(NOW(), INTERVAL %d MINUTE)",
			$tables['rate_limit'],
			$ip,
			$session_id,
			$window
		) );

		return ( null === $count || $count < $max );
	}

	/**
	 * Record submission
	 *
	 * @param string $ip IP address
	 * @param string $session_id Session ID
	 * @return bool|WP_Error Success or error
	 */
	public function record_submission( $ip, $session_id ) {
		global $wpdb;
		$tables = $this->get_table_names();

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT submission_count FROM %i 
			WHERE ip_address = %s AND session_id = %s",
			$tables['rate_limit'],
			$ip,
			$session_id
		) );

		if ( null === $existing ) {
			$result = $wpdb->insert(
				$tables['rate_limit'],
				array(
					'ip_address' => $ip,
					'session_id' => $session_id,
					'submission_count' => 1,
					'last_submission' => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%d', '%s' )
			);
		} else {
			$result = $wpdb->update(
				$tables['rate_limit'],
				array(
					'submission_count' => $existing + 1,
					'last_submission' => current_time( 'mysql' ),
				),
				array(
					'ip_address' => $ip,
					'session_id' => $session_id,
				),
				array( '%d', '%s' ),
				array( '%s', '%s' )
			);
		}

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to record submission', 'secure-contact-form' ) );
		}

		return true;
	}
}
