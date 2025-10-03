<?php
/**
 * Core functionality for Secure Contact Form
 *
 * @package SecureContactForm
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles core plugin functionality
 */
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
		
		add_shortcode( 'secure_contact_form', array( $this, 'render_form' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		add_action( 'init', array( $this, 'handle_form_submission' ) );
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
	 * Enqueue public assets
	 */
	public function enqueue_public_assets() {
		global $post;
		
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'secure_contact_form' ) ) {
			wp_enqueue_style(
				'scf-public',
				SCF_URL . 'assets/public.css',
				array(),
				SCF_VERSION
			);
		}
	}

	/**
	 * Get user IP address
	 *
	 * @return string IP address
	 */
	private function get_user_ip() {
		$ip = '';
		
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Get session ID
	 *
	 * @return string Session ID
	 */
	private function get_session_id() {
		if ( ! session_id() ) {
			session_start();
		}
		return session_id();
	}

	/**
	 * Generate random field name for URL honeypot
	 *
	 * @return string Random field name
	 */
	private function generate_honeypot_name() {
		return 'user_websirsite_URL_' . wp_rand( 1000, 9999 );
	}

	/**
	 * Render contact form
	 *
	 * @return string Form HTML
	 */
	public function render_form() {
		$settings = $this->get_settings();
		$ip = $this->get_user_ip();
		
		// Check IP consent
		$has_consented = $this->database->has_ip_consented( $ip );
		
		// Generate timestamp and honeypot name
		$timestamp = time();
		$honeypot_url_name = $this->generate_honeypot_name();
		
		// Store in session
		if ( ! session_id() ) {
			session_start();
		}
		$_SESSION['scf_timestamp'] = $timestamp;
		$_SESSION['scf_honeypot_url'] = $honeypot_url_name;
		
		ob_start();
		?>
		<div class="scf-wrapper" style="
			background-color: <?php echo esc_attr( $settings['form_bg_color'] ); ?>;
			border: 2px solid <?php echo esc_attr( $settings['form_border_color'] ); ?>;
			border-radius: <?php echo esc_attr( $settings['border_radius'] ); ?>px;
			color: <?php echo esc_attr( $settings['form_text_color'] ); ?>;
			padding: 30px;
			max-width: 600px;
			margin: 0 auto;
		">
			<?php if ( ! $has_consented ) : ?>
				<div class="scf-consent-required" style="
					background-color: #fff3cd;
					border: 1px solid #ffc107;
					border-radius: <?php echo esc_attr( $settings['border_radius'] ); ?>px;
					padding: 20px;
					margin-bottom: 20px;
				">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Privacy Consent Required', 'secure-contact-form' ); ?></h3>
					<p><?php esc_html_e( 'Before using this contact form, you must first agree to our Privacy Policy.', 'secure-contact-form' ); ?></p>
					
					<form method="post" action="">
						<?php wp_nonce_field( 'scf_consent', 'scf_consent_nonce' ); ?>
						<input type="hidden" name="scf_action" value="consent" />
						
						<label style="display: block; margin-bottom: 15px;">
							<input type="checkbox" name="scf_consent_checkbox" value="1" required />
							<?php
							if ( ! empty( $settings['privacy_link'] ) ) {
								printf(
									/* translators: %s: privacy policy URL */
									esc_html__( 'I have read and agree to the %s', 'secure-contact-form' ),
									'<a href="' . esc_url( $settings['privacy_link'] ) . '" target="_blank">' . esc_html__( 'Privacy Policy', 'secure-contact-form' ) . '</a>'
								);
							} else {
								echo esc_html( $settings['privacy_label'] );
							}
							?>
						</label>
						
						<button type="submit" style="
							background-color: <?php echo esc_attr( $settings['button_bg_color'] ); ?>;
							color: <?php echo esc_attr( $settings['button_text_color'] ); ?>;
							border: none;
							border-radius: <?php echo esc_attr( $settings['border_radius'] ); ?>px;
							padding: 12px 24px;
							cursor: pointer;
							font-size: 16px;
						"><?php esc_html_e( 'Accept and Continue', 'secure-contact-form' ); ?></button>
					</form>
				</div>
			<?php else : ?>
				
				<?php if ( isset( $_SESSION['scf_message'] ) ) : ?>
					<div class="scf-message <?php echo esc_attr( $_SESSION['scf_message_type'] ); ?>" style="
						background-color: <?php echo $_SESSION['scf_message_type'] === 'success' ? '#d4edda' : '#f8d7da'; ?>;
						border: 1px solid <?php echo $_SESSION['scf_message_type'] === 'success' ? '#c3e6cb' : '#f5c6cb'; ?>;
						border-radius: <?php echo esc_attr( $settings['border_radius'] ); ?>px;
						padding: 15px;
						margin-bottom: 20px;
					">
						<?php echo esc_html( $_SESSION['scf_message'] ); ?>
					</div>
					<?php
					unset( $_SESSION['scf_message'] );
					unset( $_SESSION['scf_message_type'] );
					?>
				<?php endif; ?>
				
				<form method="post" action="" class="scf-form">
					<?php wp_nonce_field( 'scf_submit', 'scf_nonce' ); ?>
					<input type="hidden" name="scf_action" value="submit" />
					<input type="hidden" name="scf_timestamp" value="<?php echo esc_attr( $timestamp ); ?>" />
					
					<!-- Traditional Hidden Honeypot -->
					<div style="position: absolute; opacity: 0; z-index: -5;">
						<label for="name"><?php esc_html_e( 'Name', 'secure-contact-form' ); ?></label>
						<input type="text" name="name" id="name" tabindex="-1" autocomplete="off" />
					</div>
					
					<!-- Dynamic URL Honeypot -->
					<div style="display: none;">
						<label for="<?php echo esc_attr( $honeypot_url_name ); ?>"><?php esc_html_e( 'Website', 'secure-contact-form' ); ?></label>
						<input type="url" name="<?php echo esc_attr( $honeypot_url_name ); ?>" id="<?php echo esc_attr( $honeypot_url_name ); ?>" tabindex="-1" />
					</div>
					
					<?php if ( '1' === $settings['enable_name'] ) : ?>
					<div class="scf-field" style="margin-bottom: 20px;">
						<label for="scf_real_name" style="
							display: block;
							margin-bottom: 8px;
							font-weight: 600;
						"><?php echo esc_html( $settings['name_label'] ); ?></label>
						<input 
							type="text" 
							name="scf_real_name" 
							id="scf_real_name"
							placeholder="<?php echo esc_attr( $settings['name_placeholder'] ); ?>"
							style="
								width: 100%;
								padding: 12px;
								border: 1px solid <?php echo esc_attr( $settings['form_border_color'] ); ?>;
								border-radius: <?php echo esc_attr( $settings['border_radius'] ); ?>px;
								box-sizing: border-box;
							"
						/>
					</div>
					<?php endif; ?>
					
					<?php if ( '1' === $settings['enable_email'] ) : ?>
					<div class="scf-field" style="margin-bottom: 20px;">
						<label for="scf_email" style="
							display: block;
							margin-bottom: 8px;
							font-weight: 600;
						"><?php echo esc_html( $settings['email_label'] ); ?></label>
						<input 
							type="email" 
							name="scf_email" 
							id="scf_email"
							placeholder="<?php echo esc_attr( $settings['email_placeholder'] ); ?>"
							style="
								width: 100%;
								padding: 12px;
								border: 1px solid <?php echo esc_attr( $settings['form_border_color'] ); ?>;
								border-radius: <?php echo esc_attr( $settings['border_radius'] ); ?>px;
								box-sizing: border-box;
							"
						/>
					</div>
					<?php endif; ?>
					
					<?php if ( '1' === $settings['enable_phone'] ) : ?>
					<div class="scf-field" style="margin-bottom: 20px;">
						<label for="scf_phone" style="
							display: block;
							margin-bottom: 8px;
							font-weight: 600;
						"><?php echo esc_html( $settings['phone_label'] ); ?></label>
						<input 
							type="tel" 
							name="scf_phone" 
							id="scf_phone"
							placeholder="<?php echo esc_attr( $settings['phone_placeholder'] ); ?>"
							style="
								width: 100%;
								padding: 12px;
								border: 1px solid <?php echo esc_attr( $settings['form_border_color'] ); ?>;
								border-radius: <?php echo esc_attr( $settings['border_radius'] ); ?>px;
								box-sizing: border-box;
							"
						/>
					</div>
					<?php endif; ?>
					
					<?php if ( '1' === $settings['enable_dropdown'] ) : ?>
						<?php
						$has_options = false;
						for ( $i = 1; $i <= 5; $i++ ) {
							if ( ! empty( $settings[ 'dropdown_option_' . $i ] ) ) {
								$has_options = true;
								break;
							}
						}
						?>
						<?php if ( $has_options ) : ?>
						<div class="scf-field" style="margin-bottom: 20px;">
							<label for="scf_dropdown" style="
								display: block;
								margin-bottom: 8px;
								font-weight: 600;
							"><?php echo esc_html( $settings['dropdown_label'] ); ?></label>
							<select 
								name="scf_dropdown" 
								id="scf_dropdown"
								style="
									width: 100%;
									padding: 12px;
									border: 1px solid <?php echo esc_attr( $settings['form_border_color'] ); ?>;
									border-radius: <?php echo esc_attr( $settings['border_radius'] ); ?>px;
									box-sizing: border-box;
								"
							>
								<option value=""><?php esc_html_e( 'Select an option', 'secure-contact-form' ); ?></option>
								<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
									<?php if ( ! empty( $settings[ 'dropdown_option_' . $i ] ) ) : ?>
										<option value="<?php echo esc_attr( $settings[ 'dropdown_option_' . $i ] ); ?>">
											<?php echo esc_html( $settings[ 'dropdown_option_' . $i ] ); ?>
										</option>
									<?php endif; ?>
								<?php endfor; ?>
							</select>
						</div>
						<?php endif; ?>
					<?php endif; ?>
					
					<!-- Subject field with name="honeypot" for confusion -->
					<div class="scf-field" style="margin-bottom: 20px;">
						<label for="honeypot" style="
							display: block;
							margin-bottom: 8px;
							font-weight: 600;
						"><?php echo esc_html( $settings['subject_label'] ); ?> <span style="color: red;">*</span></label>
						<input 
							type="text" 
							name="honeypot" 
							id="honeypot"
							placeholder="<?php echo esc_attr( $settings['subject_placeholder'] ); ?>"
							required
							style="
								width: 100%;
								padding: 12px;
								border: 1px solid <?php echo esc_attr( $settings['form_border_color'] ); ?>;
								border-radius: <?php echo esc_attr( $settings['border_radius'] ); ?>px;
								box-sizing: border-box;
							"
						/>
					</div>
					
					<div class="scf-field" style="margin-bottom: 20px;">
						<label for="scf_message" style="
							display: block;
							margin-bottom: 8px;
							font-weight: 600;
						"><?php echo esc_html( $settings['message_label'] ); ?> <span style="color: red;">*</span></label>
						<textarea 
							name="scf_message" 
							id="scf_message"
							placeholder="<?php echo esc_attr( $settings['message_placeholder'] ); ?>"
							rows="6"
							required
							style="
								width: 100%;
								padding: 12px;
								border: 1px solid <?php echo esc_attr( $settings['form_border_color'] ); ?>;
								border-radius: <?php echo esc_attr( $settings['border_radius'] ); ?>px;
								box-sizing: border-box;
								resize: vertical;
							"
						></textarea>
					</div>
					
					<?php if ( '1' === $settings['enable_security_question'] ) : ?>
					<div class="scf-field" style="margin-bottom: 20px;">
						<label for="scf_security" style="
							display: block;
							margin-bottom: 8px;
							font-weight: 600;
						"><?php echo esc_html( $settings['security_question'] ); ?> <span style="color: red;">*</span></label>
						<input 
							type="text" 
							name="scf_security" 
							id="scf_security"
							required
							style="
								width: 100%;
								padding: 12px;
								border: 1px solid <?php echo esc_attr( $settings['form_border_color'] ); ?>;
								border-radius: <?php echo esc_attr( $settings['border_radius'] ); ?>px;
								box-sizing: border-box;
							"
						/>
					</div>
					<?php endif; ?>
					
					<div style="margin-bottom: 20px;">
						<button type="submit" style="
							background-color: <?php echo esc_attr( $settings['button_bg_color'] ); ?>;
							color: <?php echo esc_attr( $settings['button_text_color'] ); ?>;
							border: none;
							border-radius: <?php echo esc_attr( $settings['border_radius'] ); ?>px;
							padding: 12px 24px;
							cursor: pointer;
							font-size: 16px;
							width: 100%;
						"><?php esc_html_e( 'Send Message', 'secure-contact-form' ); ?></button>
					</div>
				</form>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle form submission
	 */
	public function handle_form_submission() {
		if ( ! isset( $_POST['scf_action'] ) ) {
			return;
		}

		if ( 'consent' === $_POST['scf_action'] ) {
			$this->handle_consent_submission();
		} elseif ( 'submit' === $_POST['scf_action'] ) {
			$this->handle_contact_submission();
		}
	}

	/**
	 * Handle consent submission
	 */
	private function handle_consent_submission() {
		if ( ! isset( $_POST['scf_consent_nonce'] ) || 
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scf_consent_nonce'] ) ), 'scf_consent' ) ) {
			return;
		}

		if ( ! isset( $_POST['scf_consent_checkbox'] ) ) {
			return;
		}

		$ip = $this->get_user_ip();
		if ( empty( $ip ) ) {
			return;
		}

		$result = $this->database->record_consent( $ip );
		
		if ( ! session_id() ) {
			session_start();
		}
		
		if ( is_wp_error( $result ) ) {
			$_SESSION['scf_message'] = __( 'Failed to record consent. Please try again.', 'secure-contact-form' );
			$_SESSION['scf_message_type'] = 'error';
		}

		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Handle contact form submission
	 */
	private function handle_contact_submission() {
		if ( ! session_id() ) {
			session_start();
		}

		// Verify nonce
		if ( ! isset( $_POST['scf_nonce'] ) || 
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scf_nonce'] ) ), 'scf_submit' ) ) {
			$_SESSION['scf_message'] = __( 'Security check failed.', 'secure-contact-form' );
			$_SESSION['scf_message_type'] = 'error';
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		$settings = $this->get_settings();
		$ip = $this->get_user_ip();
		$session = $this->get_session_id();

		// Check IP consent
		if ( ! $this->database->has_ip_consented( $ip ) ) {
			$_SESSION['scf_message'] = __( 'You must accept the Privacy Policy first.', 'secure-contact-form' );
			$_SESSION['scf_message_type'] = 'error';
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// Check rate limit
		if ( ! $this->database->check_rate_limit( $ip, $session ) ) {
			$_SESSION['scf_message'] = __( 'Too many submissions. Please try again later.', 'secure-contact-form' );
			$_SESSION['scf_message_type'] = 'error';
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// Anti-spam: Traditional honeypot
		if ( ! empty( $_POST['name'] ) ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// Anti-spam: Dynamic URL honeypot
		if ( isset( $_SESSION['scf_honeypot_url'] ) ) {
			$honeypot_field = $_SESSION['scf_honeypot_url'];
			if ( ! empty( $_POST[ $honeypot_field ] ) ) {
				wp_safe_redirect( wp_get_referer() );
				exit;
			}
		}

		// Anti-spam: Time-based validation
		if ( isset( $_POST['scf_timestamp'] ) && isset( $_SESSION['scf_timestamp'] ) ) {
			$form_time = intval( $_POST['scf_timestamp'] );
			$session_time = intval( $_SESSION['scf_timestamp'] );
			$elapsed = time() - $session_time;
			$min_time = intval( $settings['min_submit_time'] );

			if ( $form_time !== $session_time || $elapsed < $min_time ) {
				$_SESSION['scf_message'] = __( 'Submission too fast. Please wait a moment.', 'secure-contact-form' );
				$_SESSION['scf_message_type'] = 'error';
				wp_safe_redirect( wp_get_referer() );
				exit;
			}
		}

		// Anti-spam: Security question
		if ( '1' === $settings['enable_security_question'] ) {
			if ( ! isset( $_POST['scf_security'] ) ) {
				$_SESSION['scf_message'] = __( 'Please answer the security question.', 'secure-contact-form' );
				$_SESSION['scf_message_type'] = 'error';
				wp_safe_redirect( wp_get_referer() );
				exit;
			}

			$user_answer = sanitize_text_field( wp_unslash( $_POST['scf_security'] ) );
			$correct_answer = $settings['security_answer'];

			if ( strcasecmp( trim( $user_answer ), trim( $correct_answer ) ) !== 0 ) {
				$_SESSION['scf_message'] = __( 'Security question answer is incorrect.', 'secure-contact-form' );
				$_SESSION['scf_message_type'] = 'error';
				wp_safe_redirect( wp_get_referer() );
				exit;
			}
		}

		// Validate required fields
		if ( ! isset( $_POST['honeypot'] ) || empty( $_POST['honeypot'] ) ) {
			$_SESSION['scf_message'] = __( 'Subject is required.', 'secure-contact-form' );
			$_SESSION['scf_message_type'] = 'error';
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		if ( ! isset( $_POST['scf_message'] ) || empty( $_POST['scf_message'] ) ) {
			$_SESSION['scf_message'] = __( 'Message is required.', 'secure-contact-form' );
			$_SESSION['scf_message_type'] = 'error';
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// Sanitize data
		$subject = sanitize_text_field( wp_unslash( $_POST['honeypot'] ) );
		$message = sanitize_textarea_field( wp_unslash( $_POST['scf_message'] ) );
		$name = isset( $_POST['scf_real_name'] ) ? sanitize_text_field( wp_unslash( $_POST['scf_real_name'] ) ) : '';
		$email = isset( $_POST['scf_email'] ) ? sanitize_email( wp_unslash( $_POST['scf_email'] ) ) : '';
		$phone = isset( $_POST['scf_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['scf_phone'] ) ) : '';
		$dropdown = isset( $_POST['scf_dropdown'] ) ? sanitize_text_field( wp_unslash( $_POST['scf_dropdown'] ) ) : '';

		// Build email body
		$email_body = "New contact form submission\n\n";
		
		if ( ! empty( $name ) ) {
			$email_body .= "Name: {$name}\n";
		}
		if ( ! empty( $email ) ) {
			$email_body .= "Email: {$email}\n";
		}
		if ( ! empty( $phone ) ) {
			$email_body .= "Phone: {$phone}\n";
		}
		if ( ! empty( $dropdown ) ) {
			$email_body .= "Selection: {$dropdown}\n";
		}
		
		$email_body .= "Subject: {$subject}\n\n";
		$email_body .= "Message:\n{$message}\n\n";
		$email_body .= "---\n";
		$email_body .= "Submitted from: " . home_url() . "\n";
		$email_body .= "IP Address: {$ip}\n";
		$email_body .= "Timestamp: " . current_time( 'mysql' );

		// Get recipients
		$recipients = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$recipient = $settings[ 'recipient_email_' . $i ];
			if ( ! empty( $recipient ) && is_email( $recipient ) ) {
				$recipients[] = $recipient;
			}
		}

		if ( empty( $recipients ) ) {
			$_SESSION['scf_message'] = __( 'No valid recipient email configured.', 'secure-contact-form' );
			$_SESSION['scf_message_type'] = 'error';
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// Send email
		$email_sent = false;
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		
		if ( ! empty( $email ) ) {
			$headers[] = 'Reply-To: ' . $email;
		}

		if ( 'wp_mail' === $settings['email_method'] ) {
			$email_sent = wp_mail( $recipients, $subject, $email_body, $headers );
		} else {
			$to = implode( ', ', $recipients );
			$email_sent = mail( $to, $subject, $email_body, implode( "\r\n", $headers ) );
		}

		if ( $email_sent ) {
			$this->database->record_submission( $ip, $session );
			$_SESSION['scf_message'] = __( 'Thank you! Your message has been sent successfully.', 'secure-contact-form' );
			$_SESSION['scf_message_type'] = 'success';
		} else {
			$_SESSION['scf_message'] = __( 'Failed to send email. Please try again later.', 'secure-contact-form' );
			$_SESSION['scf_message_type'] = 'error';
		}

		wp_safe_redirect( wp_get_referer() );
		exit;
	}
}
