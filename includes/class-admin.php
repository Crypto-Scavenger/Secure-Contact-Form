<?php
/**
 * Admin functionality
 *
 * @package SecureContactForm
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SCF_Admin {

	/**
	 * Database instance
	 *
	 * @var SCF_Database
	 */
	private $database;

	/**
	 * Settings cache
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Constructor
	 *
	 * @param SCF_Database $database Database instance
	 */
	public function __construct( $database ) {
		$this->database = $database;
		
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_scf_save_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Get settings (lazy loading)
	 *
	 * @return array
	 */
	private function get_settings() {
		if ( null === $this->settings ) {
			$this->settings = $this->database->get_all_settings();
		}
		return $this->settings;
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Secure Contact Form', 'secure-contact-form' ),
			__( 'Contact Form', 'secure-contact-form' ),
			'manage_options',
			'secure-contact-form',
			array( $this, 'render_admin_page' ),
			'dashicons-email',
			100
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current page hook
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_secure-contact-form' !== $hook ) {
			return;
		}
		
		wp_enqueue_style( 'wp-color-picker' );
		
		wp_enqueue_style(
			'scf-admin',
			SCF_URL . 'assets/admin.css',
			array( 'wp-color-picker' ),
			SCF_VERSION
		);
		
		wp_enqueue_script(
			'scf-admin',
			SCF_URL . 'assets/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			SCF_VERSION,
			true
		);
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access', 'secure-contact-form' ) );
		}
		
		$settings = $this->get_settings();
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved successfully.', 'secure-contact-form' ); ?></p>
				</div>
			<?php endif; ?>
			
			<h2 class="nav-tab-wrapper">
				<a href="?page=secure-contact-form&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'secure-contact-form' ); ?>
				</a>
				<a href="?page=secure-contact-form&tab=fields" class="nav-tab <?php echo 'fields' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Form Fields', 'secure-contact-form' ); ?>
				</a>
				<a href="?page=secure-contact-form&tab=antispam" class="nav-tab <?php echo 'antispam' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Anti-Spam', 'secure-contact-form' ); ?>
				</a>
				<a href="?page=secure-contact-form&tab=style" class="nav-tab <?php echo 'style' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Styling', 'secure-contact-form' ); ?>
				</a>
				<a href="?page=secure-contact-form&tab=advanced" class="nav-tab <?php echo 'advanced' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Advanced', 'secure-contact-form' ); ?>
				</a>
			</h2>
			
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'scf_save_settings', 'scf_nonce' ); ?>
				<input type="hidden" name="action" value="scf_save_settings">
				<input type="hidden" name="active_tab" value="<?php echo esc_attr( $active_tab ); ?>">
				
				<?php
				switch ( $active_tab ) {
					case 'fields':
						$this->render_fields_tab( $settings );
						break;
					case 'antispam':
						$this->render_antispam_tab( $settings );
						break;
					case 'style':
						$this->render_style_tab( $settings );
						break;
					case 'advanced':
						$this->render_advanced_tab( $settings );
						break;
					default:
						$this->render_general_tab( $settings );
						break;
				}
				?>
				
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render general tab
	 *
	 * @param array $settings Plugin settings
	 */
	private function render_general_tab( $settings ) {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="email_recipients"><?php esc_html_e( 'Email Recipients', 'secure-contact-form' ); ?></label>
				</th>
				<td>
					<input type="text" 
					       id="email_recipients" 
					       name="email_recipients" 
					       value="<?php echo esc_attr( $settings['email_recipients'] ?? get_option( 'admin_email' ) ); ?>" 
					       class="regular-text">
					<p class="description">
						<?php esc_html_e( 'Enter up to 3 email addresses separated by commas.', 'secure-contact-form' ); ?>
					</p>
				</td>
			</tr>
			
			<tr>
				<th scope="row">
					<label for="email_method"><?php esc_html_e( 'Email Method', 'secure-contact-form' ); ?></label>
				</th>
				<td>
					<select id="email_method" name="email_method">
						<option value="wp_mail" <?php selected( $settings['email_method'] ?? 'wp_mail', 'wp_mail' ); ?>>
							<?php esc_html_e( 'WordPress wp_mail()', 'secure-contact-form' ); ?>
						</option>
						<option value="php_mail" <?php selected( $settings['email_method'] ?? 'wp_mail', 'php_mail' ); ?>>
							<?php esc_html_e( 'PHP mail()', 'secure-contact-form' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Choose how emails should be sent.', 'secure-contact-form' ); ?>
					</p>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><?php esc_html_e( 'Shortcode', 'secure-contact-form' ); ?></th>
				<td>
					<code>[secure_contact_form]</code>
					<p class="description">
						<?php esc_html_e( 'Use this shortcode to display the contact form on any page or post.', 'secure-contact-form' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render fields tab
	 *
	 * @param array $settings Plugin settings
	 */
	private function render_fields_tab( $settings ) {
		?>
		<h3><?php esc_html_e( 'Enable/Disable Fields', 'secure-contact-form' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Name Field', 'secure-contact-form' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="enable_name" value="1" <?php checked( $settings['enable_name'] ?? '0', '1' ); ?>>
						<?php esc_html_e( 'Enable name field', 'secure-contact-form' ); ?>
					</label>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><?php esc_html_e( 'Email Field', 'secure-contact-form' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="enable_email" value="1" <?php checked( $settings['enable_email'] ?? '0', '1' ); ?>>
						<?php esc_html_e( 'Enable email field', 'secure-contact-form' ); ?>
					</label>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><?php esc_html_e( 'Phone Field', 'secure-contact-form' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="enable_phone" value="1" <?php checked( $settings['enable_phone'] ?? '0', '1' ); ?>>
						<?php esc_html_e( 'Enable phone field', 'secure-contact-form' ); ?>
					</label>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><?php esc_html_e( 'Dropdown Field', 'secure-contact-form' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="enable_dropdown" value="1" <?php checked( $settings['enable_dropdown'] ?? '0', '1' ); ?>>
						<?php esc_html_e( 'Enable dropdown field', 'secure-contact-form' ); ?>
					</label>
				</td>
			</tr>
		</table>
		
		<h3><?php esc_html_e( 'Field Labels', 'secure-contact-form' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="label_name"><?php esc_html_e( 'Name Label', 'secure-contact-form' ); ?></label></th>
				<td><input type="text" id="label_name" name="label_name" value="<?php echo esc_attr( $settings['label_name'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
			
			<tr>
				<th scope="row"><label for="label_email"><?php esc_html_e( 'Email Label', 'secure-contact-form' ); ?></label></th>
				<td><input type="text" id="label_email" name="label_email" value="<?php echo esc_attr( $settings['label_email'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
			
			<tr>
				<th scope="row"><label for="label_phone"><?php esc_html_e( 'Phone Label', 'secure-contact-form' ); ?></label></th>
				<td><input type="text" id="label_phone" name="label_phone" value="<?php echo esc_attr( $settings['label_phone'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
			
			<tr>
				<th scope="row"><label for="label_subject"><?php esc_html_e( 'Subject Label', 'secure-contact-form' ); ?></label></th>
				<td><input type="text" id="label_subject" name="label_subject" value="<?php echo esc_attr( $settings['label_subject'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
			
			<tr>
				<th scope="row"><label for="label_message"><?php esc_html_e( 'Message Label', 'secure-contact-form' ); ?></label></th>
				<td><input type="text" id="label_message" name="label_message" value="<?php echo esc_attr( $settings['label_message'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
			
			<tr>
				<th scope="row"><label for="label_dropdown"><?php esc_html_e( 'Dropdown Label', 'secure-contact-form' ); ?></label></th>
				<td><input type="text" id="label_dropdown" name="label_dropdown" value="<?php echo esc_attr( $settings['label_dropdown'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
		</table>
		
		<h3><?php esc_html_e( 'Field Placeholders', 'secure-contact-form' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="placeholder_name"><?php esc_html_e( 'Name Placeholder', 'secure-contact-form' ); ?></label></th>
				<td><input type="text" id="placeholder_name" name="placeholder_name" value="<?php echo esc_attr( $settings['placeholder_name'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
			
			<tr>
				<th scope="row"><label for="placeholder_email"><?php esc_html_e( 'Email Placeholder', 'secure-contact-form' ); ?></label></th>
				<td><input type="text" id="placeholder_email" name="placeholder_email" value="<?php echo esc_attr( $settings['placeholder_email'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
			
			<tr>
				<th scope="row"><label for="placeholder_phone"><?php esc_html_e( 'Phone Placeholder', 'secure-contact-form' ); ?></label></th>
				<td><input type="text" id="placeholder_phone" name="placeholder_phone" value="<?php echo esc_attr( $settings['placeholder_phone'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
			
			<tr>
				<th scope="row"><label for="placeholder_subject"><?php esc_html_e( 'Subject Placeholder', 'secure-contact-form' ); ?></label></th>
				<td><input type="text" id="placeholder_subject" name="placeholder_subject" value="<?php echo esc_attr( $settings['placeholder_subject'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
			
			<tr>
				<th scope="row"><label for="placeholder_message"><?php esc_html_e( 'Message Placeholder', 'secure-contact-form' ); ?></label></th>
				<td><input type="text" id="placeholder_message" name="placeholder_message" value="<?php echo esc_attr( $settings['placeholder_message'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
		</table>
		
		<h3><?php esc_html_e( 'Dropdown Options', 'secure-contact-form' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="dropdown_options"><?php esc_html_e( 'Options', 'secure-contact-form' ); ?></label></th>
				<td>
					<textarea id="dropdown_options" name="dropdown_options" rows="5" class="large-text"><?php echo esc_textarea( $settings['dropdown_options'] ?? '' ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Enter one option per line (maximum 5 options).', 'secure-contact-form' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render anti-spam tab
	 *
	 * @param array $settings Plugin settings
	 */
	private function render_antispam_tab( $settings ) {
		?>
		<h3><?php esc_html_e( 'Anti-Spam Protection Layers', 'secure-contact-form' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Active Protection', 'secure-contact-form' ); ?></th>
				<td>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><?php esc_html_e( 'Traditional Hidden Honeypot (always active)', 'secure-contact-form' ); ?></li>
						<li><?php esc_html_e( 'Dynamic URL Honeypot (always active)', 'secure-contact-form' ); ?></li>
						<li><?php esc_html_e( 'Field Name Confusion (always active)', 'secure-contact-form' ); ?></li>
						<li><?php esc_html_e( 'Time-Based Validation (configurable below)', 'secure-contact-form' ); ?></li>
						<li><?php esc_html_e( 'Security Question (optional, configure below)', 'secure-contact-form' ); ?></li>
						<li><?php esc_html_e( 'CSRF Protection with WordPress nonces (always active)', 'secure-contact-form' ); ?></li>
					</ul>
				</td>
			</tr>
		</table>
		
		<h3><?php esc_html_e( 'Time-Based Validation', 'secure-contact-form' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="min_submission_time"><?php esc_html_e( 'Minimum Submission Time (seconds)', 'secure-contact-form' ); ?></label></th>
				<td>
					<input type="number" 
					       id="min_submission_time" 
					       name="min_submission_time" 
					       value="<?php echo esc_attr( $settings['min_submission_time'] ?? '3' ); ?>" 
					       min="1" 
					       max="60" 
					       class="small-text">
					<p class="description"><?php esc_html_e( 'Forms submitted faster than this will be rejected as spam.', 'secure-contact-form' ); ?></p>
				</td>
			</tr>
		</table>
		
		<h3><?php esc_html_e( 'Security Question (Optional)', 'secure-contact-form' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Security Question', 'secure-contact-form' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="enable_security_question" value="1" <?php checked( $settings['enable_security_question'] ?? '0', '1' ); ?>>
						<?php esc_html_e( 'Add a custom security question to the form', 'secure-contact-form' ); ?>
					</label>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><label for="security_question"><?php esc_html_e( 'Security Question', 'secure-contact-form' ); ?></label></th>
				<td>
					<input type="text" 
					       id="security_question" 
					       name="security_question" 
					       value="<?php echo esc_attr( $settings['security_question'] ?? '' ); ?>" 
					       class="regular-text">
				</td>
			</tr>
			
			<tr>
				<th scope="row"><label for="security_answer"><?php esc_html_e( 'Expected Answer', 'secure-contact-form' ); ?></label></th>
				<td>
					<input type="text" 
					       id="security_answer" 
					       name="security_answer" 
					       value="<?php echo esc_attr( $settings['security_answer'] ?? '' ); ?>" 
					       class="regular-text">
					<p class="description"><?php esc_html_e( 'Case-insensitive answer matching.', 'secure-contact-form' ); ?></p>
				</td>
			</tr>
		</table>
		
		<h3><?php esc_html_e( 'Rate Limiting', 'secure-contact-form' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rate_limit_max"><?php esc_html_e( 'Maximum Submissions', 'secure-contact-form' ); ?></label></th>
				<td>
					<input type="number" 
					       id="rate_limit_max" 
					       name="rate_limit_max" 
					       value="<?php echo esc_attr( $settings['rate_limit_max'] ?? '5' ); ?>" 
					       min="1" 
					       max="100" 
					       class="small-text">
				</td>
			</tr>
			
			<tr>
				<th scope="row"><label for="rate_limit_window"><?php esc_html_e( 'Time Window (minutes)', 'secure-contact-form' ); ?></label></th>
				<td>
					<input type="number" 
					       id="rate_limit_window" 
					       name="rate_limit_window" 
					       value="<?php echo esc_attr( $settings['rate_limit_window'] ?? '60' ); ?>" 
					       min="1" 
					       max="1440" 
					       class="small-text">
					<p class="description">
						<?php
						printf(
							/* translators: 1: Max submissions, 2: Time window */
							esc_html__( 'Allow maximum %1$s submissions per %2$s minutes from the same IP address.', 'secure-contact-form' ),
							'<strong>' . esc_html( $settings['rate_limit_max'] ?? '5' ) . '</strong>',
							'<strong>' . esc_html( $settings['rate_limit_window'] ?? '60' ) . '</strong>'
						);
						?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render style tab
	 *
	 * @param array $settings Plugin settings
	 */
	private function render_style_tab( $settings ) {
		?>
		<h3><?php esc_html_e( 'Color Customization', 'secure-contact-form' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="form_bg_color"><?php esc_html_e( 'Form Background Color', 'secure-contact-form' ); ?></label></th>
				<td>
					<input type="text" 
					       id="form_bg_color" 
					       name="form_bg_color" 
					       value="<?php echo esc_attr( $settings['form_bg_color'] ?? '#1a1a1a' ); ?>" 
					       class="scf-color-picker">
				</td>
			</tr>
			
			<tr>
				<th scope="row"><label for="form_border_color"><?php esc_html_e( 'Border Color', 'secure-contact-form' ); ?></label></th>
				<td>
					<input type="text" 
					       id="form_border_color" 
					       name="form_border_color" 
					       value="<?php echo esc_attr( $settings['form_border_color'] ?? '#d11c1c' ); ?>" 
					       class="scf-color-picker">
				</td>
			</tr>
			
			<tr>
				<th scope="row"><label for="form_text_color"><?php esc_html_e( 'Text Color', 'secure-contact-form' ); ?></label></th>
				<td>
					<input type="text" 
					       id="form_text_color" 
					       name="form_text_color" 
					       value="<?php echo esc_attr( $settings['form_text_color'] ?? '#ffffff' ); ?>" 
					       class="scf-color-picker">
				</td>
			</tr>
			
			<tr>
				<th scope="row"><label for="button_bg_color"><?php esc_html_e( 'Button Background Color', 'secure-contact-form' ); ?></label></th>
				<td>
					<input type="text" 
					       id="button_bg_color" 
					       name="button_bg_color" 
					       value="<?php echo esc_attr( $settings['button_bg_color'] ?? '#d11c1c' ); ?>" 
					       class="scf-color-picker">
				</td>
			</tr>
			
			<tr>
				<th scope="row"><label for="button_text_color"><?php esc_html_e( 'Button Text Color', 'secure-contact-form' ); ?></label></th>
				<td>
					<input type="text" 
					       id="button_text_color" 
					       name="button_text_color" 
					       value="<?php echo esc_attr( $settings['button_text_color'] ?? '#ffffff' ); ?>" 
					       class="scf-color-picker">
				</td>
			</tr>
		</table>
		
		<h3><?php esc_html_e( 'Layout Customization', 'secure-contact-form' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="border_radius"><?php esc_html_e( 'Border Radius (pixels)', 'secure-contact-form' ); ?></label></th>
				<td>
					<input type="number" 
					       id="border_radius" 
					       name="border_radius" 
					       value="<?php echo esc_attr( $settings['border_radius'] ?? '4' ); ?>" 
					       min="0" 
					       max="50" 
					       class="small-text">
					<p class="description"><?php esc_html_e( 'Applies to form container, fields, and buttons.', 'secure-contact-form' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render advanced tab
	 *
	 * @param array $settings Plugin settings
	 */
	private function render_advanced_tab( $settings ) {
		?>
		<h3><?php esc_html_e( 'Data Management', 'secure-contact-form' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Cleanup on Uninstall', 'secure-contact-form' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="cleanup_on_uninstall" value="1" <?php checked( $settings['cleanup_on_uninstall'] ?? '1', '1' ); ?>>
						<?php esc_html_e( 'Delete all plugin data when uninstalling', 'secure-contact-form' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'This will remove all database tables and settings when the plugin is deleted.', 'secure-contact-form' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save settings
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access', 'secure-contact-form' ) );
		}
		
		if ( ! isset( $_POST['scf_nonce'] ) || 
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scf_nonce'] ) ), 'scf_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed', 'secure-contact-form' ) );
		}
		
		$active_tab = isset( $_POST['active_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['active_tab'] ) ) : 'general';
		
		// Only save settings for the active tab
		switch ( $active_tab ) {
			case 'general':
				$this->save_general_settings();
				break;
			case 'fields':
				$this->save_fields_settings();
				break;
			case 'antispam':
				$this->save_antispam_settings();
				break;
			case 'style':
				$this->save_style_settings();
				break;
			case 'advanced':
				$this->save_advanced_settings();
				break;
		}
		
		wp_safe_redirect( add_query_arg(
			array(
				'page'             => 'secure-contact-form',
				'tab'              => $active_tab,
				'settings-updated' => 'true',
			),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Save general settings
	 */
	private function save_general_settings() {
		$settings = array(
			'email_recipients' => isset( $_POST['email_recipients'] ) ? sanitize_text_field( wp_unslash( $_POST['email_recipients'] ) ) : '',
			'email_method'     => isset( $_POST['email_method'] ) ? sanitize_text_field( wp_unslash( $_POST['email_method'] ) ) : 'wp_mail',
		);
		
		foreach ( $settings as $key => $value ) {
			$this->database->save_setting( $key, $value );
		}
	}

	/**
	 * Save fields settings
	 */
	private function save_fields_settings() {
		$settings = array(
			'enable_name'       => isset( $_POST['enable_name'] ) ? '1' : '0',
			'enable_email'      => isset( $_POST['enable_email'] ) ? '1' : '0',
			'enable_phone'      => isset( $_POST['enable_phone'] ) ? '1' : '0',
			'enable_dropdown'   => isset( $_POST['enable_dropdown'] ) ? '1' : '0',
			'label_name'        => isset( $_POST['label_name'] ) ? sanitize_text_field( wp_unslash( $_POST['label_name'] ) ) : '',
			'label_email'       => isset( $_POST['label_email'] ) ? sanitize_text_field( wp_unslash( $_POST['label_email'] ) ) : '',
			'label_phone'       => isset( $_POST['label_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['label_phone'] ) ) : '',
			'label_subject'     => isset( $_POST['label_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['label_subject'] ) ) : '',
			'label_message'     => isset( $_POST['label_message'] ) ? sanitize_text_field( wp_unslash( $_POST['label_message'] ) ) : '',
			'label_dropdown'    => isset( $_POST['label_dropdown'] ) ? sanitize_text_field( wp_unslash( $_POST['label_dropdown'] ) ) : '',
			'placeholder_name'  => isset( $_POST['placeholder_name'] ) ? sanitize_text_field( wp_unslash( $_POST['placeholder_name'] ) ) : '',
			'placeholder_email' => isset( $_POST['placeholder_email'] ) ? sanitize_text_field( wp_unslash( $_POST['placeholder_email'] ) ) : '',
			'placeholder_phone' => isset( $_POST['placeholder_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['placeholder_phone'] ) ) : '',
			'placeholder_subject' => isset( $_POST['placeholder_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['placeholder_subject'] ) ) : '',
			'placeholder_message' => isset( $_POST['placeholder_message'] ) ? sanitize_text_field( wp_unslash( $_POST['placeholder_message'] ) ) : '',
			'dropdown_options'  => isset( $_POST['dropdown_options'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dropdown_options'] ) ) : '',
		);
		
		foreach ( $settings as $key => $value ) {
			$this->database->save_setting( $key, $value );
		}
	}

	/**
	 * Save anti-spam settings
	 */
	private function save_antispam_settings() {
		$settings = array(
			'enable_security_question' => isset( $_POST['enable_security_question'] ) ? '1' : '0',
			'security_question'        => isset( $_POST['security_question'] ) ? sanitize_text_field( wp_unslash( $_POST['security_question'] ) ) : '',
			'security_answer'          => isset( $_POST['security_answer'] ) ? sanitize_text_field( wp_unslash( $_POST['security_answer'] ) ) : '',
			'min_submission_time'      => isset( $_POST['min_submission_time'] ) ? absint( $_POST['min_submission_time'] ) : 3,
			'rate_limit_max'           => isset( $_POST['rate_limit_max'] ) ? absint( $_POST['rate_limit_max'] ) : 5,
			'rate_limit_window'        => isset( $_POST['rate_limit_window'] ) ? absint( $_POST['rate_limit_window'] ) : 60,
		);
		
		foreach ( $settings as $key => $value ) {
			$this->database->save_setting( $key, $value );
		}
	}

	/**
	 * Save style settings
	 */
	private function save_style_settings() {
		$settings = array(
			'form_bg_color'     => isset( $_POST['form_bg_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['form_bg_color'] ) ) : '#1a1a1a',
			'form_border_color' => isset( $_POST['form_border_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['form_border_color'] ) ) : '#d11c1c',
			'form_text_color'   => isset( $_POST['form_text_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['form_text_color'] ) ) : '#ffffff',
			'button_bg_color'   => isset( $_POST['button_bg_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['button_bg_color'] ) ) : '#d11c1c',
			'button_text_color' => isset( $_POST['button_text_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['button_text_color'] ) ) : '#ffffff',
			'border_radius'     => isset( $_POST['border_radius'] ) ? absint( $_POST['border_radius'] ) : 4,
		);
		
		foreach ( $settings as $key => $value ) {
			$this->database->save_setting( $key, $value );
		}
	}

	/**
	 * Save advanced settings
	 */
	private function save_advanced_settings() {
		$settings = array(
			'cleanup_on_uninstall' => isset( $_POST['cleanup_on_uninstall'] ) ? '1' : '0',
		);
		
		foreach ( $settings as $key => $value ) {
			$this->database->save_setting( $key, $value );
		}
	}
}
