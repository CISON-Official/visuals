# File Reference

## Root Files
### [`main.php`](../main.php)
Plugin bootstrap that includes every module.

### [`README.md`](../README.md)
Existing high-level project summary.

### [`info.md`](../info.md)
Deployment notes and hard-coded IDs for development/production.

### [`testing.js`](../testing.js)
Scratch / experimental JavaScript version of the bulk-registration UX.

## `src/`
### [`src/database.php`](../src/database.php)
Shared schema creation and upgrade helpers.

### [`src/auth.php`](../src/auth.php)
Guest access restriction and profile-cart clearing.

### [`src/menu.php`](../src/menu.php)
Adds a backend menu item for selected roles.

### `src/forms/`
- [`src/forms/conference.php`](../src/forms/conference.php): conference checkout form
- [`src/forms/organisation_conference.php`](../src/forms/organisation_conference.php): bulk organization registration
- [`src/forms/mock-examination.php`](../src/forms/mock-examination.php): exam registration form and validation helpers

### `src/db/`
- [`src/db/conference.php`](../src/db/conference.php): conference registration helpers and current checkout logic
- [`src/db/examination.php`](../src/db/examination.php): examination registration validation and persistence

### `src/PRS/`
- [`src/PRS/display.php`](../src/PRS/display.php): Gravity Forms dashboard shortcode
- [`src/PRS/corporate.php`](../src/PRS/corporate.php): corporate Gravity Forms dashboard shortcode
- [`src/PRS/student.php`](../src/PRS/student.php): student Gravity Forms dashboard shortcode
- [`src/PRS/company.php`](../src/PRS/company.php): company Gravity Forms dashboard shortcode
- [`src/PRS/remaining.php`](../src/PRS/remaining.php): remaining conference dashboard shortcode

### `src/profile/`
- [`src/profile/email.php`](../src/profile/email.php): profile email tab and AJAX mail sender
- [`src/profile/certificate.php`](../src/profile/certificate.php): certificate profile tab
- [`src/profile/secure.php`](../src/profile/secure.php): secure links profile tab
- [`src/profile/conference.php`](../src/profile/conference.php): BuddyPress member directory filtering and corporate tab behavior

### `src/corporate/`
- [`src/corporate/signuppage.php`](../src/corporate/signuppage.php): signup landing page shortcode
- [`src/corporate/nav.php`](../src/corporate/nav.php): corporate profile navigation changes
- [`src/corporate/default_member_dir.php`](../src/corporate/default_member_dir.php): member directory exclusion rules

### `src/templates/`
- [`src/templates/conference_table.php`](../src/templates/conference_table.php): admin conference registration controller
- [`src/templates/class-crt-list-table.php`](../src/templates/class-crt-list-table.php): list table implementation

### `src/student-member-upgrade.php`
Student member upgrade plugin/controller.

## Reading Order
If you want to understand the system quickly, read in this order:
1. [`main.php`](../main.php)
2. [`docs/architecture.md`](architecture.md)
3. [`docs/database.md`](database.md)
4. [`docs/forms.md`](forms.md)
5. [`docs/admin.md`](admin.md)
6. [`docs/profile-and-security.md`](profile-and-security.md)
7. [`docs/shortcodes.md`](shortcodes.md)

