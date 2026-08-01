<?php

if (!defined('ABSPATH')) {
    exit; // No direct access.
}

/**
 * Resolve a safe internal redirect target.
 */
function evp_safe_ballot_redirect_target()
{
    $referer = wp_get_referer();
    return $referer ? $referer : home_url('/');
}

// -----------------------------------------------------------------------------
// POST HANDLER (admin-post.php)
// -----------------------------------------------------------------------------
add_action('admin_post_evp_cast_year_pikachu_vote', 'evp_handle_year_filtered_vote_submission');
add_action('admin_post_nopriv_evp_cast_year_pikachu_vote', 'evp_handle_year_filtered_vote_submission');

function evp_handle_year_filtered_vote_submission()
{
    error_log('EVP: Handler reached. POST data: ' . print_r($_POST, true));

    // 1. Nonce Verification
    if (!isset($_POST['evp_year_ballot_nonce']) || !wp_verify_nonce($_POST['evp_year_ballot_nonce'], 'evp_cast_year_vote')) {
        error_log("EVP Error: Nonce verification failed.");
        wp_safe_redirect(add_query_arg('vote_status', 'invalid_request', evp_safe_ballot_redirect_target()));
        exit;
    }

    global $wpdb;
    $table_elections = $wpdb->prefix . 'election_entries';
    $table_candidates = $wpdb->prefix . 'election_candidates';
    $table_voters = $wpdb->prefix . 'election_voters';

    $user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    $voter_name = $user_id ? $current_user->display_name : 'Anonymous Guest';
    $ip_address = preg_replace('/[^a-fA-F0-9:.]/', '', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');


    $stored_user_id = $user_id > 0 ? $user_id : null;
    $stored_ip = $ip_address;
    error_log("EVP: IP Address: $ip_address, User_ID $user_id, Stored IP: $stored_ip");

    $votes = isset($_POST['votes']) ? (array) wp_unslash($_POST['votes']) : [];
    $ballot_year = isset($_POST['ballot_year']) ? sanitize_text_field(wp_unslash($_POST['ballot_year'])) : '';

    if (empty($votes) || empty($ballot_year) || !preg_match('/^\d{4}$/', $ballot_year)) {
        error_log("EVP Error: Votes array or ballot year was empty or invalid.");
        wp_safe_redirect(add_query_arg('vote_status', 'empty_ballot', evp_safe_ballot_redirect_target()));
        exit;
    }

    $recorded = 0;
    $already_voted = 0;

    foreach ($votes as $election_id => $candidate_id) {
        $election_id = intval($election_id);
        $candidate_id = intval($candidate_id);

        if ($election_id <= 0 || $candidate_id <= 0) {
            continue;
        }

        // 2. Validate Election Exists
        $election_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_elections} WHERE id = %d",
            $election_id
        ));

        if (!$election_exists) {
            error_log("EVP Error: Election ID {$election_id} does not exist.");
            continue;
        }

        // 3. Check if Already Voted
        if ($user_id > 0) {
            $has_voted = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_voters} WHERE election_id = %d AND user_id = %d",
                $election_id,
                $user_id
            ));
        } else {
            $has_voted = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_voters} WHERE election_id = %d AND ip_address = %s",
                $election_id,
                $ip_address
            ));
        }

        if ($has_voted > 0) {
            $already_voted++;
            continue;
        }

        // 4. Validate Candidate Belongs to Election
        $valid_candidate = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_candidates} WHERE id = %d AND election_id = %d",
            $candidate_id,
            $election_id
        ));

        if ($valid_candidate > 0) {
            $inserted = $wpdb->insert(
                $table_voters,
                [
                    'election_id' => $election_id,
                    'candidate_id' => $candidate_id,
                    'name' => $voter_name,
                    'user_id' => $stored_user_id,
                    'ip_address' => $stored_ip,
                ],
                ['%d', '%d', '%s', '%d', '%s']
            );

            if ($inserted !== false) {
                $recorded++;
            } else {
                error_log("EVP DB Insert Error: " . $wpdb->last_error);
                $already_voted++;
            }
        } else {
            error_log("EVP Error: Candidate ID {$candidate_id} is not valid for Election ID {$election_id}.");
        }
    }

    if ($recorded === 0 && $already_voted > 0) {
        $status = 'already_voted';
    } elseif ($already_voted > 0) {
        $status = 'partial';
    } elseif ($recorded > 0) {
        $status = 'success';
    } else {
        $status = 'empty_ballot';
    }

    error_log("EVP: Finished processing. Redirect status: '{$status}'");

    wp_safe_redirect(add_query_arg('vote_status', $status, evp_safe_ballot_redirect_target()));
    exit;
}

