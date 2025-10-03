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

		// Settings table
		$sql = $wpdb->prepare(
			"CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				setting_key varchar(191) NOT NULL,
				setting_value longtext,
				PRIMARY KEY (id),
				UNIQUE KEY setting_key (setting_key)
			) %s",
			$tables['settings'],
			$charset_collate
		);
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// IP Consents table
		$sql = $wpdb->prepare(
			"CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				ip_address varchar(45) NOT NULL,
				consented_at datetime NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY ip_address (ip_address),
				KEY consented_at (consented_at)
			) %s",
			$tables['consents'],
			$charset_collate
		);
		dbDelta( $sql );

		// Rate Limit table
		$sql = $wpdb->prepare(
			"CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				ip_address varchar(45) NOT NULL,
				session_id varchar(64) NOT NULL,
				submission_count int(11) NOT NULL DEFAULT 0,
				last_submission datetime NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY ip_session (ip_address, session_id),
				KEY last_submission (last_submission)
			) %s",
			$tables['rate_limit'],
			$charset_collate
		);
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
			if ( false === $this->get_setting( $key ) ) {
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
	 * @return array All settings
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

		if ( false === $results ) {
			return array();
		}

		$settings = array();
		foreach ( $results as $row ) {
			$settings[ $row['setting_key'] ] = maybe_unserialize( $row['setting_value'] );
		}

		$this->settings_cache = $settings;
		return $settings;
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

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i WHERE ip_address = %s",
			$tables['consents'],
			$ip
		) );

		return $exists > 0;
	}

	/**
	 * Record IP consent
	 *
	 * @param string $ip IP address
	 * @return bool|WP_Error Success or error
	 */
	public function record_consent( $ip ) {
		global $wpdb;
		$tables = $this->get_table_names();

		$result = $wpdb->replace(
			$tables['consents'],
			array(
				'ip_address' => $ip,
				'consented_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to record consent', 'secure-contact-form' ) );
		}

		return true;
	}

	/**
	 * Check rate limit
	 *
	 * @param string $ip IP address
	 * @param string $session Session ID
	 * @return bool Is within limit
	 */
	public function check_rate_limit( $ip, $session ) {
		global $wpdb;
		$tables = $this->get_table_names();
		$settings = $this->get_all_settings();

		$max_submissions = intval( $settings['rate_limit_max'] );
		$window_minutes = intval( $settings['rate_limit_window'] );

		$record = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE ip_address = %s AND session_id = %s",
			$tables['rate_limit'],
			$ip,
			$session
		) );

		if ( ! $record ) {
			return true;
		}

		$last_submission = strtotime( $record->last_submission );
		$window_start = time() - ( $window_minutes * 60 );

		if ( $last_submission < $window_start ) {
			return true;
		}

		return $record->submission_count < $max_submissions;
	}

	/**
	 * Record submission for rate limiting
	 *
	 * @param string $ip IP address
	 * @param string $session Session ID
	 * @return bool|WP_Error Success or error
	 */
	public function record_submission( $ip, $session ) {
		global $wpdb;
		$tables = $this->get_table_names();
		$settings = $this->get_all_settings();

		$window_minutes = intval( $settings['rate_limit_window'] );
		$window_start = gmdate( 'Y-m-d H:i:s', time() - ( $window_minutes * 60 ) );

		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE ip_address = %s AND session_id = %s AND last_submission >= %s",
			$tables['rate_limit'],
			$ip,
			$session,
			$window_start
		) );

		if ( $existing ) {
			$result = $wpdb->update(
				$tables['rate_limit'],
				array(
					'submission_count' => $existing->submission_count + 1,
					'last_submission' => current_time( 'mysql' ),
				),
				array( 'id' => $existing->id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		} else {
			$result = $wpdb->replace(
				$tables['rate_limit'],
				array(
					'ip_address' => $ip,
					'session_id' => $session,
					'submission_count' => 1,
					'last_submission' => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%d', '%s' )
			);
		}

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to record submission', 'secure-contact-form' ) );
		}

		return true;
	}
}
