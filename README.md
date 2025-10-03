# Secure Contact Form

A WordPress plugin providing a secure, customizable contact form with advanced anti-spam protection and GDPR compliance.

## Description

Secure Contact Form is a comprehensive contact form solution designed with security, privacy, and user experience in mind. The plugin implements multiple layers of anti-spam protection while maintaining GDPR compliance through IP-based consent tracking.

## Features

### Core Functionality

- **Simple Shortcode**: Add contact forms anywhere with `[secure_contact_form]`
- **GDPR Compliance**: IP-based consent tracking with privacy policy integration
- **Rate Limiting**: Prevent submission flooding with configurable limits
- **Multiple Recipients**: Send notifications to up to 3 email addresses
- **Flexible Email Methods**: Choose between WordPress `wp_mail()` or PHP `mail()`

### Form Fields

**Required Fields:**
- Subject line
- Message text area
- Privacy Policy consent checkbox (with customizable link)

**Optional Fields (Enable/Disable):**
- Name field
- Email field
- Phone number field
- Dropdown selection (up to 5 custom options)

### Advanced Anti-Spam Protection

The plugin implements 6 layers of sophisticated anti-spam protection:

1. **Traditional Hidden Honeypot**
   - Hidden field with CSS positioning (`position: absolute; opacity: 0; z-index: -5`)
   - Uses common field name "name" to attract spam bots
   - Invisible to legitimate users

2. **Dynamic URL Honeypot**
   - Hidden URL field with randomized name (e.g., `user_websirsite_URL_1234`)
   - Field name changes on each page load
   - Uses `display: none` to hide from users

3. **Field Name Confusion**
   - The visible "Subject" field uses `name="honeypot"` instead of `name="subject"`
   - Creates confusion for bots expecting standard field names
   - Legitimate users see and fill the visible field normally

4. **Time-Based Submission Validation**
   - Tracks form load time vs submission time on server-side
   - Rejects submissions faster than configurable minimum (default: 3 seconds)
   - Server-side timestamp validation prevents client-side manipulation

5. **Security Question Challenge**
   - Optional custom question/answer pair
   - Case-insensitive answer matching
   - Admin can set any question and expected answer

6. **CSRF Protection**
   - WordPress nonce verification on all form submissions
   - Prevents cross-site request forgery attacks
   - Session-based validation

### Visual Customization

Complete control over form appearance:

- **Colors:**
  - Form background color
  - Border color
  - Text color
  - Button background color
  - Button text color

- **Layout:**
  - Border radius for all elements (form, fields, buttons)
  - Responsive design optimized for mobile devices
  - Clean, modern interface

- **Field Customization:**
  - Custom labels for all fields
  - Custom placeholder text
  - Fully translatable

## Installation

1. Upload the `secure-contact-form` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Tools → Contact Form** to configure settings
4. Add the shortcode `[secure_contact_form]` to any page or post

## Usage

### Basic Setup

1. Go to **Tools → Contact Form** in WordPress admin
2. Configure email recipients under the "Email Settings" tab
3. Customize form fields under the "Form Fields" tab
4. Adjust anti-spam settings under the "Anti-Spam" tab
5. Design the form appearance under the "Visual Design" tab
6. Add `[secure_contact_form]` shortcode to your page

### GDPR Compliance

The plugin implements GDPR compliance through IP consent tracking:

- Users must accept the Privacy Policy before submitting the form
- IP addresses are stored only after explicit consent
- Consent is required only once per IP address
- Consent data can be removed on plugin uninstall

### Rate Limiting

Protection against submission flooding:

- Configure maximum submissions per time window
- Default: 5 submissions per 60 minutes
- Tracked by IP address and session ID
- Old records automatically cleaned up

### Anti-Spam Configuration

1. **Minimum Submit Time**: Set the minimum seconds before form can be submitted (default: 3)
2. **Security Question**: Enable and customize the security challenge
3. **Rate Limiting**: Adjust max submissions and time window