// -----------------------------------------------------------------------------
// SHORTCODE RENDERER: [election_ballot year="2026"]
// -----------------------------------------------------------------------------
add_shortcode('election_ballot', 'evp_render_year_ballot');
function evp_render_year_ballot($atts)
{
    global $wpdb;
    $table_elections = $wpdb->prefix . 'election_entries';
    $table_candidates = $wpdb->prefix . 'election_candidates';
    $table_voters = $wpdb->prefix . 'election_voters';

    $args = shortcode_atts([
        'year' => gmdate('Y'),
    ], $atts);

    $target_year = sanitize_text_field($args['year']);
    if (!preg_match('/^\d{4}$/', $target_year)) {
        $target_year = gmdate('Y');
    }

    $user_id = get_current_user_id();
    $ip_address = preg_replace('/[^a-fA-F0-9:.]/', '', $_SERVER['REMOTE_ADDR'] ?? '');

    $total_elections_this_year = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_elections} WHERE YEAR(created_at) = %s",
        $target_year
    ));

    $voted_elections_this_year = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT v.election_id) FROM {$table_voters} v
         INNER JOIN {$table_elections} e ON v.election_id = e.id
         WHERE YEAR(e.created_at) = %s AND ((%d > 0 AND v.user_id = %d) OR (%d = 0 AND v.ip_address = %s))",
        $target_year,
        $user_id,
        $user_id,
        $user_id,
        $ip_address
    ));

    ob_start();

    $fully_voted = $total_elections_this_year > 0 && $voted_elections_this_year >= $total_elections_this_year;

    if ($fully_voted && !isset($_GET['vote_status'])): ?>
        <div id="evp-voted-modal"
            style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:999999; display:flex; align-items:center; justify-content:center; cursor:pointer;">
            <div style="background:#fff; padding:40px; max-width:450px; border-radius:8px; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,0.3); cursor:default;"
                onclick="event.stopPropagation();">
                <span style="font-size:50px; color:#c5221f;">⚠️</span>
                <h2 style="margin-top:15px; color:#222;">Ballot Already Submitted</h2>
                <p style="font-size:16px; color:#555; line-height:1.5;">Our records indicate that you have already cast your
                    official votes for the <strong><?php echo esc_html($target_year); ?></strong> election track.</p>
                <p style="font-size:14px; color:#888; margin-top:20px;">Click anywhere on this screen to return to the homepage.
                </p>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="button"
                    style="display:inline-block; margin-top:15px; background:#0073aa; color:#fff; padding:10px 20px; text-decoration:none; border-radius:4px; font-weight:bold;">Return
                    Home</a>
            </div>
        </div>
        <script>
            console.log(`Living: ${$total_elections_this_year} > 0 && ${$voted_elections_this_year} >= ${$total_elections_this_year};`)
            document.getElementById('evp-voted-modal').addEventListener('click', function () {
                window.location.href = "<?php echo esc_url(home_url('/')); ?>";
            });
        </script>
        <?php
        return ob_get_clean();
    endif;

    $elections = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_elections} WHERE YEAR(created_at) = %s ORDER BY id ASC",
        $target_year
    ));

    if (isset($_GET['vote_status'])) {
        $status = sanitize_key(wp_unslash($_GET['vote_status']));
        if ($status === 'success') {
            return '<div style="background:#e6f4ea; border-left:4px solid #137333; padding:15px; color:#137333; font-weight:bold; margin-bottom:20px;">Ballot cast! Your votes for ' . esc_html($target_year) . ' have been logged.</div>';
        } elseif ($status === 'partial') {
            return '<div style="background:#fef7e0; border-left:4px solid #b06000; padding:15px; color:#b06000; font-weight:bold; margin-bottom:20px;">Some of your choices were recorded. Positions you\'d already voted on were skipped.</div>';
        } elseif ($status === 'already_voted') {
            return '<div style="background:#fce8e6; border-left:4px solid #c5221f; padding:15px; color:#c5221f; font-weight:bold; margin-bottom:20px;">You\'ve already voted in every position on this ballot.</div>';
        } elseif ($status === 'empty_ballot') {
            return '<div style="background:#fef7e0; border-left:4px solid #b06000; padding:15px; color:#b06000; font-weight:bold; margin-bottom:20px;">Submission rejected: Select at least one candidate choice.</div>';
        } elseif ($status === 'invalid_request') {
            return '<div style="background:#fce8e6; border-left:4px solid #c5221f; padding:15px; color:#c5221f; font-weight:bold; margin-bottom:20px;">Your session expired before this could be submitted. Please try again.</div>';
        }
    }

    if (empty($elections)) {
        return '<p>No election tracks found for the year ' . esc_html($target_year) . '.</p>';
    }
    ?>
    <style>
        .evp-year-ballot {
            max-width: 100%;
            margin: 20px auto;
            font-family: inherit;
            border: 1px solid #ddd;
            padding: 25px;
            border-radius: 6px;
            background: #fff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .evp-title {
            border-bottom: 3px double #0073aa;
            padding-bottom: 10px;
            margin-top: 0;
            color: #111;
        }

        .evp-position-block {
            margin-bottom: 25px;
            background: #fafafa;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #0073aa;
        }

        .evp-pos-heading {
            margin: 0 0 10px 0;
            font-size: 1.25rem;
            color: #222;
        }

        .evp-cand-row {
            display: block;
            margin-bottom: 10px;
            font-size: 1rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #eee;
            background: #fff;
            transition: 0.15s ease-in-out;
        }

        .evp-cand-row:hover {
            background: #f0f6fa;
            border-color: #0073aa;
        }

        .evp-cand-radio {
            margin-right: 10px !important;
            vertical-align: middle;
            transform: scale(1.1);
        }

        .evp-submit {
            background-color: #0073aa;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            font-size: 1.1rem;
            transition: background 0.2s;
        }

        .evp-submit:hover {
            background-color: #005177;
        }
    </style>
    <script>
        console.log("Living: ")
    </script>

    <div class="evp-year-ballot">
        <h2 class="evp-title">Ballot <?php echo esc_html($target_year); ?></h2>

        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="POST">
            <?php wp_nonce_field('evp_cast_year_vote', 'evp_year_ballot_nonce'); ?>
            <input type="hidden" name="action" value="evp_cast_year_pikachu_vote">
            <input type="hidden" name="ballot_year" value="<?php echo esc_attr($target_year); ?>">

            <?php foreach ($elections as $election):
                if ($user_id > 0) {
                    $already = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table_voters} WHERE election_id = %d AND user_id = %d",
                        $election->id,
                        $user_id
                    ));
                } else {
                    $already = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table_voters} WHERE election_id = %d AND ip_address = %s",
                        $election->id,
                        $ip_address
                    ));
                }
                if ($already > 0) {
                    continue;
                }

                $candidates = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table_candidates} WHERE election_id = %d ORDER BY id ASC",
                    $election->id
                ));

                if (empty($candidates))
                    continue;
                ?>
                <div class="evp-position-block">
                    <h3 class="evp-pos-heading"><?php echo esc_html($election->position); ?>
                        <small
                            style="font-weight:normal; font-size:0.85rem; color:#666;">(<?php echo esc_html($election->name); ?>)</small>
                    </h3>

                    <?php foreach ($candidates as $candidate): ?>
                        <label class="evp-cand-row">
                            <input type="radio" class="evp-cand-radio" name="votes[<?php echo intval($election->id); ?>]"
                                value="<?php echo intval($candidate->id); ?>" required>
                            <strong><?php echo esc_html($candidate->name); ?></strong>
                            <?php if (!empty($candidate->description)): ?>
                                — <span style="font-size:0.9rem; color:#555;"><?php echo esc_html($candidate->description); ?></span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <input type="submit" class="evp-submit" value="Submit My Ballot Choices">
        </form>
    </div>
    <?php
    return ob_get_clean();
}