# Architecture

## Plugin Shape
The codebase is a single WordPress plugin with multiple feature modules.

The bootstrap file [`main.php`](../main.php) is responsible for wiring everything together. It does not define a classic service container or framework layer. Instead, each module registers its own hooks and shortcodes as soon as it is included.

## Runtime Flow
1. WordPress loads the plugin.
2. `main.php` defines `VISUALS_PATH` and `VISUALS_URL`.
3. Database helpers are loaded and activation hooks are registered.
4. Feature modules are required.
5. Each module registers its own actions, filters, and shortcodes.

## Major Subsystems
### Database bootstrap
[`src/database.php`](../src/database.php) is the shared schema manager.

It creates and updates:
- `nsa_registrations`
- `cison_examination_registrations`
- `user_certificates`
- `cison_upgrade_requests`

### Gravity Forms dashboards
The PRS modules read Gravity Forms entries and show them as HTML tables.

These tables do not persist data themselves. They read entries from Gravity Forms and decorate them with WooCommerce purchase checks.

### WooCommerce registration flows
The conference and bulk-registration forms use AJAX to:
- add the correct product to cart
- save registration data in the custom table
- load the WooCommerce checkout into a modal
- link the saved rows back to the resulting order

### BuddyPress/BuddyBoss extensions
The profile modules add:
- conditional profile navigation
- secure-link tabs
- email-sending controls
- certificate listing views
- member directory filtering

### Admin tools
The admin side provides:
- conference registration list/edit/delete screens
- student upgrade request review
- database bootstrap on activation and `admin_init`

## State Handling
The plugin uses several state mechanisms:
- WordPress options for schema version tracking
- WordPress transients for one-day profile-cart clearing
- WooCommerce session data for registration data and registration IDs
- Gravity Forms entry data for live dashboards

## Permission Model
Access control is implemented in-module rather than centrally.

Examples:
- `manage_options` gates admin menus and sensitive dashboards
- selected WordPress user IDs gate certain Gravity Forms views
- corporate BuddyPress nav items are only added for users whose xprofile account type is `corporate`
- some profile tabs only appear on the current user’s own profile

## Data Flow Patterns
### Conference registration
Frontend form -> AJAX save -> `nsa_registrations` row -> WooCommerce checkout -> payment complete -> order ID + paid status written back to row.

### Bulk registration
Multi-step form -> attendee JSON -> multiple `nsa_registrations` rows -> checkout -> payment complete -> order ID + paid status written back to all saved rows.

### Examination registration
Frontend form -> validation -> insert/update in `cison_examination_registrations` -> reference number returned to the user.

### Upgrade requests
Profile button -> AJAX insert into `cison_upgrade_requests` -> admin review page -> status update -> member type promotion on approval.

## Styling Approach
Most UI is rendered inline within the PHP modules.

That means:
- front-end layout and CSS are tightly coupled to the PHP renderers
- many views are self-contained shortcode outputs
- some admin screens rely on WordPress default styles plus inline custom CSS
