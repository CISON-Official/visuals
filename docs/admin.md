# Admin Screens

## Conference Registrations
File: [`src/templates/conference_table.php`](../src/templates/conference_table.php)

### Menu
Adds a Tools submenu called `Conference Registrations`.

### Main features
- searchable list table
- sortable columns
- bulk delete
- single-row edit
- single-row delete
- WooCommerce purchase verification from the edit screen

### Table class
[`src/templates/class-crt-list-table.php`](../src/templates/class-crt-list-table.php) powers the list view.

Columns include:
- ID
- Member ID
- First Name
- Last Name
- Email
- Phone
- Registering For
- Payment Status
- Registered On

### Row actions
Each row supports:
- edit
- delete

### WooCommerce verification
When WooCommerce is active, the edit screen can check whether the row’s email has a paid order that matches the `registering_for` text.

### Admin-post handlers
- `crt_update_entry`
- `crt_delete_entry`
- `crt_bulk_delete`

### AJAX handler
- `crt_check_woo_purchase`

## Student Upgrade Requests
File: [`src/student-member-upgrade.php`](../src/student-member-upgrade.php)

### Menu
Adds `Upgrade Requests` under Tools.

### Admin view
The review page supports filtering by:
- pending
- approved
- rejected
- all

### Review actions
Admins can:
- approve
- reject

### Side effects on approval
When approved, the module:
- updates the request row to `approved`
- changes the member type to `statistician-member` when BuddyPress supports it
- clears the cached paid-fees transient for that user

### Front-end trigger
Student members see a profile-header button that submits a request through AJAX.

## Database Bootstrap on Admin Pages
`main.php` registers `visuals_init_database()` on:
- plugin activation
- `admin_init`

This ensures schema updates are applied when admins visit the site.

## Admin UX Notes
The admin screens use:
- WordPress core styles
- `WP_List_Table`
- inline scripts for purchase checking
- inline forms and notices for status changes
