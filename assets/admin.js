/**
 * Admin scripts for Secure Contact Form
 *
 * @package SecureContactForm
 * @since 1.0.0
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Initialize color pickers
		$('.scf-color-picker').wpColorPicker();

		// Tab switching
		$('.nav-tab-wrapper .nav-tab').on('click', function(e) {
			e.preventDefault();
			
			var target = $(this).attr('href');
			
			// Update active tab
			$('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
			$(this).addClass('nav-tab-active');
			
			// Show target tab content
			$('.scf-tab-content').hide();
			$(target).show();
		});
	});
})(jQuery);
