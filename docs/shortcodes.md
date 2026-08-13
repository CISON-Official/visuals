# Shortcodes

## Conference and Registration Shortcodes
### `[registration_wc_checkout]`
Defined in [`src/forms/conference.php`](../src/forms/conference.php).

Purpose:
- renders the conference registration form and payment modal

Behavior:
- allows workshop, on-site conference, or virtual conference selection
- resolves the selected combination to a WooCommerce product ID
- saves the registration data by AJAX
- loads WooCommerce checkout into a Bootstrap modal

### `[nsa_registration_form]`
Defined in [`src/forms/organisation_conference.php`](../src/forms/organisation_conference.php).

Purpose:
- renders the multi-step bulk registration flow

Behavior:
- collects global package options
- collects organization billing data
- allows multiple attendee records to be added
- saves each attendee into `nsa_registrations`
- opens WooCommerce checkout in a modal

## Examination Shortcode
### `cison_examination_registration_form_shortcode`
Registered in [`src/forms/mock-examination.php`](../src/forms/mock-examination.php).

There is no explicit shortcode tag in the code snippet shown here, but the function is a shortcode renderer used for the professional examination registration form.

Purpose:
- renders the examination application form

Behavior:
- validates membership status
- generates or reuses a reference number
- inserts or updates the registration row
- returns success or validation messages inline

## Admin and PRS Shortcodes
### `[display_gf_entries]`
Defined in [`src/PRS/display.php`](../src/PRS/display.php).

Purpose:
- lists Gravity Forms entries in a simple table

Default attributes:
- `id=1`
- `product=12293`

Behavior:
- shows entry ID, name, email, submit date, and a paid/pending indicator
- uses WooCommerce order history to determine payment status

### `[all_user_entries]`
Defined in [`src/PRS/corporate.php`](../src/PRS/corporate.php).

Purpose:
- displays active Gravity Forms entries for a form

Default attributes:
- `form_id=15`

Access:
- restricted to specific WordPress user IDs

Behavior:
- shows name, email, company, position, questions, source, submission date, and payment state

### `[all_user_entries_for_students]`
Defined in [`src/PRS/student.php`](../src/PRS/student.php).

Purpose:
- displays student-oriented Gravity Forms entries

Behavior:
- restricted to selected users
- shows student name, email, phone, institution, university data, student ID, and payment state

### `[all_user_entries_for_company]`
Defined in [`src/PRS/company.php`](../src/PRS/company.php).

Purpose:
- displays company-oriented Gravity Forms entries

Behavior:
- restricted to selected users
- shows organization name, organization email, attendee count, submission date, and payment state

### `[all_users_paying_for_remaining_conference]`
Defined in [`src/PRS/remaining.php`](../src/PRS/remaining.php).

Purpose:
- displays another conference entry table with a different product-id check

Behavior:
- similar access model to the other PRS tables
- checks WooCommerce product `12721`

### `[signup_page_shortcode]`
Defined in [`src/corporate/signuppage.php`](../src/corporate/signuppage.php).

Purpose:
- renders a two-panel signup chooser for corporate vs member registration

Behavior:
- styled as a split-screen landing page
- links to the corporate and member signup destinations

### `[all_user_entries]` vs other PRS dashboards
The corporate PRS file also comments `Usage: [all_user_entries form_id="15"]`, which matches the registered shortcode there.

This name is reused as the main Gravity Forms dashboard shortcode for the corporate-style table.

## Registration Utility Note
The repository also contains `testing.js`, which mirrors and experiments with a similar registration/cart flow.

It is not registered as a shortcode and does not appear to be loaded from `main.php`.
