<?php
/**
 * Core functionality
 *
 * @package SecureContactForm
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SCF_Core {

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
		
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		add_shortcode( 'secure_contact_form', array( $this, 'render_shortcode' ) );
		add_action( 'init', array( $this, 'handle_form_submission' ) );
		add_action( 'init', array( $this, 'handle_consent_submission' ) );
		add_action( 'init', array( $this, 'start_session' ) );
	}

	/**
	 * Start PHP session
	 */
	public function start_session() {
		if ( ! session_id() && ! headers_sent() ) {
			session_start();
		}
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
	 * Enqueue public assets
	 */
	public function enqueue_public_assets() {
		global $post;
		
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'secure_contact_form' ) ) {
			return;
		}
		
		wp_enqueue_style(
			'scf-public',
			SCF_URL . 'assets/public.css',
			array(),
			SCF_VERSION
		);
		
		wp_enqueue_script(
			'scf-public',
			SCF_URL . 'assets/public.js',
			array( 'jquery' ),
			SCF_VERSION,
			true
		);
	}

	/**
	 * Get user IP address
	 *
	 * @return string
	 */
	private function get_user_ip() {
		$ip = '';
		
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Handle consent submission
	 */
	public function handle_consent_submission() {
		if ( ! isset( $_POST['scf_consent_action'] ) ) {
			return;
		}
		
		if ( ! isset( $_POST['scf_consent_nonce'] ) || 
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scf_consent_nonce'] ) ), 'scf_consent' ) ) {
			return;
		}
		
		$ip_address = $this->get_user_ip();
		if ( empty( $ip_address ) ) {
			return;
		}
		
		if ( isset( $_POST['scf_privacy_consent'] ) && '1' === $_POST['scf_privacy_consent'] ) {
			$this->database->record_consent( $ip_address );
		}
		
		wp_safe_redirect( remove_query_arg( array( 'scf_consent_action', 'scf_consent_nonce', 'scf_privacy_consent' ) ) );
		exit;
	}

	/**
	 * Handle form submission
	 */
	public function handle_form_submission() {
		if ( ! isset( $_POST['scf_submit'] ) ) {
			return;
		}
		
		// Verify nonce
		if ( ! isset( $_POST['scf_nonce'] ) || 
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scf_nonce'] ) ), 'scf_submit_form' ) ) {
			wp_die( esc_html__( 'Security check failed', 'secure-contact-form' ) );
		}
		
		$settings = $this->get_settings();
		$errors   = array();
		
		// Get IP address
		$ip_address = $this->get_user_ip();
		
		// Check if consent is required and given
		if ( ! $this->database->has_consent( $ip_address ) ) {
			$errors[] = __( 'Please accept the Privacy Policy before submitting.', 'secure-contact-form' );
		}
		
		// Check rate limiting
		$max_attempts    = (int) ( $settings['rate_limit_max'] ?? 5 );
		$window_minutes  = (int) ( $settings['rate_limit_window'] ?? 60 );
		
		if ( ! $this->database->check_rate_limit( $ip_address, $max_attempts, $window_minutes ) ) {
			$errors[] = __( 'You have submitted too many forms. Please try again later.', 'secure-contact-form' );
		}
		
		// Validate honeypots
		// Traditional honeypot (should be empty)
		if ( ! empty( $_POST['name'] ) ) {
			$errors[] = __( 'Spam detected', 'secure-contact-form' );
		}
		
		// Dynamic URL honeypot (should be empty)
		foreach ( $_POST as $key => $value ) {
			if ( strpos( $key, 'website_URL_' ) === 0 && ! empty( $value ) ) {
				$errors[] = __( 'Spam detected', 'secure-contact-form' );
			}
		}
		
		// Time-based validation
		$min_time = (int) ( $settings['min_submission_time'] ?? 3 );
		if ( isset( $_SESSION['scf_form_load_time'] ) ) {
			$elapsed = time() - $_SESSION['scf_form_load_time'];
			if ( $elapsed < $min_time ) {
				$errors[] = __( 'Form submitted too quickly. Please try again.', 'secure-contact-form' );
			}
		}
		
		// Validate security question
		if ( '1' === ( $settings['enable_security_question'] ?? '0' ) ) {
			$security_answer = isset( $_POST['scf_security_answer'] ) ? sanitize_text_field( wp_unslash( $_POST['scf_security_answer'] ) ) : '';
			$correct_answer  = $settings['security_answer'] ?? '';
			
			if ( strcasecmp( trim( $security_answer ), trim( $correct_answer ) ) !== 0 ) {
				$errors[] = __( 'Security answer is incorrect.', 'secure-contact-form' );
			}
		}
		
		// Validate required fields
		$subject = isset( $_POST['honeypot'] ) ? sanitize_text_field( wp_unslash( $_POST['honeypot'] ) ) : '';
		$message = isset( $_POST['scf_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['scf_message'] ) ) : '';
		
		if ( empty( $subject ) ) {
			$errors[] = __( 'Subject is required.', 'secure-contact-form' );
		}
		
		if ( empty( $message ) ) {
			$errors[] = __( 'Message is required.', 'secure-contact-form' );
		}
		
		// Validate optional fields
		$form_data = array();
		
		if ( '1' === ( $settings['enable_name'] ?? '0' ) ) {
			$form_data['name'] = isset( $_POST['scf_name'] ) ? sanitize_text_field( wp_unslash( $_POST['scf_name'] ) ) : '';
		}
		
		if ( '1' === ( $settings['enable_email'] ?? '0' ) ) {
			$email = isset( $_POST['scf_email'] ) ? sanitize_email( wp_unslash( $_POST['scf_email'] ) ) : '';
			if ( ! empty( $email ) && ! is_email( $email ) ) {
				$errors[] = __( 'Invalid email address.', 'secure-contact-form' );
			}
			$form_data['email'] = $email;
		}
		
		if ( '1' === ( $settings['enable_phone'] ?? '0' ) ) {
			$form_data['phone'] = isset( $_POST['scf_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['scf_phone'] ) ) : '';
		}
		
		if ( '1' === ( $settings['enable_dropdown'] ?? '0' ) ) {
			$form_data['dropdown'] = isset( $_POST['scf_dropdown'] ) ? sanitize_text_field( wp_unslash( $_POST['scf_dropdown'] ) ) : '';
		}
		
		if ( ! empty( $errors ) ) {
			$_SESSION['scf_errors']    = $errors;
			$_SESSION['scf_form_data'] = array_merge( $form_data, array( 'subject' => $subject, 'message' => $message ) );
			wp_safe_redirect( wp_get_referer() );
			exit;
		}
		
		// Send email
		$recipients    = $settings['email_recipients'] ?? get_option( 'admin_email' );
		$email_method  = $settings['email_method'] ?? 'wp_mail';
		$email_list    = array_map( 'trim', explode( ',', $recipients ) );
		$email_list    = array_slice( $email_list, 0, 3 ); // Max 3 recipients
		
		$email_subject = sprintf(
			/* translators: %s: Subject from form */
			__( '[Contact Form] %s', 'secure-contact-form' ),
			$subject
		);
		
		$email_body = $this->build_email_body( $form_data, $subject, $message );
		
		$sent = false;
		if ( 'php_mail' === $email_method ) {
			foreach ( $email_list as $recipient ) {
				if ( is_email( $recipient ) ) {
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_mail
					$sent = mail( $recipient, $email_subject, $email_body );
				}
			}
		} else {
			$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
			foreach ( $email_list as $recipient ) {
				if ( is_email( $recipient ) ) {
					$sent = wp_mail( $recipient, $email_subject, $email_body, $headers );
				}
			}
		}
		
		// Record submission
		$this->database->record_submission( $ip_address );
		
		// Clear form data and set success message
		unset( $_SESSION['scf_form_data'] );
		$_SESSION['scf_success'] = __( 'Thank you! Your message has been sent successfully.', 'secure-contact-form' );
		
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Build email body
	 *
	 * @param array  $form_data Form data
	 * @param string $subject Subject
	 * @param string $message Message
	 * @return string
	 */
	private function build_email_body( $form_data, $subject, $message ) {
		$body = __( 'New contact form submission:', 'secure-contact-form' ) . "\n\n";
		
		$body .= __( 'Subject:', 'secure-contact-form' ) . ' ' . $subject . "\n";
		
		if ( ! empty( $form_data['name'] ) ) {
			$body .= __( 'Name:', 'secure-contact-form' ) . ' ' . $form_data['name'] . "\n";
		}
		
		if ( ! empty( $form_data['email'] ) ) {
			$body .= __( 'Email:', 'secure-contact-form' ) . ' ' . $form_data['email'] . "\n";
		}
		
		if ( ! empty( $form_data['phone'] ) ) {
			$body .= __( 'Phone:', 'secure-contact-form' ) . ' ' . $form_data['phone'] . "\n";
		}
		
		if ( ! empty( $form_data['dropdown'] ) ) {
			$body .= __( 'Selected Option:', 'secure-contact-form' ) . ' ' . $form_data['dropdown'] . "\n";
		}
		
		$body .= "\n" . __( 'Message:', 'secure-contact-form' ) . "\n" . $message;
		
		return $body;
	}

	/**
	 * Render shortcode
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$settings   = $this->get_settings();
		$ip_address = $this->get_user_ip();
		
		// Store form load time for time-based validation
		if ( ! isset( $_SESSION['scf_form_load_time'] ) ) {
			$_SESSION['scf_form_load_time'] = time();
		}
		
		// Check if consent is needed
		if ( ! $this->database->has_consent( $ip_address ) ) {
			return $this->render_consent_form( $settings );
		}
		
		return $this->render_contact_form( $settings );
	}

	/**
	 * Render consent form
	 *
	 * @param array $settings Plugin settings
	 * @return string
	 */
	private function render_consent_form( $settings ) {
		$privacy_url = get_privacy_policy_url();
		
		ob_start();
		?>
		<div class="scf-consent-wrapper" style="
			background-color: <?php echo esc_attr( $settings['form_bg_color'] ?? '#1a1a1a' ); ?>;
			border: 2px solid <?php echo esc_attr( $settings['form_border_color'] ?? '#d11c1c' ); ?>;
			border-radius: <?php echo esc_attr( $settings['border_radius'] ?? '4' ); ?>px;
			color: <?php echo esc_attr( $settings['form_text_color'] ?? '#ffffff' ); ?>;
		">
			<div class="scf-consent-header">
				<i class="fas fa-shield-alt"></i>
				<h3><?php esc_html_e( 'Privacy Consent Required', 'secure-contact-form' ); ?></h3>
			</div>
			
			<div class="scf-consent-content">
				<p><?php esc_html_e( 'Before using this contact form, please review and accept our privacy policy.', 'secure-contact-form' ); ?></p>
				
				<form method="post" class="scf-consent-form">
					<?php wp_nonce_field( 'scf_consent', 'scf_consent_nonce' ); ?>
					<input type="hidden" name="scf_consent_action" value="1">
					
					<label class="scf-consent-checkbox">
						<input type="checkbox" name="scf_privacy_consent" value="1" required>
						<span>
							<?php
							if ( ! empty( $privacy_url ) ) {
								printf(
									/* translators: %s: Privacy policy link */
									wp_kses_post( __( 'I have read and accept the <a href="%s" target="_blank">Privacy Policy</a>', 'secure-contact-form' ) ),
									esc_url( $privacy_url )
								);
							} else {
								esc_html_e( 'I accept the Privacy Policy', 'secure-contact-form' );
							}
							?>
						</span>
					</label>
					
					<button type="submit" class="scf-consent-button" style="
						background-color: <?php echo esc_attr( $settings['button_bg_color'] ?? '#d11c1c' ); ?>;
						color: <?php echo esc_attr( $settings['button_text_color'] ?? '#ffffff' ); ?>;
						border-radius: <?php echo esc_attr( $settings['border_radius'] ?? '4' ); ?>px;
					">
						<i class="fas fa-check"></i>
						<?php esc_html_e( 'Continue to Contact Form', 'secure-contact-form' ); ?>
					</button>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render contact form
	 *
	 * @param array $settings Plugin settings
	 * @return string
	 */
	private function render_contact_form( $settings ) {
		// Get stored form data and errors
		$form_data = isset( $_SESSION['scf_form_data'] ) ? $_SESSION['scf_form_data'] : array();
		$errors    = isset( $_SESSION['scf_errors'] ) ? $_SESSION['scf_errors'] : array();
		$success   = isset( $_SESSION['scf_success'] ) ? $_SESSION['scf_success'] : '';
		
		// Clear session data
		unset( $_SESSION['scf_form_data'], $_SESSION['scf_errors'], $_SESSION['scf_success'] );
		
		// Generate random honeypot field name
		$random_honeypot = 'website_URL_' . wp_rand( 1000, 9999 );
		
		ob_start();
		?>
		<div class="scf-form-wrapper" style="
			background-color: <?php echo esc_attr( $settings['form_bg_color'] ?? '#1a1a1a' ); ?>;
			border: 2px solid <?php echo esc_attr( $settings['form_border_color'] ?? '#d11c1c' ); ?>;
			border-radius: <?php echo esc_attr( $settings['border_radius'] ?? '4' ); ?>px;
			color: <?php echo esc_attr( $settings['form_text_color'] ?? '#ffffff' ); ?>;
		">
			<?php if ( ! empty( $errors ) ) : ?>
				<div class="scf-errors">
					<i class="fas fa-exclamation-triangle"></i>
					<ul>
						<?php foreach ( $errors as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			
			<?php if ( ! empty( $success ) ) : ?>
				<div class="scf-success">
					<i class="fas fa-check-circle"></i>
					<p><?php echo esc_html( $success ); ?></p>
				</div>
			<?php endif; ?>
			
			<form method="post" class="scf-contact-form" novalidate>
				<?php wp_nonce_field( 'scf_submit_form', 'scf_nonce' ); ?>
				
				<!-- Traditional Honeypot (hidden with CSS) -->
				<input type="text" name="name" class="scf-honeypot" tabindex="-1" autocomplete="off">
				
				<!-- Dynamic URL Honeypot -->
				<input type="url" name="<?php echo esc_attr( $random_honeypot ); ?>" class="scf-url-honeypot" tabindex="-1" autocomplete="off">
				
				<?php if ( '1' === ( $settings['enable_name'] ?? '0' ) ) : ?>
					<div class="scf-field">
						<label for="scf_name"><?php echo esc_html( $settings['label_name'] ?? __( 'Name', 'secure-contact-form' ) ); ?></label>
						<input type="text" 
						       id="scf_name" 
						       name="scf_name" 
						       value="<?php echo esc_attr( $form_data['name'] ?? '' ); ?>"
						       placeholder="<?php echo esc_attr( $settings['placeholder_name'] ?? '' ); ?>"
						       style="border-radius: <?php echo esc_attr( $settings['border_radius'] ?? '4' ); ?>px;">
					</div>
				<?php endif; ?>
				
				<?php if ( '1' === ( $settings['enable_email'] ?? '0' ) ) : ?>
					<div class="scf-field">
						<label for="scf_email"><?php echo esc_html( $settings['label_email'] ?? __( 'Email', 'secure-contact-form' ) ); ?></label>
						<input type="email" 
						       id="scf_email" 
						       name="scf_email" 
						       value="<?php echo esc_attr( $form_data['email'] ?? '' ); ?>"
						       placeholder="<?php echo esc_attr( $settings['placeholder_email'] ?? '' ); ?>"
						       style="border-radius: <?php echo esc_attr( $settings['border_radius'] ?? '4' ); ?>px;">
					</div>
				<?php endif; ?>
				
				<?php if ( '1' === ( $settings['enable_phone'] ?? '0' ) ) : ?>
					<div class="scf-field">
						<label for="scf_phone"><?php echo esc_html( $settings['label_phone'] ?? __( 'Phone', 'secure-contact-form' ) ); ?></label>
						<input type="tel" 
						       id="scf_phone" 
						       name="scf_phone" 
						       value="<?php echo esc_attr( $form_data['phone'] ?? '' ); ?>"
						       placeholder="<?php echo esc_attr( $settings['placeholder_phone'] ?? '' ); ?>"
						       style="border-radius: <?php echo esc_attr( $settings['border_radius'] ?? '4' ); ?>px;">
					</div>
				<?php endif; ?>
				
				<?php if ( '1' === ( $settings['enable_dropdown'] ?? '0' ) ) : ?>
					<div class="scf-field">
						<label for="scf_dropdown"><?php echo esc_html( $settings['label_dropdown'] ?? __( 'Select Option', 'secure-contact-form' ) ); ?></label>
						<select id="scf_dropdown" 
						        name="scf_dropdown"
						        style="border-radius: <?php echo esc_attr( $settings['border_radius'] ?? '4' ); ?>px;">
							<option value=""><?php esc_html_e( 'Select...', 'secure-contact-form' ); ?></option>
							<?php
							$options = explode( "\n", $settings['dropdown_options'] ?? '' );
							foreach ( $options as $option ) {
								$option = trim( $option );
								if ( ! empty( $option ) ) {
									$selected = ( isset( $form_data['dropdown'] ) && $form_data['dropdown'] === $option ) ? ' selected' : '';
									echo '<option value="' . esc_attr( $option ) . '"' . esc_attr( $selected ) . '>' . esc_html( $option ) . '</option>';
								}
							}
							?>
						</select>
					</div>
				<?php endif; ?>
				
				<div class="scf-field scf-field-required">
					<label for="honeypot">
						<?php echo esc_html( $settings['label_subject'] ?? __( 'Subject', 'secure-contact-form' ) ); ?>
						<span class="scf-required">*</span>
					</label>
					<input type="text" 
					       id="honeypot" 
					       name="honeypot" 
					       value="<?php echo esc_attr( $form_data['subject'] ?? '' ); ?>"
					       placeholder="<?php echo esc_attr( $settings['placeholder_subject'] ?? '' ); ?>"
					       required
					       style="border-radius: <?php echo esc_attr( $settings['border_radius'] ?? '4' ); ?>px;">
				</div>
				
				<div class="scf-field scf-field-required">
					<label for="scf_message">
						<?php echo esc_html( $settings['label_message'] ?? __( 'Message', 'secure-contact-form' ) ); ?>
						<span class="scf-required">*</span>
					</label>
					<textarea id="scf_message" 
					          name="scf_message" 
					          rows="5" 
					          placeholder="<?php echo esc_attr( $settings['placeholder_message'] ?? '' ); ?>"
					          required
					          style="border-radius: <?php echo esc_attr( $settings['border_radius'] ?? '4' ); ?>px;"><?php echo esc_textarea( $form_data['message'] ?? '' ); ?></textarea>
				</div>
				
				<?php if ( '1' === ( $settings['enable_security_question'] ?? '0' ) ) : ?>
					<div class="scf-field scf-field-required scf-security-question">
						<label for="scf_security_answer">
							<i class="fas fa-question-circle"></i>
							<?php echo esc_html( $settings['security_question'] ?? __( 'Security Question', 'secure-contact-form' ) ); ?>
							<span class="scf-required">*</span>
						</label>
						<input type="text" 
						       id="scf_security_answer" 
						       name="scf_security_answer" 
						       required
						       style="border-radius: <?php echo esc_attr( $settings['border_radius'] ?? '4' ); ?>px;">
					</div>
				<?php endif; ?>
				
				<button type="submit" 
				        name="scf_submit" 
				        class="scf-submit-button"
				        style="
				        	background-color: <?php echo esc_attr( $settings['button_bg_color'] ?? '#d11c1c' ); ?>;
				        	color: <?php echo esc_attr( $settings['button_text_color'] ?? '#ffffff' ); ?>;
				        	border-radius: <?php echo esc_attr( $settings['border_radius'] ?? '4' ); ?>px;
				        ">
					<i class="fas fa-paper-plane"></i>
					<?php esc_html_e( 'Send Message', 'secure-contact-form' ); ?>
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}
