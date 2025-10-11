# Secure Contact Form - Installation Notes

## Complete File Structure

```
secure-contact-form/
├── secure-contact-form.php          # Main plugin file
├── README.md                         # Complete documentation
├── LICENSE                           # GPL v2 license (create manually)
├── INSTALLATION-NOTES.md            # This file
├── uninstall.php                    # Cleanup on plugin deletion
├── index.php                        # Security stub (root)
├── assets/
│   ├── admin.css                    # Admin interface styles
│   ├── admin.js                     # Admin interface scripts
│   ├── public.css                   # Frontend form styles
│   ├── public.js                    # Frontend form scripts
│   └── index.php                    # Security stub (copy from root)
└── includes/
    ├── class-database.php           # Database operations
    ├── class-admin.php              # Admin interface
    ├── class-core.php               # Core functionality
    └── index.php                    # Security stub (copy from root)
```

## Important: Security Stub Files

The `index.php` file containing:
```php
<?php
// Silence is golden
```

Must be copied to the following directories:
1. **Root directory** (`/secure-contact-form/index.php`)
2. **Assets directory** (`/secure-contact-form/assets/index.php`)
3. **Includes directory** (`/secure-contact-form/includes/index.php`)

This prevents directory listing and enhances security.

## License File

You need to create a separate `LICENSE` file containing the full GPL v2 license text.

You can obtain the GPL v2 license text from:
https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt

## Installation Steps

1. **Create the directory structure** as shown above
2. **Copy all provided files** to their respective locations
3. **Create the index.php security stubs** in all directories
4. **Add the GPL v2 LICENSE file** to the root directory
5. **Upload to WordPress**:
   - Upload the entire `secure-contact-form` folder to `/wp-content/plugins/`
   - Or zip the folder and upload via WordPress admin
6. **Activate the plugin** through the Plugins menu
7. **Configure settings** at Contact Form → Settings

## Initial Configuration

After activation:

1. **Go to Contact Form** in the WordPress admin menu
2. **General Tab**:
   - Set email recipients (default is admin email)
   - Choose email method (wp_mail recommended)
3. **Form Fields Tab**:
   - Enable desired optional fields
   - Customize labels and placeholders
4. **Anti-Spam Tab**:
   - Review default settings (recommended for most sites)
   - Optionally enable security question
5. **Styling Tab**:
   - Customize colors if needed (defaults to dark theme)
6. **Add shortcode** to a page: `[secure_contact_form]`

## Testing Checklist

After installation, test the following:

### Consent Flow
- [ ] Consent screen appears on first visit
- [ ] Privacy policy link works correctly
- [ ] Consent checkbox is required
- [ ] Form appears after consent
- [ ] Consent persists (no re-prompt on refresh)

### Form Submission
- [ ] All enabled fields display correctly
- [ ] Required fields show asterisks
- [ ] Form validates empty fields
- [ ] Email validation works (if email field enabled)
- [ ] Success message appears after submission
- [ ] Email arrives at configured recipient(s)

### Anti-Spam Protection
- [ ] Submitting too quickly shows error
- [ ] Security question works (if enabled)
- [ ] Rate limiting kicks in after max submissions
- [ ] Honeypot fields are invisible to users

### Styling
- [ ] Form displays correctly on desktop
- [ ] Form displays correctly on mobile
- [ ] Colors match configuration
- [ ] Icons display (requires Font Awesome on site)
- [ ] Animations work smoothly

### Admin Panel
- [ ] All tabs load correctly
- [ ] Settings save properly
- [ ] Color pickers work
- [ ] Field toggles show/hide related settings

## Troubleshooting

### Emails Not Sending
1. Test with both `wp_mail()` and PHP `mail()` methods
2. Check your server's email configuration
3. Install and configure an SMTP plugin
4. Check spam folder

### Form Not Displaying
1. Ensure shortcode is correct: `[secure_contact_form]`
2. Check if CSS/JS files are loading (browser console)
3. Clear browser cache
4. Check for JavaScript conflicts with other plugins

### Database Tables Not Created
1. Deactivate and reactivate the plugin
2. Check database user has CREATE TABLE permissions
3. Review error logs for SQL errors

### Rate Limiting Too Strict
1. Go to Anti-Spam tab
2. Increase "Maximum Submissions" value
3. Increase "Time Window" value

### Consent Screen Reappears
1. Check if PHP sessions are working
2. Verify IP address is being captured correctly
3. Check database table `wp_scf_consent` has records

## Database Information

### Tables Created
- `{prefix}_scf_settings` - Plugin configuration
- `{prefix}_scf_consent` - IP consent tracking
- `{prefix}_scf_rate_limits` - Submission rate limiting

### Data Cleanup
- Set "Cleanup on Uninstall" in Advanced tab
- When enabled, all tables are deleted on plugin deletion
- When disabled, data persists after uninstall

## Security Notes

### Honeypot Fields
The plugin uses invisible honeypot fields that should never be filled by real users. These include:
- Field with `name="name"` (traditional honeypot)
- Field with `name="website_URL_####"` (dynamic honeypot)
- The visible subject field uses `name="honeypot"` (field name confusion)

### Server Requirements
- PHP sessions must be enabled
- Write permissions for session storage
- MySQL/MariaDB with CREATE TABLE privileges

### Best Practices
1. Keep WordPress core updated
2. Use strong passwords for admin accounts
3. Regular database backups
4. Monitor submission logs
5. Adjust rate limiting based on your traffic

## Performance Notes

### Asset Loading
- Frontend CSS/JS only loads on pages with the shortcode
- Admin CSS/JS only loads on plugin settings page
- No assets load on other pages

### Database Queries
- Settings are cached after first load
- Rate limit cleanup
