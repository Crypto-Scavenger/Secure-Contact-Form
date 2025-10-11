/**
 * Secure Contact Form - Admin JavaScript
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		
		// Initialize color pickers
		if ($.fn.wpColorPicker) {
			$('.scf-color-picker').wpColorPicker();
		}
		
		// Toggle security question fields visibility
		$('input[name="enable_security_question"]').on('change', function() {
			var $questionFields = $('#security_question, #security_answer').closest('tr');
			if ($(this).is(':checked')) {
				$questionFields.show();
			} else {
				$questionFields.hide();
			}
		}).trigger('change');
		
		// Toggle dropdown options field visibility
		$('input[name="enable_dropdown"]').on('change', function() {
			var $dropdownFields = $('#dropdown_options').closest('tr');
			if ($(this).is(':checked')) {
				$dropdownFields.show();
			} else {
				$dropdownFields.hide();
			}
		}).trigger('change');
		
		// Limit dropdown options to 5 lines
		$('#dropdown_options').on('input', function() {
			var lines = $(this).val().split('\n');
			if (lines.length > 5) {
				lines = lines.slice(0, 5);
				$(this).val(lines.join('\n'));
			}
		});
		
		// Show/hide field label inputs based on enabled fields
		function toggleFieldSettings() {
			var fieldsToToggle = ['name', 'email', 'phone', 'dropdown'];
			
			$.each(fieldsToToggle, function(index, field) {
				var isEnabled = $('input[name="enable_' + field + '"]').is(':checked');
				var $labelField = $('#label_' + field).closest('tr');
				var $placeholderField = $('#placeholder_' + field).closest('tr');
				
				if (isEnabled) {
					$labelField.show();
					if ($placeholderField.length) {
						$placeholderField.show();
					}
				} else {
					$labelField.hide();
					if ($placeholderField.length) {
						$placeholderField.hide();
					}
				}
			});
		}
		
		$('input[name^="enable_"]').on('change', toggleFieldSettings);
		toggleFieldSettings();
		
		// Email validation
		$('#email_recipients').on('blur', function() {
			var emails = $(this).val().split(',');
			var invalidEmails = [];
			
			$.each(emails, function(index, email) {
				email = email.trim();
				if (email && !isValidEmail(email)) {
					invalidEmails.push(email);
				}
			});
			
			if (invalidEmails.length > 0) {
				alert('Invalid email address(es): ' + invalidEmails.join(', '));
			}
		});
		
		// Simple email validation
		function isValidEmail(email) {
			var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			return regex.test(email);
		}
		
		// Limit email recipients to 3
		$('#email_recipients').on('blur', function() {
			var emails = $(this).val().split(',');
			if (emails.length > 3) {
				alert('Maximum 3 email recipients allowed. Only the first 3 will be used.');
			}
		});
		
		// Update rate limit description dynamically
		$('#rate_limit_max, #rate_limit_window').on('input', function() {
			var max = $('#rate_limit_max').val() || '5';
			var window = $('#rate_limit_window').val() || '60';
			
			var $description = $(this).closest('tr').find('.description');
			if (!$description.length) {
				$description = $('#rate_limit_window').closest('tr').find('.description');
			}
			
			if ($description.length) {
				$description.html('Allow maximum <strong>' + max + '</strong> submissions per <strong>' + window + '</strong> minutes from the same IP address.');
			}
		});
		
		// Copy shortcode to clipboard
		$(document).on('click', 'code', function() {
			var $code = $(this);
			var text = $code.text();
			
			// Create temporary input
			var $temp = $('<input>');
			$('body').append($temp);
			$temp.val(text).select();
			document.execCommand('copy');
			$temp.remove();
			
			// Visual feedback
			var originalText = $code.text();
			$code.text('Copied!');
			$code.css('background', '#00a32a');
			$code.css('color', '#fff');
			
			setTimeout(function() {
				$code.text(originalText);
				$code.css('background', '');
				$code.css('color', '');
			}, 1500);
		});
		
	});
	
})(jQuery);