### Form Customization

**Email Settings:**
- Add up to 3 recipient email addresses
- Choose email sending method

**Required Fields:**
- Customize labels and placeholders for Subject and Message
- Set Privacy Policy label and link

**Optional Fields:**
- Enable/disable Name, Email, Phone, and Dropdown fields
- Customize labels and placeholders for each
- Add up to 5 dropdown options

**Visual Design:**
- Use color pickers for instant visual feedback
- Adjust border radius for modern or traditional appearance
- Colors update in real-time preview

## File Structure

```
secure-contact-form/
├── secure-contact-form.php    # Main plugin file (initialization only)
├── README.md                   # This documentation
├── uninstall.php              # Cleanup on plugin deletion
├── index.php                  # Security stub
├── assets/                    # Plugin assets
│   ├── admin.css              # Admin page styles
│   ├── admin.js               # Admin page scripts (tabs, color picker)
│   ├── public.css             # Form styles
│   └── index.php              # Security stub
└── includes/                  # Plugin classes
    ├── class-database.php     # All database operations
    ├── class-core.php         # Core functionality (shortcode, form rendering, validation)
    ├── class-admin.php        # Admin interface
    └── index.php              # Security stub
```

## Database Structure

The plugin creates 3 custom tables to avoid bloating `wp_options`:

### Settings Table (`wp_scf_settings`)

```sql
CREATE TABLE wp_scf_settings (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    setting_key varchar(191) NOT NULL,
    setting_value longtext,
    PRIMARY KEY (id),
    UNIQUE KEY setting_key (setting_key)
)
```

Stores all plugin configuration including colors, labels, anti-spam settings, etc.

### IP Consents Table (`wp_scf_ip_consents`)

```sql
CREATE TABLE wp_scf_ip_consents (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    ip_address varchar(45) NOT NULL,
    consented_at datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY ip_address (ip_address),
    KEY consented_at (consented_at)
)
```

Tracks which IP addresses have consented to the Privacy Policy.

### Rate Limit Table (`wp_scf_rate_limit`)

```sql
CREATE TABLE wp_scf_rate_limit (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    ip_address varchar(45) NOT NULL,
    session_id varchar(64) NOT NULL,
    submission_count int(11) NOT NULL DEFAULT 0,
    last_submission datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY ip_session (ip_address, session_id),
    KEY last_submission (last_submission)
)
```

Tracks submission frequency to prevent flooding.

## Technical Details

### WordPress APIs Used

- **Plugin API**: Action and filter hooks for WordPress integration
- **Database API**: All queries use `$wpdb->prepare()` with placeholders
- **HTTP API**: Email sending via `wp_mail()` or PHP `mail()`
- **Session API**: PHP sessions for form state and rate limiting
- **Shortcode API**: Form rendering via `[secure_contact_form]`

### Security Implementation

**SQL Injection Prevention:**
- All database queries use `$wpdb->prepare()` with `%i` for table names and `%s`/`%d`/`%f` for values
- Compatible with WordPress 6.2+ identifier placeholder syntax
- Zero direct SQL string concatenation

**XSS Prevention:**
- All output escaped with appropriate context functions:
  - `esc_html()` for text content
  - `esc_attr()` for HTML attributes
  - `esc_url()` for URLs
  - `sanitize_hex_color()` for color values

**CSRF Protection:**
- WordPress nonces on all forms
- Separate nonces for consent and submission
- Session-based validation

**Input Validation:**
- All POST data sanitized with appropriate functions
- Email validation with `sanitize_email()` and `is_email()`
- URL validation with `esc_url_raw()`
- Text sanitization with `sanitize_text_field()`

**Capability Checks:**
- `manage_options` required for all admin operations
- Double verification in both render and save methods

### Performance Optimizations

**Lazy Loading:**
- Settings loaded only when needed (not in constructor)
- Prevents unnecessary database queries on every page load

