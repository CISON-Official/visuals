# Profile and Security

## Guest Access Control
File: [`src/auth.php`](../src/auth.php)

### `cison_custom_guest_access_control`
Hooks:
- `bp_template_redirect`

Purpose:
- blocks anonymous access to most BuddyPress/BuddyBoss pages unless the URI matches an allowlist

Behavior:
- if the visitor is logged in, nothing is blocked
- if the visitor is a guest, the code checks the request URI against a public path allowlist
- if the URI is not public, `bp_core_no_access()` redirects to the login flow

### `cison_maybe_clear_profile_cart`
Hooks:
- `template_redirect`

Purpose:
- clears the WooCommerce cart when a logged-in user visits their own BuddyPress profile

Behavior:
- only runs for the current user’s own profile
- uses a transient to avoid clearing repeatedly for one day
- initializes the WooCommerce cart if needed
- empties the cart once, then caches that it has been done

## Email Profile Tab
File: [`src/profile/email.php`](../src/profile/email.php)

### Profile navigation
Adds a `send-email` tab for selected user IDs only.

### AJAX email sender
The module registers:
- `wp_ajax_send_custom_user_email`

Behavior:
- checks a nonce
- sends an email to the displayed profile owner
- returns JSON success or error messages

### Mail sender filters
The module customizes:
- `wp_mail_from`
- `wp_mail_from_name`

If the current user is not one of the allowed user IDs, outgoing mail uses the logged-in user’s email and display name.

## Certificates Tab
File: [`src/profile/certificate.php`](../src/profile/certificate.php)

### Profile navigation
Adds a `profile/certificates` tab.

### Data source
It reads certificate rows for the displayed user from a certificates table.

### Rendering
- shows an empty state when no certificates are found
- shows preview thumbnails for image files
- shows an expired/active badge based on `expire_date`
- links to the certificate file path when available

## Secure Links Tab
File: [`src/profile/secure.php`](../src/profile/secure.php)

### Profile navigation
Adds a `secure-section` tab for selected users only, and only on the user’s own profile.

### Content
The tab renders a curated set of secure links, including:
- attendee tables
- certificate verification pages
- PRS registration lists
- conference registration lists

## Corporate Profile Navigation
File: [`src/corporate/nav.php`](../src/corporate/nav.php)

### Account type detection
Reads BuddyPress xprofile field `1614` and treats `corporate` as the special account type.

### Corporate-only nav changes
For corporate users, it:
- removes `connections`
- removes `forums`
- removes `groups`
- adds `Payments`
- adds `Members`

### Corporate tab screens
The screen callbacks currently render placeholder content:
- "Your payments will appear here."
- "Your members will appear here."

## Member Directory Filtering
File: [`src/corporate/default_member_dir.php`](../src/corporate/default_member_dir.php)

### Purpose
Excludes corporate users from the BuddyBoss members directory and adjusts the displayed member count.

### Mechanism
- queries xprofile field `1618`
- filters out users whose value is not `Corporate`

## Security Model Summary
The plugin relies on several layers:
- explicit user ID allowlists
- WordPress capability checks
- BuddyPress profile ownership checks
- nonces on AJAX and admin-post actions
- login checks for guest access

This is adequate for a site-specific operational plugin, but it is not centralized or declarative.
