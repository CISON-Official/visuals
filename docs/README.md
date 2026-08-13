# Visuals Project Documentation

## What This Project Is
`Visuals` is a WordPress plugin that sits on top of WordPress, WooCommerce, Gravity Forms, and BuddyPress/BuddyBoss to support CISON registration and member workflows.

It combines several related areas:
- conference registration and checkout
- bulk attendee registration
- professional examination registration
- Gravity Forms entry dashboards
- BuddyPress profile extensions and security gates
- a student upgrade request workflow
- a certificate listing/profile tab

This repository is not a single-purpose plugin. It is a collection of feature modules that share the same WordPress install, database, and user/session state.

## Entry Point
The plugin bootstrap is [`main.php`](../main.php).

It defines the plugin metadata, loads the schema bootstrap, and then includes the feature modules in this order:
- database bootstrap
- Gravity Forms entry display
- conference and examination database helpers
- PRS dashboards
- profile and security modules
- form modules
- authentication and menu helpers
- corporate signup/navigation modules
- template and student upgrade controller

## Project Map
- [`docs/architecture.md`](architecture.md): overall architecture and runtime flow
- [`docs/database.md`](database.md): database tables and schema behavior
- [`docs/shortcodes.md`](shortcodes.md): every shortcode in the plugin
- [`docs/forms.md`](forms.md): form flows, AJAX endpoints, checkout behavior
- [`docs/profile-and-security.md`](profile-and-security.md): BuddyPress/BuddyBoss and security logic
- [`docs/admin.md`](admin.md): admin menus and review screens
- [`docs/reference.md`](reference.md): file-by-file module reference
- [`docs/known-issues.md`](known-issues.md): implementation mismatches and operational caveats

## Quick Summary
### Frontend-facing pieces
- `signup_page_shortcode` renders the two-panel corporate/member signup landing page.
- `registration_wc_checkout` renders the conference registration wizard and opens WooCommerce checkout in a modal.
- `nsa_registration_form` renders the multi-step bulk organization registration flow.
- `cison_examination_registration_form_shortcode` renders the examination registration form.
- `display_gf_entries` and the PRS shortcodes render Gravity Forms entry tables.

### Admin-facing pieces
- `Conference Registrations` under Tools provides CRUD access to `nsa_registrations`.
- `Upgrade Requests` under Tools provides student upgrade review.
- Gravity Forms dashboards are restricted to specific WordPress user IDs.

### Profile extensions
- Corporate users get custom BuddyPress nav tabs.
- Selected users can send email from a profile tab.
- Selected users get a secure-links profile section.
- A certificates tab lists certificate records for the displayed user.

## Dependencies
The plugin expects these systems to be available in the site:
- WordPress
- WooCommerce
- Gravity Forms
- BuddyPress or BuddyBoss

Some features also assume:
- `wc_get_orders`, `WC()->cart`, and WooCommerce checkout templates exist
- `GFAPI` is available for Gravity Forms entry access
- BuddyPress xprofile functions and nav APIs are available

## Data Sources
This plugin stores and reads from these custom tables:
- `wp_nsa_registrations`
- `wp_cison_examination_registrations`
- `wp_user_certificates`
- `wp_cison_upgrade_requests`

Table prefixes follow the site `wp_` prefix at runtime.

## Notes
- `testing.js` is a loose support file and does not appear to be loaded by `main.php`.
- `info.md` contains deployment-specific IDs and a partial payment UI snippet.
- Some module names and shortcode names are inconsistent across files. See [`docs/known-issues.md`](known-issues.md).