**Conditional Asset Loading:**
- Public CSS only loads on pages with the shortcode
- Admin assets only load on plugin settings page

**Database Optimization:**
- Custom tables prevent wp_options bloat
- Indexed columns for fast lookups
- Automatic cleanup of old rate limit records

**Caching:**
- Settings cached in memory during request
- No repeated database queries for same data

## Requirements

- **WordPress**: 6.2 or higher (uses `%i` placeholder for table names)
- **PHP**: 7.4 or higher
- **MySQL**: 5.6+ or MariaDB 10.0+
- **Permissions**: `manage_options` capability for admin access

## Compatibility

- Works with all properly coded WordPress themes
- Compatible with major page builders
- No conflicts with caching plugins
- Multisite compatible
- Follows WordPress coding standards

## Frequently Asked Questions

### How do I add the form to my site?

Use the shortcode `[secure_contact_form]` on any page or post. The plugin will automatically load the necessary styles.

### Is the form GDPR compliant?

Yes. The plugin requires users to accept the Privacy Policy before submitting. IP addresses are only stored after explicit consent.

### What happens if someone doesn't accept the Privacy Policy?

They cannot use the form. A notice is displayed requiring them to accept before the form becomes available.

### Can I customize the form appearance?

Yes. Go to Tools → Contact Form → Visual Design tab to customize all colors and the border radius.

### How does the anti-spam protection work?

The plugin uses 6 layers of protection including hidden honeypots, field name confusion, time-based validation, security questions, and CSRF protection. This multi-layered approach catches most spam without requiring CAPTCHAs.

### Can I receive emails at multiple addresses?

Yes. You can configure up to 3 recipient email addresses in the Email Settings tab.

### What if the form stops working after updates?

Clear your browser cache and any WordPress caching plugins. The plugin follows WordPress standards and should work across updates.

### Will this affect my site's performance?

No. The plugin uses lazy loading and conditional asset enqueuing to minimize performance impact. Styles only load on pages with the shortcode.

### Can I translate the form?

Yes. All text is translatable using the `secure-contact-form` text domain. The plugin is ready for translation.

### What data is stored?

The plugin stores:
- Form settings in a custom database table
- IP addresses that have consented to Privacy Policy
- Rate limiting data (IP, session, submission count)

All data can be removed by enabling "Cleanup on Uninstall" in the settings.

## Privacy & GDPR

### Data Collection

The plugin collects and stores:

1. **IP Addresses**: Only after users explicitly consent to the Privacy Policy
2. **Form Submissions**: Sent via email, not stored in database
3. **Rate Limiting Data**: Temporary records for flood protection

### Data Retention

- IP consent records: Stored indefinitely until plugin uninstall (if cleanup enabled)
- Rate limit records: Automatically cleaned after 24 hours
- Form submissions: Not stored; only sent via email

### User Rights

Users can request:
- Consent withdrawal (admin must manually remove from database)
- Data export (admin must manually export IP records)
- Data deletion (enable cleanup on uninstall, or manually delete)

### GDPR Compliance Features

- Explicit consent required before form use
- Clear privacy policy link
- No third-party services or tracking
- All data stored locally in WordPress database
- Option to remove all data on uninstall

## Changelog

### Version 1.0.0
- Initial release
- Contact form with shortcode support
- IP-based GDPR consent tracking
- 6-layer anti-spam protection system
- Rate limiting for submission flooding
- Customizable form fields (required and optional)
- Multiple email recipients
- Complete visual customization
- Custom database tables for optimal performance
- Comprehensive security implementation
- Mobile-responsive design
- Full WordPress coding standards compliance

## Support

For issues or questions:
1. Check the FAQ section above
2. Review the plugin settings and ensure proper configuration
3. Test on a staging site before reporting issues
4. Provide detailed information about your environment (WordPress version, PHP version, active plugins, theme)
