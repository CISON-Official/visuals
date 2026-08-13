# Known Issues and Caveats

## Mismatched Certificate Table Names
The shared database bootstrap creates `user_certificates`, but [`src/profile/certificate.php`](../src/profile/certificate.php) queries `certificate_registry`.

That means the certificate profile tab will not see the table created by the shared schema unless another part of the site creates `certificate_registry`.

## Certificate Column Mismatch
The certificate schema in [`src/database.php`](../src/database.php) uses `created`, while the profile renderer expects `created_at`.

That causes date display issues unless the table is different from the shared schema.

## Multiple Gravity Forms Dashboard Variants
There are several PRS shortcodes with similar behavior and overlapping intent:
- `all_user_entries`
- `all_user_entries_for_students`
- `all_user_entries_for_company`
- `all_users_paying_for_remaining_conference`
- `display_gf_entries`

This is fine operationally, but the naming is inconsistent and easy to confuse.

## Hard-Coded User IDs
Several features are restricted by explicit WordPress user IDs:
- Gravity Forms dashboards
- secure links tab
- email tab
- student upgrade button logic
- profile access controls

This is site-specific and should be documented anywhere the plugin is deployed to a different environment.

## Hard-Coded Product IDs
The registration flows and payment checks use product IDs directly in code.

Examples include:
- `12293`
- `12721`
- `12816`
- `12817`
- `12818`
- `12670`
- `12672`

If products are recreated or moved, the plugin will need code updates.

## Mixed Schema Evolution Logic
The upgrade-request table is created in both the shared bootstrap and the student upgrade controller.

They are compatible in intent, but the definitions are not identical.

## Scratch File
[`testing.js`](../testing.js) appears to be an experimental or older version of the bulk registration UI.

It is not included from `main.php`, so it should be treated as reference material unless the loading path changes.

