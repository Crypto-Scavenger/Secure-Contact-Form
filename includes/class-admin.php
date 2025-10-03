<?php
/**
 * Admin interface for Secure Contact Form
 *
 * @package SecureContactForm
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin interface
 */
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
	 * @return array Settings
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
		add_submenu_page(
			'tools.php',
			__( 'Secure Contact Form', 'secure-contact-form' ),
			__( 'Contact Form', 'secure-contact-form' ),
			'manage_options',
			'secure-contact-form',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'tools_page_secure-contact-form' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		
		wp_enqueue_style(
			'scf-admin',
			SCF_URL . 'assets/admin.css',
			array(),
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
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved successfully.', 'secure-contact-form' ); ?></p>
				</div>
			<?php endif; ?>
			
			<div class="scf-admin-notice" style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 12px; margin: 20px 0;">
				<p><strong><?php esc_html_e( 'Shortcode:', 'secure-contact-form' ); ?></strong> <code>[secure_contact_form]</code></p>
				<p><?php esc_html_e( 'Use this shortcode to display the contact form on any page or post.', 'secure-contact-form' ); ?></p>
			</div>
			
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'scf_save_settings', 'scf_nonce' ); ?>
				<input type="hidden" name="action" value="scf_save_settings" />
				
				<div class="scf-tabs">
					<h2 class="nav-tab-wrapper">
						<a href="#email" class="nav-tab nav-tab-active"><?php esc_html_e( 'Email Settings', 'secure-contact-form' ); ?></a>
						<a href="#fields" class="nav-tab"><?php esc_html_e( 'Form Fields', 'secure-contact-form' ); ?></a>
						<a href="#antispam" class="nav-tab"><?php esc_html_e( 'Anti-Spam', 'secure-contact-form' ); ?></a>
						<a href="#visual" class="nav-tab"><?php esc_html_e( 'Visual Design', 'secure-contact-form' ); ?></a>
						<a href="#other" class="nav-tab"><?php esc_html_e( 'Other', 'secure-contact-form' ); ?></a>
					</h2>
					
					<!-- Email Settings Tab -->
					<div id="email" class="scf-tab-content">
						<h2><?php esc_html_e( 'Email Settings', 'secure-contact-form' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="recipient_email_1"><?php esc_html_e( 'Recipient Email 1', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="email" 
										name="recipient_email_1" 
										id="recipient_email_1"
										value="<?php echo esc_attr( $settings['recipient_email_1'] ); ?>"
										class="regular-text"
										required
									/>
									<p class="description"><?php esc_html_e( 'Primary recipient email address (required)', 'secure-contact-form' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="recipient_email_2"><?php esc_html_e( 'Recipient Email 2', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="email" 
										name="recipient_email_2" 
										id="recipient_email_2"
										value="<?php echo esc_attr( $settings['recipient_email_2'] ); ?>"
										class="regular-text"
									/>
									<p class="description"><?php esc_html_e( 'Additional recipient (optional)', 'secure-contact-form' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="recipient_email_3"><?php esc_html_e( 'Recipient Email 3', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="email" 
										name="recipient_email_3" 
										id="recipient_email_3"
										value="<?php echo esc_attr( $settings['recipient_email_3'] ); ?>"
										class="regular-text"
									/>
									<p class="description"><?php esc_html_e( 'Additional recipient (optional)', 'secure-contact-form' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="email_method"><?php esc_html_e( 'Email Method', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<select name="email_method" id="email_method">
										<option value="wp_mail" <?php selected( 'wp_mail', $settings['email_method'] ); ?>><?php esc_html_e( 'WordPress wp_mail()', 'secure-contact-form' ); ?></option>
										<option value="php_mail" <?php selected( 'php_mail', $settings['email_method'] ); ?>><?php esc_html_e( 'PHP mail()', 'secure-contact-form' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Choose email sending method', 'secure-contact-form' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
					
					<!-- Form Fields Tab -->
					<div id="fields" class="scf-tab-content" style="display:none;">
						<h2><?php esc_html_e( 'Required Fields', 'secure-contact-form' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="subject_label"><?php esc_html_e( 'Subject Field Label', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="subject_label" 
										id="subject_label"
										value="<?php echo esc_attr( $settings['subject_label'] ); ?>"
										class="regular-text"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="subject_placeholder"><?php esc_html_e( 'Subject Placeholder', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="subject_placeholder" 
										id="subject_placeholder"
										value="<?php echo esc_attr( $settings['subject_placeholder'] ); ?>"
										class="regular-text"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="message_label"><?php esc_html_e( 'Message Field Label', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="message_label" 
										id="message_label"
										value="<?php echo esc_attr( $settings['message_label'] ); ?>"
										class="regular-text"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="message_placeholder"><?php esc_html_e( 'Message Placeholder', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="message_placeholder" 
										id="message_placeholder"
										value="<?php echo esc_attr( $settings['message_placeholder'] ); ?>"
										class="regular-text"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="privacy_label"><?php esc_html_e( 'Privacy Policy Label', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="privacy_label" 
										id="privacy_label"
										value="<?php echo esc_attr( $settings['privacy_label'] ); ?>"
										class="regular-text"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="privacy_link"><?php esc_html_e( 'Privacy Policy Link', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="url" 
										name="privacy_link" 
										id="privacy_link"
										value="<?php echo esc_attr( $settings['privacy_link'] ); ?>"
										class="regular-text"
									/>
									<p class="description"><?php esc_html_e( 'URL to your privacy policy page', 'secure-contact-form' ); ?></p>
								</td>
							</tr>
						</table>
						
						<h2><?php esc_html_e( 'Optional Fields', 'secure-contact-form' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable Name Field', 'secure-contact-form' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="enable_name" value="1" <?php checked( '1', $settings['enable_name'] ); ?> />
										<?php esc_html_e( 'Enable', 'secure-contact-form' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="name_label"><?php esc_html_e( 'Name Field Label', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="name_label" 
										id="name_label"
										value="<?php echo esc_attr( $settings['name_label'] ); ?>"
										class="regular-text"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="name_placeholder"><?php esc_html_e( 'Name Placeholder', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="name_placeholder" 
										id="name_placeholder"
										value="<?php echo esc_attr( $settings['name_placeholder'] ); ?>"
										class="regular-text"
									/>
								</td>
							</tr>
							
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable Email Field', 'secure-contact-form' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="enable_email" value="1" <?php checked( '1', $settings['enable_email'] ); ?> />
										<?php esc_html_e( 'Enable', 'secure-contact-form' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="email_label"><?php esc_html_e( 'Email Field Label', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="email_label" 
										id="email_label"
										value="<?php echo esc_attr( $settings['email_label'] ); ?>"
										class="regular-text"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="email_placeholder"><?php esc_html_e( 'Email Placeholder', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="email_placeholder" 
										id="email_placeholder"
										value="<?php echo esc_attr( $settings['email_placeholder'] ); ?>"
										class="regular-text"
									/>
								</td>
							</tr>
							
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable Phone Field', 'secure-contact-form' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="enable_phone" value="1" <?php checked( '1', $settings['enable_phone'] ); ?> />
										<?php esc_html_e( 'Enable', 'secure-contact-form' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="phone_label"><?php esc_html_e( 'Phone Field Label', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="phone_label" 
										id="phone_label"
										value="<?php echo esc_attr( $settings['phone_label'] ); ?>"
										class="regular-text"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="phone_placeholder"><?php esc_html_e( 'Phone Placeholder', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="phone_placeholder" 
										id="phone_placeholder"
										value="<?php echo esc_attr( $settings['phone_placeholder'] ); ?>"
										class="regular-text"
									/>
								</td>
							</tr>
							
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable Dropdown Field', 'secure-contact-form' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="enable_dropdown" value="1" <?php checked( '1', $settings['enable_dropdown'] ); ?> />
										<?php esc_html_e( 'Enable', 'secure-contact-form' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="dropdown_label"><?php esc_html_e( 'Dropdown Label', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="dropdown_label" 
										id="dropdown_label"
										value="<?php echo esc_attr( $settings['dropdown_label'] ); ?>"
										class="regular-text"
									/>
								</td>
							</tr>
							<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
							<tr>
								<th scope="row">
									<label for="dropdown_option_<?php echo esc_attr( $i ); ?>"><?php echo esc_html( sprintf( __( 'Dropdown Option %d', 'secure-contact-form' ), $i ) ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="dropdown_option_<?php echo esc_attr( $i ); ?>" 
										id="dropdown_option_<?php echo esc_attr( $i ); ?>"
										value="<?php echo esc_attr( $settings[ 'dropdown_option_' . $i ] ); ?>"
										class="regular-text"
									/>
								</td>
							</tr>
							<?php endfor; ?>
						</table>
					</div>
					
					<!-- Anti-Spam Tab -->
					<div id="antispam" class="scf-tab-content" style="display:none;">
						<h2><?php esc_html_e( 'Anti-Spam Protection', 'secure-contact-form' ); ?></h2>
						
						<div style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 12px; margin: 20px 0;">
							<h3 style="margin-top: 0;"><?php esc_html_e( 'Active Protection Methods', 'secure-contact-form' ); ?></h3>
							<ul style="margin-left: 20px;">
								<li><?php esc_html_e( 'Traditional hidden honeypot field', 'secure-contact-form' ); ?></li>
								<li><?php esc_html_e( 'Dynamic URL honeypot with randomized name', 'secure-contact-form' ); ?></li>
								<li><?php esc_html_e( 'Field name confusion (Subject uses name="honeypot")', 'secure-contact-form' ); ?></li>
								<li><?php esc_html_e( 'Time-based submission validation', 'secure-contact-form' ); ?></li>
								<li><?php esc_html_e( 'CSRF protection via WordPress nonces', 'secure-contact-form' ); ?></li>
							</ul>
						</div>
						
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="min_submit_time"><?php esc_html_e( 'Minimum Submit Time (seconds)', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="number" 
										name="min_submit_time" 
										id="min_submit_time"
										value="<?php echo esc_attr( $settings['min_submit_time'] ); ?>"
										min="1"
										max="60"
										class="small-text"
									/>
									<p class="description"><?php esc_html_e( 'Minimum time before form can be submitted (prevents bots)', 'secure-contact-form' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable Security Question', 'secure-contact-form' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="enable_security_question" value="1" <?php checked( '1', $settings['enable_security_question'] ); ?> />
										<?php esc_html_e( 'Enable', 'secure-contact-form' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="security_question"><?php esc_html_e( 'Security Question', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="security_question" 
										id="security_question"
										value="<?php echo esc_attr( $settings['security_question'] ); ?>"
										class="regular-text"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="security_answer"><?php esc_html_e( 'Expected Answer', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="security_answer" 
										id="security_answer"
										value="<?php echo esc_attr( $settings['security_answer'] ); ?>"
										class="regular-text"
									/>
									<p class="description"><?php esc_html_e( 'Case-insensitive matching', 'secure-contact-form' ); ?></p>
								</td>
							</tr>
						</table>
						
						<h2><?php esc_html_e( 'Rate Limiting', 'secure-contact-form' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="rate_limit_max"><?php esc_html_e( 'Max Submissions', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="number" 
										name="rate_limit_max" 
										id="rate_limit_max"
										value="<?php echo esc_attr( $settings['rate_limit_max'] ); ?>"
										min="1"
										max="100"
										class="small-text"
									/>
									<p class="description"><?php esc_html_e( 'Maximum submissions allowed per time window', 'secure-contact-form' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="rate_limit_window"><?php esc_html_e( 'Time Window (minutes)', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="number" 
										name="rate_limit_window" 
										id="rate_limit_window"
										value="<?php echo esc_attr( $settings['rate_limit_window'] ); ?>"
										min="1"
										max="1440"
										class="small-text"
									/>
								</td>
							</tr>
						</table>
					</div>
					
					<!-- Visual Design Tab -->
					<div id="visual" class="scf-tab-content" style="display:none;">
						<h2><?php esc_html_e( 'Visual Design', 'secure-contact-form' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="form_bg_color"><?php esc_html_e( 'Form Background Color', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="form_bg_color" 
										id="form_bg_color"
										value="<?php echo esc_attr( $settings['form_bg_color'] ); ?>"
										class="scf-color-picker"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="form_border_color"><?php esc_html_e( 'Border Color', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="form_border_color" 
										id="form_border_color"
										value="<?php echo esc_attr( $settings['form_border_color'] ); ?>"
										class="scf-color-picker"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="form_text_color"><?php esc_html_e( 'Text Color', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="form_text_color" 
										id="form_text_color"
										value="<?php echo esc_attr( $settings['form_text_color'] ); ?>"
										class="scf-color-picker"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="button_bg_color"><?php esc_html_e( 'Button Background Color', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="button_bg_color" 
										id="button_bg_color"
										value="<?php echo esc_attr( $settings['button_bg_color'] ); ?>"
										class="scf-color-picker"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="button_text_color"><?php esc_html_e( 'Button Text Color', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										name="button_text_color" 
										id="button_text_color"
										value="<?php echo esc_attr( $settings['button_text_color'] ); ?>"
										class="scf-color-picker"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="border_radius"><?php esc_html_e( 'Border Radius (px)', 'secure-contact-form' ); ?></label>
								</th>
								<td>
									<input 
										type="number" 
										name="border_radius" 
										id="border_radius"
										value="<?php echo esc_attr( $settings['border_radius'] ); ?>"
										min="0"
										max="50"
										class="small-text"
									/>
									<p class="description"><?php esc_html_e( 'Applied to form, fields, buttons, and all elements', 'secure-contact-form' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
					
					<!-- Other Tab -->
					<div id="other" class="scf-tab-content" style="display:none;">
						<h2><?php esc_html_e( 'Other Settings', 'secure-contact-form' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Cleanup on Uninstall', 'secure-contact-form' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="cleanup_on_uninstall" value="1" <?php checked( '1', $settings['cleanup_on_uninstall'] ); ?> />
										<?php esc_html_e( 'Remove all plugin data when uninstalled', 'secure-contact-form' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'This includes all settings, IP consents, and rate limit records', 'secure-contact-form' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
				</div>
				
				<?php submit_button(); ?>
			</form>
		</div>
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

		$settings = array(
			'recipient_email_1' => isset( $_POST['recipient_email_1'] ) ? sanitize_email( wp_unslash( $_POST['recipient_email_1'] ) ) : '',
			'recipient_email_2' => isset( $_POST['recipient_email_2'] ) ? sanitize_email( wp_unslash( $_POST['recipient_email_2'] ) ) : '',
			'recipient_email_3' => isset( $_POST['recipient_email_3'] ) ? sanitize_email( wp_unslash( $_POST['recipient_email_3'] ) ) : '',
			'email_method' => isset( $_POST['email_method'] ) ? sanitize_text_field( wp_unslash( $_POST['email_method'] ) ) : 'wp_mail',
			
			'subject_label' => isset( $_POST['subject_label'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_label'] ) ) : '',
			'subject_placeholder' => isset( $_POST['subject_placeholder'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_placeholder'] ) ) : '',
			'message_label' => isset( $_POST['message_label'] ) ? sanitize_text_field( wp_unslash( $_POST['message_label'] ) ) : '',
			'message_placeholder' => isset( $_POST['message_placeholder'] ) ? sanitize_text_field( wp_unslash( $_POST['message_placeholder'] ) ) : '',
			'privacy_label' => isset( $_POST['privacy_label'] ) ? sanitize_text_field( wp_unslash( $_POST['privacy_label'] ) ) : '',
			'privacy_link' => isset( $_POST['privacy_link'] ) ? esc_url_raw( wp_unslash( $_POST['privacy_link'] ) ) : '',
			
			'enable_name' => isset( $_POST['enable_name'] ) ? '1' : '0',
			'name_label' => isset( $_POST['name_label'] ) ? sanitize_text_field( wp_unslash( $_POST['name_label'] ) ) : '',
			'name_placeholder' => isset( $_POST['name_placeholder'] ) ? sanitize_text_field( wp_unslash( $_POST['name_placeholder'] ) ) : '',
			'enable_email' => isset( $_POST['enable_email'] ) ? '1' : '0',
			'email_label' => isset( $_POST['email_label'] ) ? sanitize_text_field( wp_unslash( $_POST['email_label'] ) ) : '',
			'email_placeholder' => isset( $_POST['email_placeholder'] ) ? sanitize_text_field( wp_unslash( $_POST['email_placeholder'] ) ) : '',
			'enable_phone' => isset( $_POST['enable_phone'] ) ? '1' : '0',
			'phone_label' => isset( $_POST['phone_label'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_label'] ) ) : '',
			'phone_placeholder' => isset( $_POST['phone_placeholder'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_placeholder'] ) ) : '',
			'enable_dropdown' => isset( $_POST['enable_dropdown'] ) ? '1' : '0',
			'dropdown_label' => isset( $_POST['dropdown_label'] ) ? sanitize_text_field( wp_unslash( $_POST['dropdown_label'] ) ) : '',
			
			'min_submit_time' => isset( $_POST['min_submit_time'] ) ? absint( $_POST['min_submit_time'] ) : 3,
			'enable_security_question' => isset( $_POST['enable_security_question'] ) ? '1' : '0',
			'security_question' => isset( $_POST['security_question'] ) ? sanitize_text_field( wp_unslash( $_POST['security_question'] ) ) : '',
			'security_answer' => isset( $_POST['security_answer'] ) ? sanitize_text_field( wp_unslash( $_POST['security_answer'] ) ) : '',
			
			'rate_limit_max' => isset( $_POST['rate_limit_max'] ) ? absint( $_POST['rate_limit_max'] ) : 5,
			'rate_limit_window' => isset( $_POST['rate_limit_window'] ) ? absint( $_POST['rate_limit_window'] ) : 60,
			
			'form_bg_color' => isset( $_POST['form_bg_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['form_bg_color'] ) ) : '#ffffff',
			'form_border_color' => isset( $_POST['form_border_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['form_border_color'] ) ) : '#dddddd',
			'form_text_color' => isset( $_POST['form_text_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['form_text_color'] ) ) : '#333333',
			'button_bg_color' => isset( $_POST['button_bg_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['button_bg_color'] ) ) : '#0073aa',
			'button_text_color' => isset( $_POST['button_text_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['button_text_color'] ) ) : '#ffffff',
			'border_radius' => isset( $_POST['border_radius'] ) ? absint( $_POST['border_radius'] ) : 4,
			
			'cleanup_on_uninstall' => isset( $_POST['cleanup_on_uninstall'] ) ? '1' : '0',
		);

		// Dropdown options
		for ( $i = 1; $i <= 5; $i++ ) {
			$key = 'dropdown_option_' . $i;
			$settings[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		}

		foreach ( $settings as $key => $value ) {
			$result = $this->database->save_setting( $key, $value );
			if ( is_wp_error( $result ) ) {
				wp_die( esc_html( $result->get_error_message() ) );
			}
		}

		wp_safe_redirect( add_query_arg(
			array(
				'page' => 'secure-contact-form',
				'settings-updated' => 'true',
			),
			admin_url( 'tools.php' )
		) );
		exit;
	}
}
