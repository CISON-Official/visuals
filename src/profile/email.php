<?php
/**
 * Design for the email tag
 * 
 */

add_action('bp_setup_nav', 'add_email_to_profile_tag', 100);

function add_email_to_profile_tag()
{
    $current_user = wp_get_current_user();
    $allowed_users = array(938, 2459);

    if (!in_array($current_user->ID, $allowed_users)) {
        return;
    }

    if (bp_is_my_profile()) {
        return;
    }

    bp_core_new_nav_item(
        array(
            'name' => __('Email', 'textdomain'),
            'slug' => 'send-email',
            'position' => 80,
            'screen_function' => 'custom_email_screen_view',
            'default_subnav_slug' => 'send-email',
            'item_css_id' => 'custom-email-tab'
        )
    );
}

function custom_email_screen_view()
{
    add_action('bp_template_content', 'custom_email_content_template');
    bp_core_load_template(apply_filters('bp_core_template_loader', 'members/single/plugins'));
}

add_action('wp_ajax_send_custom_user_email', 'handle_custom_user_email');

function handle_custom_user_email()
{
    // Security check
    check_ajax_referer('email_nonce', 'security');

    $subject = sanitize_text_field($_POST['subject']);
    $message = wp_kses_post($_POST['message']);
    $user_id = bp_displayed_user_id(); // The user whose profile we are on
    $recipient = get_userdata($user_id)->user_email;

    if (empty($subject) || empty($message)) {
        wp_send_json_error('Please fill in all fields.');
    }

    $sent = wp_mail($recipient, $subject, $message);

    if ($sent) {
        wp_send_json_success('Email sent successfully!');
    } else {
        wp_send_json_error('Email failed to send. Check server logs.');
    }
}

function custom_email_content_template()
{
    ?>
    <div class="email-section" style="padding: 20px; background: #f9f9f9; border-radius: 8px;">
        <h3>Send an Email to <?php echo bp_get_displayed_user_fullname(); ?></h3>

        <form id="custom-email-form">
            <?php wp_nonce_field('email_nonce', 'security'); ?>

            <p>
                <label>Subject</label><br>
                <input type="text" id="email_subject" name="subject" style="width:100%;">
            </p>

            <p>
                <label>Message</label><br>
                <textarea id="email_message" name="message" style="width:100%; height:150px;"></textarea>
            </p>

            <button type="button" id="submit-email-btn" class="button">Send Email</button>
            <div id="email-response-msg" style="margin-top: 15px; font-weight: bold;"></div>
        </form>

        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                $('#submit-email-btn').on('click', function (e) {
                    e.preventDefault();

                    var data = {
                        action: 'send_custom_user_email',
                        security: $('#security').val(),
                        subject: $('#email_subject').val(),
                        message: $('#email_message').val()
                    };

                    $('#email-response-msg').text('Sending...').css('color', '#333');

                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', data, function (response) {
                        if (response.success) {
                            $('#email-response-msg').text(response.data).css('color', 'green');
                            $('#custom-email-form')[0].reset();
                        } else {
                            $('#email-response-msg').text(response.data).css('color', 'red');
                        }
                    });
                });
            });
        </script>
    </div>
    <?php
}

/**
 * Dynamically change the "From" email to the logged-in user's email
 */
add_filter('wp_mail_from', function ($original_email) {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $allowed_users = array(938, 2459);

        if (!in_array($current_user->ID, $allowed_users)) {
            return $current_user->user_email;
        }
        return $original_email;
    }
    return $original_email;
});


/**
 * Dynamically change the "From" name to the logged-in user's display name
 */
add_filter('wp_mail_from_name', function ($original_name) {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $allowed_users = array(938, 2459);

        if (!in_array($current_user->ID, $allowed_users)) {
            return $current_user->display_name;
        }
        return $original_name;
    }
    return $original_name;
});