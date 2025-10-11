/**
 * Secure Contact Form - Public JavaScript
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		
		// Add visual feedback for form interactions
		$('.scf-contact-form input, .scf-contact-form textarea, .scf-contact-form select').on('focus', function() {
			$(this).closest('.scf-field').addClass('scf-field-active');
		}).on('blur', function() {
			$(this).closest('.scf-field').removeClass('scf-field-active');
		});
		
		// Add loading state to submit button
		$('.scf-contact-form').on('submit', function() {
			var $button = $(this).find('.scf-submit-button');
			$button.prop('disabled', true);
			$button.html('<i class="fas fa-spinner fa-spin"></i> Sending...');
		});
		
		// Add loading state to consent button
		$('.scf-consent-form').on('submit', function() {
			var $button = $(this).find('.scf-consent-button');
			$button.prop('disabled', true);
			$button.html('<i class="fas fa-spinner fa-spin"></i> Processing...');
		});
		
		// Smooth scroll to form if there are errors
		if ($('.scf-errors').length > 0) {
			$('html, body').animate({
				scrollTop: $('.scf-form-wrapper').offset().top - 100
			}, 500);
		}
		
		// Auto-dismiss success message after 10 seconds
		if ($('.scf-success').length > 0) {
			setTimeout(function() {
				$('.scf-success').fadeOut(500);
			}, 10000);
		}
		
		// Character counter for message field (visual enhancement)
		var $messageField = $('#scf_message');
		if ($messageField.length > 0) {
			var $counter = $('<div class="scf-char-counter"></div>');
			$messageField.closest('.scf-field').append($counter);
			
			$messageField.on('input', function() {
				var length = $(this).val().length;
				$counter.text(length + ' characters');
				
				if (length > 1000) {
					$counter.css('color', '#d11c1c');
				} else {
					$counter.css('color', 'rgba(255, 255, 255, 0.6)');
				}
			});
		}
		
		// Add glitch effect on form load (cyberpunk aesthetic)
		$('.scf-form-wrapper, .scf-consent-wrapper').addClass('scf-glitch-load');
		setTimeout(function() {
			$('.scf-form-wrapper, .scf-consent-wrapper').removeClass('scf-glitch-load');
		}, 300);
	});
	
})(jQuery);
