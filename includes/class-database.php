<?php
/**
 * Database operations
 *
 * @package SecureContactForm
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SCF_Database {

	/**
	 * Settings cache
	 *
	 * @var array|null
	 */
	private $settings_cache = null;

	/**
	 * Table existence verified
	 *
	 * @var bool|null
	 */
	private $tables_verified = null;

	/**
	 * Get table names
	 *
	 * @return array
	 */
	private function get_table_names() {
		global $wpdb;
		return array(
			'settings'    => $wpdb->prefix . 'scf_settings',
			'consent'     => $wpdb->prefix . 'scf_consent',
			'rate_limits' => $wpdb->prefix . 'scf_rate_limits',
		);
	}

	/**
	 * Ensure tables exist (CRITICAL: Call before every query)
	 *
	 * @return bool
	 */
	private function ensure_tables_exist() {
		if ( null !== $this->tables_verified ) {
			return $this->tables_verified;
		}

		global $wpdb;
		$tables        = $this->get_table_names();
		$all_exist     = true;
		
		foreach ( $tables as $table ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			) );
			
			if ( $table !== $table_exists ) {
				$all_exist = false;
				break;
			}
		}
		
		if ( ! $all_exist ) {
			$this->create_tables();
			
			// Verify creation succeeded
			$all_exist = true;
			foreach ( $tables as $table ) {
				$table_exists = $wpdb->get_var( $wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$table
				) );
				
				if ( $table !== $table_exists ) {
					$all_exist = false;
					break;
				}
			}
		}
		
		$this->tables_verified = $all_exist;
		return $this->tables_verified;
	}

	/**
	 * Activate plugin - create tables
	 */
	public static function activate() {
		$instance = new self();
		$instance->create_tables();
		$instance->initialize_defaults();
	}

	/**
	 * Deactivate plugin
	 */
	public static function deactivate() {
		// Clear any scheduled events if needed
	}

	/**
	 * Create database tables
	 */
	private function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$tables          = $this->get_table_names();
		
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		
		// Settings table
		$sql = $wpdb->prepare(
			'CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				setting_key varchar(191) NOT NULL,
				setting_value longtext,
				PRIMARY KEY (id),
				UNIQUE KEY setting_key (setting_key)
			)',
			$tables['settings']
		) . ' ' . $charset_collate;
		
		dbDelta( $sql );
		
		// Consent tracking table
		$sql = $wpdb->prepare(
			'CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				ip_address varchar(45) NOT NULL,
				consent_date datetime NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY ip_address (ip_address),
				KEY consent_date (consent_date)
			)',
			$tables['consent']
		) . ' ' . $charset_collate;
		
		dbDelta( $sql );
		
		// Rate limiting table
		$sql = $wpdb->prepare(
			'CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				ip_address varchar(45) NOT NULL,
				submission_time datetime NOT NULL,
				PRIMARY KEY (id),
				KEY ip_address (ip_address),
				KEY submission_time (submission_time)
			)',
			$tables['rate_limits']
		) . ' ' . $charset_collate;
		
		dbDelta( $sql );
	}

	/**
	 * Initialize default settings
	 */
	private function initialize_defaults() {
		$defaults = array(
			// Email settings
			'email_recipients'  => get_option( 'admin_email' ),
			'email_method'      => 'wp_mail',
			
			// Field settings
			'enable_name'       => '1',
			'enable_email'      => '1',
			'enable_phone'      => '0',
			'enable_dropdown'   => '0',
			
			// Field labels
			'label_name'        => __( 'Name', 'secure-contact-form' ),
			'label_email'       => __( 'Email', 'secure-contact-form' ),
			'label_phone'       => __( 'Phone', 'secure-contact-form' ),
			'label_subject'     => __( 'Subject', 'secure-contact-form' ),
			'label_message'     => __( 'Message', 'secure-contact-form' ),
			'label_dropdown'    => __( 'Select Option', 'secure-contact-form' ),
			'label_privacy'     => __( 'I accept the Privacy Policy', 'secure-contact-form' ),
			
			// Placeholders
			'placeholder_name'    => __( 'Your name', 'secure-contact-form' ),
			'placeholder_email'   => __( 'your@email.com', 'secure-contact-form' ),
			'placeholder_phone'   => __( 'Your phone number', 'secure-contact-form' ),
			'placeholder_subject' => __( 'Subject of your message', 'secure-contact-form' ),
			'placeholder_message' => __( 'Type your message here...', 'secure-contact-form' ),
			
			// Dropdown options
			'dropdown_options'  => __( "Option 1\nOption 2\nOption 3", 'secure-contact-form' ),
			
			// Anti-spam settings
			'enable_security_question' => '0',
			'security_question'        => __( 'What is 2 + 2?', 'secure-contact-form' ),
			'security_answer'          => '4',
			'min_submission_time'      => '3',
			'rate_limit_max'           => '5',
			'rate_limit_window'        => '60',
			
			// Style settings
			'form_bg_color'     => '#1a1a1a',
			'form_border_color' => '#d11c1c',
			'form_text_color'   => '#ffffff',
			'button_bg_color'   => '#d11c1c',
			'button_text_color' => '#ffffff',
			'border_radius'     => '4',
			
			// Other settings
			'cleanup_on_uninstall' => '1',
		);
		
		foreach ( $defaults as $key => $value ) {
			if ( false === $this->get_setting( $key ) ) {
				$this->save_setting( $key, $value );
			}
		}
	}

	/**
	 * Get single setting
	 *
	 * @param string $key Setting key
	 * @param mixed  $default Default value
	 * @return mixed
	 */
	public function get_setting( $key, $default = false ) {
		if ( ! $this->ensure_tables_exist() ) {
			return $default;
		}

		global $wpdb;
		$tables = $this->get_table_names();
		
		$value = $wpdb->get_var( $wpdb->prepare(
			'SELECT setting_value FROM %i WHERE setting_key = %s',
			$tables['settings'],
			$key
		) );
		
		if ( null === $value ) {
			return $default;
		}
		
		return maybe_unserialize( $value );
	}

	/**
	 * Get all settings (with caching)
	 *
	 * @return array
	 */
	public function get_all_settings() {
		if ( null !== $this->settings_cache ) {
			return $this->settings_cache;
		}

		if ( ! $this->ensure_tables_exist() ) {
			return array();
		}

		global $wpdb;
		$tables = $this->get_table_names();
		
		$results = $wpdb->get_results(
			$wpdb->prepare( 'SELECT setting_key, setting_value FROM %i', $tables['settings'] ),
			ARRAY_A
		);
		
		if ( ! is_array( $results ) ) {
			return array();
		}
		
		$settings = array();
		foreach ( $results as $row ) {
			$key   = $row['setting_key'] ?? '';
			$value = $row['setting_value'] ?? '';
			if ( ! empty( $key ) ) {
				$settings[ $key ] = maybe_unserialize( $value );
			}
		}
		
		$this->settings_cache = $settings;
		return $settings;
	}

	/**
	 * Save setting
	 *
	 * @param string $key Setting key
	 * @param mixed  $value Setting value
	 * @return bool
	 */
	public function save_setting( $key, $value ) {
		if ( ! $this->ensure_tables_exist() ) {
			return false;
		}

		global $wpdb;
		$tables = $this->get_table_names();
		
		$result = $wpdb->replace(
			$tables['settings'],
			array(
				'setting_key'   => $key,
				'setting_value' => maybe_serialize( $value ),
			),
			array( '%s', '%s' )
		);
		
		if ( false !== $result ) {
			$this->settings_cache = null;
		}
		
		return false !== $result;
	}

	/**
	 * Check if IP has consented
	 *
	 * @param string $ip_address IP address
	 * @return bool
	 */
	public function has_consent( $ip_address ) {
		if ( ! $this->ensure_tables_exist() ) {
			return false;
		}

		global $wpdb;
		$tables = $this->get_table_names();
		
		$consent = $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM %i WHERE ip_address = %s',
			$tables['consent'],
			$ip_address
		) );
		
		return null !== $consent;
	}

	/**
	 * Record consent
	 *
	 * @param string $ip_address IP address
	 * @return bool
	 */
	public function record_consent( $ip_address ) {
		if ( ! $this->ensure_tables_exist() ) {
			return false;
		}

		global $wpdb;
		$tables = $this->get_table_names();
		
		$result = $wpdb->replace(
			$tables['consent'],
			array(
				'ip_address'   => $ip_address,
				'consent_date' => current_time( 'mysql' ),
			),
			array( '%s', '%s' )
		);
		
		return false !== $result;
	}

	/**
	 * Check rate limit
	 *
	 * @param string $ip_address IP address
	 * @param int    $max_attempts Maximum attempts
	 * @param int    $window_minutes Time window in minutes
	 * @return bool True if allowed, false if rate limited
	 */
	public function check_rate_limit( $ip_address, $max_attempts, $window_minutes ) {
		if ( ! $this->ensure_tables_exist() ) {
			return true;
		}

		global $wpdb;
		$tables = $this->get_table_names();
		
		// Clean old records
		$wpdb->query( $wpdb->prepare(
			'DELETE FROM %i WHERE submission_time < DATE_SUB(NOW(), INTERVAL %d MINUTE)',
			$tables['rate_limits'],
			$window_minutes
		) );
		
		// Count recent submissions
		$count = $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE ip_address = %s AND submission_time > DATE_SUB(NOW(), INTERVAL %d MINUTE)',
			$tables['rate_limits'],
			$ip_address,
			$window_minutes
		) );
		
		return (int) ( $count ?? 0 ) < $max_attempts;
	}

	/**
	 * Record submission
	 *
	 * @param string $ip_address IP address
	 * @return bool
	 */
	public function record_submission( $ip_address ) {
		if ( ! $this->ensure_tables_exist() ) {
			return false;
		}

		global $wpdb;
		$tables = $this->get_table_names();
		
		$result = $wpdb->insert(
			$tables['rate_limits'],
			array(
				'ip_address'      => $ip_address,
				'submission_time' => current_time( 'mysql' ),
			),
			array( '%s', '%s' )
		);
		
		return false !== $result;
	}

	/**
	 * Cleanup on uninstall
	 */
	public static function uninstall() {
		$instance = new self();
		$cleanup  = $instance->get_setting( 'cleanup_on_uninstall', '1' );
		
		if ( '1' !== $cleanup ) {
			return;
		}
		
		global $wpdb;
		$tables = $instance->get_table_names();
		
		foreach ( $tables as $table ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		}
	}
}
