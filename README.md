# **Visuals: Gravity Forms Entry Dashboard for WordPress**

## Overview
Visuals is a robust WordPress plugin designed to seamlessly integrate with Gravity Forms, providing administrators with a powerful shortcode to display a centralized dashboard of form entries. This plugin enhances data oversight by allowing active Gravity Forms entries to be viewed directly within any WordPress page or post, streamlining administrative tasks and improving accessibility to critical data.

## Features
-   **Gravity Forms Integration**: Connects directly with Gravity Forms API to fetch and display entry data.
-   **Shortcode Utility**: Provides an easy-to-use `[all_user_entries]` shortcode for embedding entry dashboards anywhere on your site.
-   **Admin-Exclusive Access**: Ensures data security by restricting entry viewing to users with `manage_options` capabilities (typically administrators).
-   **Active Entry Filtering**: Automatically filters out deleted or trashed entries, ensuring only relevant, active data is displayed.
-   **Customizable Display**: Allows specification of the Gravity Form ID to display entries from any specific form.
-   **Structured Data Output**: Presents entry data in a clear, sortable HTML table format, improving readability and data management.

## Getting Started
To get this plugin up and running on your WordPress site, follow these steps:

### Installation
1.  **Clone the Repository**:
    ```bash
    git clone git@github.com:CISON-Official/visuals.git
    ```
2.  **Upload to WordPress**:
    *   Navigate to your WordPress installation's `wp-content/plugins/` directory.
    *   Copy the entire `visuals` folder (from the cloned repository) into this directory.
    *   Alternatively, zip the `visuals` folder and upload it via the WordPress Admin dashboard: `Plugins` > `Add New` > `Upload Plugin`.
3.  **Activate the Plugin**:
    *   From your WordPress Admin dashboard, go to `Plugins` > `Installed Plugins`.
    *   Locate "Visuals" in the list and click "Activate".

### Environment Variables
This plugin does not explicitly require any environment variables. It relies on the standard WordPress environment and Gravity Forms plugin being active.

## Usage
Once the Visuals plugin is installed and activated, you can display Gravity Forms entries on any page or post using its shortcode.

1.  **Ensure Gravity Forms is Active**: This plugin requires Gravity Forms to be installed and active on your WordPress site.
2.  **Identify Your Form ID**:
    *   In your WordPress Admin, navigate to `Forms` > `Forms`.
    *   The ID for each form is typically displayed next to its name or in the URL when editing the form (e.g., `id=15`).
3.  **Insert the Shortcode**:
    *   Edit the page or post where you want the entries to appear.
    *   Add the `[all_user_entries]` shortcode, specifying the `form_id` attribute.
    *   **Example**: To display entries from Gravity Form with ID `15`:
        ```
        [all_user_entries form_id="15"]
        ```
    *   If no `form_id` is specified, it defaults to `15`.
4.  **View Entries**:
    *   Publish or update the page/post.
    *   When an administrator views this page, they will see a table containing active entries from the specified Gravity Form. Non-admin users will see an "Access Denied" message.

## Technologies Used
| Technology        | Description                                       |
| :---------------- | :------------------------------------------------ |
| **PHP**           | Primary programming language for the plugin.      |
| **WordPress**     | Content Management System platform.               |
| **Gravity Forms** | Forms builder plugin, providing data source.      |

## Contributing
We welcome contributions to the Visuals plugin! If you have suggestions for improvements, bug fixes, or new features, please consider contributing.

✨ **How to Contribute**:
*   🐛 **Report Bugs**: If you find a bug, please open an issue on the GitHub repository. Provide a detailed description and steps to reproduce.
*   🚀 **Suggest Features**: Have an idea for a new feature? Open an issue to discuss it.
*   🛠️ **Submit Pull Requests**:
    *   Fork the repository.
    *   Create a new branch for your feature or bug fix (`git checkout -b feature/your-feature-name` or `bugfix/your-bug-fix`).
    *   Make your changes, ensuring code quality and adherence to WordPress coding standards.
    *   Commit your changes (`git commit -m 'feat: Add new feature'`).
    *   Push to your branch (`git push origin feature/your-feature-name`).
    *   Open a pull request to the `main` branch of this repository.

## Author
**CISON**
Connect with us:
- LinkedIn: [Your LinkedIn Profile](https://www.linkedin.com/in/your-username/)
- X (formerly Twitter): [@YourTwitterHandle](https://x.com/YourTwitterHandle)

---

## Badges
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg?logo=php&logoColor=white)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![Gravity Forms](https://img.shields.io/badge/Gravity%20Forms-Required-orange.svg?logo=wordpress&logoColor=white)](https://www.gravityforms.com/)

## Dokugen Badge
[![Readme was generated by Dokugen](https://img.shields.io/badge/Readme%20was%20generated%20by-Dokugen-brightgreen)](https://www.npmjs.com/package/dokugen)