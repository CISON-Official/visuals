# Forms and Checkout Flows

## Conference Registration
File: [`src/forms/conference.php`](../src/forms/conference.php)

### What it does
This module renders a registration form that:
- collects attendee identity and address information
- lets the user choose workshop, on-site conference, or virtual conference
- prevents selecting both on-site and virtual at the same time
- maps the selection to a WooCommerce product
- saves the registration to `nsa_registrations`
- opens checkout in a Bootstrap modal

### AJAX endpoints
- `add_to_cart_dynamic`
- `load_wc_checkout`
- `clear_cart`
- `save_registration`

### Payment/order linking
After payment completion, the module updates the saved registration row with:
- `order_id`
- `payment_status = paid`

### Product IDs in the current code
The form currently maps:
- workshop -> `12816`
- conference -> `12817`
- virtual -> `12818`
- workshop + conference -> `12670`
- workshop + virtual -> `12672`

### Front-end behavior
- validates required fields
- confirms email and confirm-email match
- serializes `registering_for` as a comma-joined string
- disables submit while checkout is being loaded

## Bulk Organization Registration
File: [`src/forms/organisation_conference.php`](../src/forms/organisation_conference.php)

### What it does
This is a multi-step bulk registration workflow.

It collects:
- global package selection
- organization billing details
- one or more attendee records

Then it:
- saves each attendee as a row in `nsa_registrations`
- stores inserted IDs in the WooCommerce session
- loads checkout into a modal
- links all saved rows to the resulting WooCommerce order

### AJAX endpoints
- `nsa_add_to_cart`
- `nsa_clear_cart`
- `nsa_load_checkout`
- `nsa_save_registrations`

### Registration rules
- workshop can be combined with either conference option
- conference must be either on-site or virtual
- the code prevents on-site and virtual from being selected together

### Stored extras
This module also writes:
- `who_paid` in the format `OrgName|orgemail`
- `hear_about` as a global value for all attendees

### Default behavior
- the first attendee card is added automatically
- checkout is loaded only after all validation passes

## Professional Examination Registration
Files:
- [`src/forms/mock-examination.php`](../src/forms/mock-examination.php)
- [`src/db/examination.php`](../src/db/examination.php)

### What it does
This form records exam candidate registrations with validation for:
- membership status
- membership ID
- title
- names
- email
- phone
- examination stage
- qualification
- payment platform
- country/state

### Validation rules
- membership ID is required for members
- email must be valid
- date of birth is normalized to `Y-m-d` when provided
- country is currently limited to Nigeria in the available options

### Persistence behavior
- if a row already exists for the email, the code updates it
- otherwise, it creates a new row and generates a reference number

## Scratch / Experimental JS
`testing.js` contains an older or alternate version of the bulk registration UI logic.

It is useful as reference material, but it is not part of the active PHP shortcode flow.
