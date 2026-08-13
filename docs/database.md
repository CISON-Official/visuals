# Database

## Shared Schema Bootstrap
[`src/database.php`](../src/database.php) owns the database setup.

It is called:
- on plugin activation
- on `admin_init`

The intent is to keep tables available even if the plugin is updated without a fresh activation.

## Tables
### `nsa_registrations`
Created by `create_nsa_registration_table()`.

Purpose:
- stores conference and bulk registration records

Key columns:
- `member_id`
- `registering_for`
- `title`
- `first_name`
- `last_name`
- `email`
- `phone`
- `occupation`
- `organisation`
- `street`
- `city`
- `state`
- `postcode`
- `country`
- `gender`
- `hear_about`
- `order_id`
- `payment_status`
- `registration_date`
- `ip_address`

Indexes:
- `member_id`
- `email`
- `order_id`
- `registration_date`

Schema evolution:
- `alter_nsa_registration_table()` adds `middle_name`
- it also adds or resizes `who_paid`

### `cison_examination_registrations`
Created by `create_examination_registration_table()`.

Purpose:
- stores professional examination applications

Key columns:
- `reference_number`
- `is_member`
- `membership_id`
- `middle_name`
- `title`
- `first_name`
- `last_name`
- `email`
- `phone`
- `gender`
- `date_of_birth`
- `examination_stage`
- `highest_qualification`
- `current_employer`
- `street`
- `city`
- `state`
- `country`
- `payment_platform`
- `payment_status`
- `application_status`
- `notes`
- `registration_date`
- `updated_at`
- `ip_address`

Indexes:
- `email`
- `reference_number`
- `examination_stage`
- `application_status`
- `registration_date`

Schema evolution:
- `alter_examination_registration_table()` backfills missing columns and indexes

### `user_certificates`
Created by `bbc_create_certificates_table()`.

Purpose:
- stores certificate metadata and file paths

Key columns:
- `user_id`
- `name`
- `description`
- `certificate_path`
- `created`
- `secret_token`
- `expire_date`

Note:
- this schema uses `created`, while the certificate profile module queries `created_at`
- see [`docs/known-issues.md`](known-issues.md)

### `cison_upgrade_requests`
Created by `bbc_create_student_upgrade_table()` and also by the student upgrade plugin class.

Purpose:
- stores student upgrade requests and review status

Key columns:
- `user_id`
- `status`
- `created_at`
- `updated_at`

The student upgrade plugin version uses a slightly different schema definition from the shared bootstrap, but both target the same table name.

## Query and Write Patterns
### Conference registration rows
Rows are inserted from the registration forms and later updated with:
- `order_id`
- `payment_status = paid`

### Examination registrations
The insertion helper first checks for an existing record by email.

If a row already exists:
- it updates the existing row
- it preserves the reference number when present

If no row exists:
- it inserts a new row
- it generates a new reference number

### Student upgrade requests
Each request is stored as a row with a status lifecycle:
- `pending`
- `approved`
- `rejected`

## Session and Transient Use
The database layer is complemented by:
- WooCommerce session values for registration tracking
- transients for short-lived profile cart clearing
- WordPress options for schema version tracking in the upgrade plugin
