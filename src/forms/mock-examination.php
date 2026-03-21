<?php

function cison_get_examination_form_defaults()
{
    return array(
        'is_member' => '',
        'membership_id' => '',
        'title' => '',
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
        'gender' => '',
        'date_of_birth' => '',
        'examination_stage' => '',
        'highest_qualification' => '',
        'current_employer' => '',
        'street' => '',
        'city' => '',
        'state' => '',
        'state_manual' => '',
        'country' => 'NG',
        'payment_platform' => '',
        'notes' => '',
    );
}

function cison_get_examination_titles()
{
    return array('Mr', 'Mrs', 'Ms', 'Dr', 'Prof');
}

function cison_get_examination_genders()
{
    return array('Male', 'Female', 'Prefer Not to Answer');
}

function cison_get_examination_stages()
{
    return array(
        'Professional Stage I',
        'Professional Stage II',
        'Professional Stage III',
        'Professional Stage IV',
    );
}

function cison_get_examination_countries()
{
    return array(
        'NG' => 'Nigeria',
        // 'GH' => 'Ghana',
        // 'KE' => 'Kenya',
        // 'US' => 'United States',
        // 'GB' => 'United Kingdom',
    );
}

function cison_get_nigerian_states()
{
    return array(
        'Abia',
        'Adamawa',
        'Akwa Ibom',
        'Anambra',
        'Bauchi',
        'Bayelsa',
        'Benue',
        'Borno',
        'Cross River',
        'Delta',
        'Ebonyi',
        'Edo',
        'Ekiti',
        'Enugu',
        'Federal Capital Territory',
        'Gombe',
        'Imo',
        'Jigawa',
        'Kaduna',
        'Kano',
        'Katsina',
        'Kebbi',
        'Kogi',
        'Kwara',
        'Lagos',
        'Nasarawa',
        'Niger',
        'Ogun',
        'Ondo',
        'Osun',
        'Oyo',
        'Plateau',
        'Rivers',
        'Sokoto',
        'Taraba',
        'Yobe',
        'Zamfara',
    );
}

function cison_get_examination_payment_platforms()
{
    return array(
        'woocommerce_card' => 'WooCommerce Card Payment',
        'woocommerce_bank_transfer' => 'WooCommerce Bank Transfer',
        'woocommerce_ussd' => 'WooCommerce USSD',
        'woocommerce_transfer' => 'WooCommerce Account Transfer',
    );
}

function cison_get_examination_payment_platform_label($platform)
{
    $platforms = cison_get_examination_payment_platforms();

    return $platforms[$platform] ?? $platform;
}

function cison_get_examination_full_name($row)
{
    $parts = array_filter(array(
        $row['first_name'] ?? '',
        $row['middle_name'] ?? '',
        $row['last_name'] ?? '',
    ));

    return implode(' ', $parts);
}

function cison_render_examination_status_badge($status, $modifier = '')
{
    $normalized = strtolower(trim((string) $status));
    $classes = trim('cison-exam-badge cison-exam-badge--' . sanitize_html_class($normalized ?: 'unknown') . ' ' . $modifier);

    return sprintf(
        '<span class="%s">%s</span>',
        esc_attr($classes),
        esc_html($status ?: 'N/A')
    );
}

function cison_examination_registration_form_shortcode()
{
    $values = cison_get_examination_form_defaults();
    $feedback_message = '';
    $feedback_type = '';

    if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['cison_examination_registration_submit'])) {
        $values = array_merge($values, wp_unslash($_POST));

        if (!isset($_POST['cison_examination_registration_nonce']) || !wp_verify_nonce($_POST['cison_examination_registration_nonce'], 'cison_examination_registration_action')) {
            $feedback_message = 'Security check failed. Please try again.';
            $feedback_type = 'error';
        } else {
            $registration = cison_insert_examination_registration(wp_unslash($_POST));

            if (is_wp_error($registration)) {
                $feedback_message = $registration->get_error_message();
                $feedback_type = 'error';
            } else {
                $feedback_message = sprintf(
                    'Your professional examination registration has been %s successfully. Reference Number: %s.',
                    !empty($registration['updated']) ? 'updated' : 'submitted',
                    $registration['reference_number']
                );
                $feedback_type = 'success';
                $values = cison_get_examination_form_defaults();
            }
        }
    }

    $titles = cison_get_examination_titles();
    $genders = cison_get_examination_genders();
    $stages = cison_get_examination_stages();
    $countries = cison_get_examination_countries();
    $nigerian_states = cison_get_nigerian_states();
    $payment_platforms = cison_get_examination_payment_platforms();
    $manual_state_value = 'NG' === ($values['country'] ?? 'NG')
        ? ($values['state_manual'] ?? '')
        : ($values['state_manual'] ?: $values['state']);

    ob_start();
    ?>
    <div class="cison-exam-registration">
        <div class="cison-exam-registration__header">
            <h3>Professional Examination Registration</h3>
            <p>Complete the form below to register as a candidate for the professional examination.</p>
        </div>

        <?php if ($feedback_message): ?>
            <div class="cison-exam-registration__alert cison-exam-registration__alert--<?php echo esc_attr($feedback_type); ?>">
                <?php echo esc_html($feedback_message); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="cison-exam-registration__form" novalidate>
            <?php wp_nonce_field('cison_examination_registration_action', 'cison_examination_registration_nonce'); ?>

            <div class="cison-exam-registration__grid cison-exam-registration__grid--two">
                <div>
                    <label for="cison_exam_is_member">Are you a CISON Member? <span>*</span></label>
                    <select id="cison_exam_is_member" name="is_member" required>
                        <option value="">Select</option>
                        <option value="yes" <?php selected($values['is_member'], 'yes'); ?>>Yes</option>
                        <option value="no" <?php selected($values['is_member'], 'no'); ?>>No</option>
                    </select>
                </div>

                
                <div class="cison-exam-registration__grid js-member-id-field" style="<?php echo 'yes' === $values['is_member'] ? '' : 'display:none;'; ?>">
                    <div>
                        <label for="cison_exam_membership_id">CISON Membership ID <span>*</span></label>
                        <input id="cison_exam_membership_id" type="text" name="membership_id"
                            value="<?php echo esc_attr($values['membership_id']); ?>"
                            <?php echo 'yes' === $values['is_member'] ? 'required' : ''; ?>>
                    </div>
                </div>
            </div>

            <div class="cison-exam-registration__grid ">
                <div>
                <label for="cison_exam_title">Title <span>*</span></label>
                <select id="cison_exam_title" name="title" required>
                    <option value="">Select</option>
                    <?php foreach ($titles as $title): ?>
                        <option value="<?php echo esc_attr($title); ?>" <?php selected($values['title'], $title); ?>>
                            <?php echo esc_html($title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                    </div>
            </div>
                        

            <div class="cison-exam-registration__grid cison-exam-registration__grid--three">
                <div>
                    <label for="cison_exam_first_name">First Name <span>*</span></label>
                    <input id="cison_exam_first_name" type="text" name="first_name"
                        value="<?php echo esc_attr($values['first_name']); ?>" required>
                </div>

                <div>
                    <label for="cison_exam_middle_name">Middle Name</label>
                    <input id="cison_exam_middle_name" type="text" name="middle_name"
                        value="<?php echo esc_attr($values['middle_name']); ?>">
                </div>

                <div>
                    <label for="cison_exam_last_name">Last Name <span>*</span></label>
                    <input id="cison_exam_last_name" type="text" name="last_name"
                        value="<?php echo esc_attr($values['last_name']); ?>" required>
                </div>
            </div>

            <div class="cison-exam-registration__grid cison-exam-registration__grid--two">
                <div>
                    <label for="cison_exam_email">Email Address <span>*</span></label>
                    <input id="cison_exam_email" type="email" name="email"
                        value="<?php echo esc_attr($values['email']); ?>" required>
                </div>

                <div>
                    <label for="cison_exam_phone">Phone Number <span>*</span></label>
                    <input id="cison_exam_phone" type="tel" name="phone"
                        value="<?php echo esc_attr($values['phone']); ?>" required>
                </div>
            </div>

            <div class="cison-exam-registration__grid cison-exam-registration__grid--two">
                <div>
                    <label for="cison_exam_gender">Gender</label>
                    <select id="cison_exam_gender" name="gender">
                        <option value="">Select</option>
                        <?php foreach ($genders as $gender): ?>
                            <option value="<?php echo esc_attr($gender); ?>" <?php selected($values['gender'], $gender); ?>>
                                <?php echo esc_html($gender); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="cison_exam_dob">Date of Birth</label>
                    <input id="cison_exam_dob" type="date" name="date_of_birth"
                        value="<?php echo esc_attr($values['date_of_birth']); ?>">
                </div>
            </div>

            <div class="cison-exam-registration__grid cison-exam-registration__grid--two">
                <div>
                    <label for="cison_exam_stage">Examination Stage <span>*</span></label>
                    <select id="cison_exam_stage" name="examination_stage" required>
                        <option value="">Select</option>
                        <?php foreach ($stages as $stage): ?>
                            <option value="<?php echo esc_attr($stage); ?>" <?php selected($values['examination_stage'], $stage); ?>>
                                <?php echo esc_html($stage); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="cison_exam_qualification">Highest Qualification <span>*</span></label>
                    <input id="cison_exam_qualification" type="text" name="highest_qualification"
                        value="<?php echo esc_attr($values['highest_qualification']); ?>" required>
                </div>
            </div>

            <div class="cison-exam-registration__grid cison-exam-registration__grid--two">
                <div>
                    <label for="cison_exam_employer">Current Employer / Organisation</label>
                    <input id="cison_exam_employer" type="text" name="current_employer"
                        value="<?php echo esc_attr($values['current_employer']); ?>">
                </div>

            </div>

            <div class="cison-exam-registration__grid">
                <div>
                    <label for="cison_exam_street">Street Address</label>
                    <input id="cison_exam_street" type="text" name="street"
                        value="<?php echo esc_attr($values['street']); ?>"
                        placeholder="House number and street name">
                </div>
            </div>

            <div class="cison-exam-registration__grid cison-exam-registration__grid--three">
                <div>
                    <label for="cison_exam_city">City</label>
                    <input id="cison_exam_city" type="text" name="city"
                        value="<?php echo esc_attr($values['city']); ?>">
                </div>

                <div>
                    <label for="cison_exam_country">Country <span>*</span></label>
                    <select id="cison_exam_country" name="country" required>
                        <?php foreach ($countries as $code => $label): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($values['country'], $code); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="js-state-select-wrap" style="<?php echo 'NG' === $values['country'] ? '' : 'display:none;'; ?>">
                    <label for="cison_exam_state">State <span>*</span></label>
                    <select id="cison_exam_state" name="state" <?php echo 'NG' === $values['country'] ? 'required' : 'disabled'; ?>>
                        <option value="">Select</option>
                        <?php foreach ($nigerian_states as $state): ?>
                            <option value="<?php echo esc_attr($state); ?>" <?php selected($values['state'], $state); ?>>
                                <?php echo esc_html($state); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="js-state-manual-wrap" style="<?php echo 'NG' === $values['country'] ? 'display:none;' : ''; ?>">
                    <label for="cison_exam_state_manual">State / Region <span>*</span></label>
                    <input id="cison_exam_state_manual" type="text" name="state_manual"
                        value="<?php echo esc_attr($manual_state_value); ?>"
                        <?php echo 'NG' === $values['country'] ? 'disabled' : 'required'; ?>>
                </div>
            </div>

            <div class="cison-exam-registration__grid">
                <div>
                    <label for="cison_exam_notes">Additional Notes</label>
                    <textarea id="cison_exam_notes" name="notes" rows="4"><?php echo esc_textarea($values['notes']); ?></textarea>
                </div>
            </div>

            <button type="submit" name="cison_examination_registration_submit" value="1"
                class="cison-exam-registration__submit">
                Submit Registration
            </button>
        </form>
    </div>

    <style>
        .cison-exam-registration {
            max-width: 920px;
            margin: 0 auto;
            padding: 24px;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
        }

        .cison-exam-registration__header {
            margin-bottom: 24px;
        }

        .cison-exam-registration__header h3 {
            margin: 0 0 8px;
            font-size: 1.8rem;
            color: #0f172a;
        }

        .cison-exam-registration__header p {
            margin: 0;
            color: #475569;
        }

        .cison-exam-registration__alert {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: 600;
        }

        .cison-exam-registration__alert--success {
            background: #dcfce7;
            color: #166534;
        }

        .cison-exam-registration__alert--error {
            background: #fee2e2;
            color: #991b1b;
        }

        .cison-exam-registration__form label {
            display: block;
            margin-bottom: 8px;
            color: #0f172a;
            font-weight: 600;
        }

        .cison-exam-registration__form label span {
            color: #dc2626;
        }

        .cison-exam-registration__help {
            display: block;
            margin-top: 8px;
            color: #475569;
            font-size: 13px;
        }

        .cison-exam-registration__grid {
            display: grid;
            gap: 18px;
            margin-bottom: 18px;
        }

        .cison-exam-registration__grid--two {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .cison-exam-registration__grid--three {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .cison-exam-registration__form input,
        .cison-exam-registration__form select,
        .cison-exam-registration__form textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 15px;
            color: #0f172a;
            background: #fff;
        }

        .cison-exam-registration__submit {
            border: 0;
            border-radius: 999px;
            padding: 14px 22px;
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            background: #0f766e;
            cursor: pointer;
        }

        .cison-exam-registration__submit:hover {
            background: #115e59;
        }

        @media (max-width: 768px) {
            .cison-exam-registration__grid--two,
            .cison-exam-registration__grid--three {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.cison-exam-registration').forEach(function(container) {
                const memberSelect = container.querySelector('[name="is_member"]');
                const memberField = container.querySelector('.js-member-id-field');
                const memberInput = container.querySelector('[name="membership_id"]');
                const countrySelect = container.querySelector('[name="country"]');
                const stateSelectWrap = container.querySelector('.js-state-select-wrap');
                const stateSelect = container.querySelector('[name="state"]');
                const stateManualWrap = container.querySelector('.js-state-manual-wrap');
                const stateManual = container.querySelector('[name="state_manual"]');

                const toggleMemberField = function() {
                    const showMembership = memberSelect && memberSelect.value === 'yes';
                    if (memberField) {
                        memberField.style.display = showMembership ? 'grid' : 'none';
                    }
                    if (memberInput) {
                        memberInput.required = showMembership;
                        if (!showMembership) {
                            memberInput.value = '';
                        }
                    }
                };

                const toggleStateField = function() {
                    const isNigeria = countrySelect && countrySelect.value === 'NG';
                    if (stateSelectWrap) {
                        stateSelectWrap.style.display = isNigeria ? 'block' : 'none';
                    }
                    if (stateManualWrap) {
                        stateManualWrap.style.display = isNigeria ? 'none' : 'block';
                    }
                    if (stateSelect) {
                        stateSelect.disabled = !isNigeria;
                        stateSelect.required = isNigeria;
                        if (!isNigeria) {
                            stateSelect.value = '';
                        }
                    }
                    if (stateManual) {
                        stateManual.disabled = isNigeria;
                        stateManual.required = !isNigeria;
                        if (isNigeria) {
                            stateManual.value = '';
                        }
                    }
                };

                if (memberSelect) {
                    memberSelect.addEventListener('change', toggleMemberField);
                }
                if (countrySelect) {
                    countrySelect.addEventListener('change', toggleStateField);
                }

                toggleMemberField();
                toggleStateField();
            });
        });
    </script>
    <?php
    return ob_get_clean();
}

function cison_examination_submissions_shortcode($atts)
{
    if (!current_user_can('manage_options')) {
        return '<p>You do not have permission to view examination submissions.</p>';
    }

    global $wpdb;
    $table_name = cison_get_examination_registration_table_name();

    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) !== $table_name) {
        return '<p style="color:red;">Error: Examination submissions table not found.</p>';
    }

    $atts = shortcode_atts(array(
        'per_page' => 20,
        'filter' => 'examination_stage',
    ), $atts);

    $allowed_filters = array('examination_stage', 'payment_status', 'application_status', 'payment_platform', 'is_member');
    $filter_key = sanitize_key($atts['filter']);
    if (!in_array($filter_key, $allowed_filters, true)) {
        $filter_key = 'examination_stage';
    }

    $search = isset($_GET['exam_s']) ? sanitize_text_field(wp_unslash($_GET['exam_s'])) : '';
    $filter_param = 'exam_filter_' . $filter_key;
    $filter_value = isset($_GET[$filter_param]) ? sanitize_text_field(wp_unslash($_GET[$filter_param])) : '';
    $paged = isset($_GET['exam_paged']) ? max(1, intval($_GET['exam_paged'])) : 1;
    $per_page = max(1, intval($atts['per_page']));
    $offset = ($paged - 1) * $per_page;

    $where_clauses = array('1=1');
    $query_params = array();

    if ($search) {
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $where_clauses[] = '(reference_number LIKE %s OR membership_id LIKE %s OR first_name LIKE %s OR middle_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
        for ($i = 0; $i < 7; $i++) {
            $query_params[] = $search_term;
        }
    }

    if ($filter_value) {
        $where_clauses[] = "$filter_key = %s";
        $query_params[] = $filter_value;
    }

    $where_sql = implode(' AND ', $where_clauses);
    $count_query = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
    if ($query_params) {
        $count_query = $wpdb->prepare($count_query, $query_params);
    }
    $total_items = (int) $wpdb->get_var($count_query);
    $total_pages = max(1, (int) ceil($total_items / $per_page));

    $query = "
        SELECT *
        FROM $table_name
        WHERE $where_sql
        ORDER BY updated_at DESC, registration_date DESC
        LIMIT %d OFFSET %d";
    $query_params_with_paging = array_merge($query_params, array($per_page, $offset));
    $query = $wpdb->prepare(
        "SELECT *
        FROM $table_name
        WHERE $where_sql
        ORDER BY updated_at DESC, registration_date DESC
        LIMIT %d OFFSET %d",
        $query_params_with_paging
    );
    $results = $wpdb->get_results($query, ARRAY_A);
    $filter_options = $wpdb->get_col("SELECT DISTINCT $filter_key FROM $table_name WHERE $filter_key != '' ORDER BY $filter_key ASC");

    ob_start();
    ?>
    <div class="cison-exam-submissions">
        <div class="cison-exam-submissions__controls">
            <form method="get" class="cison-exam-submissions__search">
                <input type="text" name="exam_s" value="<?php echo esc_attr($search); ?>" placeholder="Search submissions">
                <button type="submit">Search</button>
                <?php if ($search || $filter_value): ?>
                    <a href="<?php echo esc_url(remove_query_arg(array('exam_s', 'exam_paged', $filter_param))); ?>">Clear</a>
                <?php endif; ?>
            </form>

            <form method="get" class="cison-exam-submissions__filter">
                <select name="<?php echo esc_attr($filter_param); ?>" onchange="this.form.submit()">
                    <option value="">All <?php echo esc_html(ucwords(str_replace('_', ' ', $filter_key))); ?></option>
                    <?php foreach ($filter_options as $option): ?>
                        <option value="<?php echo esc_attr($option); ?>" <?php selected($filter_value, $option); ?>>
                            <?php echo esc_html($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="exam_s" value="<?php echo esc_attr($search); ?>">
            </form>
        </div>

        <div class="cison-exam-submissions__table-wrap">
            <table class="cison-exam-submissions__table">
                <thead>
                    <tr>
                        <th>Reference Number</th>
                        <th>Candidate</th>
                        <th>Email</th>
                        <th>Member</th>
                        <th>Examination Stage</th>
                        <th>Payment Platform</th>
                        <th>Application Status</th>
                        <th>Payment Status</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($results): ?>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row['reference_number'] ?: 'N/A'); ?></td>
                                <td>
                                    <strong><?php echo esc_html(cison_get_examination_full_name($row)); ?></strong><br>
                                    <small><?php echo esc_html($row['membership_id'] ?: 'Non-member'); ?></small>
                                </td>
                                <td><?php echo esc_html($row['email']); ?></td>
                                <td><?php echo esc_html('yes' === $row['is_member'] ? 'Yes' : 'No'); ?></td>
                                <td><?php echo esc_html($row['examination_stage']); ?></td>
                                <td><?php echo esc_html($row['payment_platform'] ? cison_get_examination_payment_platform_label($row['payment_platform']) : 'N/A'); ?></td>
                                <td><?php echo cison_render_examination_status_badge($row['application_status'], 'cison-exam-badge--app'); ?></td>
                                <td><?php echo cison_render_examination_status_badge($row['payment_status'], 'cison-exam-badge--payment'); ?></td>
                                <td><?php echo esc_html(date_i18n('M j, Y g:i a', strtotime($row['updated_at'] ?: $row['registration_date']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">No examination submissions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="cison-exam-submissions__pagination">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('exam_paged', '%#%'),
                    'format' => '',
                    'total' => $total_pages,
                    'current' => $paged,
                    'prev_text' => '«',
                    'next_text' => '»',
                    'add_args' => array(
                        'exam_s' => $search,
                        $filter_param => $filter_value,
                    ),
                ));
                ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .cison-exam-submissions {
            margin: 24px 0;
        }

        .cison-exam-submissions__controls {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .cison-exam-submissions__search,
        .cison-exam-submissions__filter {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .cison-exam-submissions input,
        .cison-exam-submissions select,
        .cison-exam-submissions button {
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
        }

        .cison-exam-submissions button {
            border: 0;
            background: #0f766e;
            color: #fff;
            cursor: pointer;
        }

        .cison-exam-submissions__table-wrap {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #fff;
        }

        .cison-exam-submissions__table {
            width: 100%;
            border-collapse: collapse;
        }

        .cison-exam-submissions__table th,
        .cison-exam-submissions__table td {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
        }

        .cison-exam-submissions__table th {
            background: #f8fafc;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .cison-exam-submissions__pagination {
            margin-top: 18px;
        }

        .cison-exam-submissions__pagination .page-numbers {
            display: inline-block;
            margin-right: 8px;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            text-decoration: none;
        }

        .cison-exam-submissions__pagination .current {
            background: #0f766e;
            border-color: #0f766e;
            color: #fff;
        }

        .cison-exam-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
            background: #e2e8f0;
            color: #0f172a;
        }

        .cison-exam-badge--submitted,
        .cison-exam-badge--pending {
            background: #fef3c7;
            color: #92400e;
        }

        .cison-exam-badge--paid,
        .cison-exam-badge--approved {
            background: #dcfce7;
            color: #166534;
        }

        .cison-exam-badge--rejected,
        .cison-exam-badge--failed {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
    <?php

    return ob_get_clean();
}

function cison_examination_tracking_shortcode()
{
    $result = null;
    $feedback_message = '';
    $feedback_type = '';

    if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['cison_examination_tracking_submit'])) {
        if (!isset($_POST['cison_examination_tracking_nonce']) || !wp_verify_nonce($_POST['cison_examination_tracking_nonce'], 'cison_examination_tracking_action')) {
            $feedback_message = 'Security check failed. Please try again.';
            $feedback_type = 'error';
        } else {
            global $wpdb;
            $table_name = cison_get_examination_registration_table_name();
            $reference_number = sanitize_text_field(wp_unslash($_POST['reference_number'] ?? ''));
            $email = strtolower(sanitize_email(wp_unslash($_POST['email'] ?? '')));

            if (empty($reference_number) || empty($email)) {
                $feedback_message = 'Reference number and email address are required.';
                $feedback_type = 'error';
            } else {
                $result = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT *
                        FROM $table_name
                        WHERE reference_number = %s AND email = %s
                        LIMIT 1",
                        $reference_number,
                        $email
                    ),
                    ARRAY_A
                );

                if (!$result) {
                    $feedback_message = 'No submission was found for that reference number and email address.';
                    $feedback_type = 'error';
                }
            }
        }
    }

    ob_start();
    ?>
    <div class="cison-exam-tracking">
        <form method="post" class="cison-exam-tracking__form" novalidate>
            <?php wp_nonce_field('cison_examination_tracking_action', 'cison_examination_tracking_nonce'); ?>
            <div class="cison-exam-tracking__grid">
                <div>
                    <label for="cison_exam_track_reference">Reference Number</label>
                    <input id="cison_exam_track_reference" type="text" name="reference_number"
                        value="<?php echo isset($_POST['reference_number']) ? esc_attr(wp_unslash($_POST['reference_number'])) : ''; ?>"
                        required>
                </div>
                <div>
                    <label for="cison_exam_track_email">Email Address</label>
                    <input id="cison_exam_track_email" type="email" name="email"
                        value="<?php echo isset($_POST['email']) ? esc_attr(wp_unslash($_POST['email'])) : ''; ?>"
                        required>
                </div>
            </div>
            <button type="submit" name="cison_examination_tracking_submit" value="1">Track Submission</button>
        </form>

        <?php if ($feedback_message): ?>
            <div class="cison-exam-tracking__alert cison-exam-tracking__alert--<?php echo esc_attr($feedback_type); ?>">
                <?php echo esc_html($feedback_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($result): ?>
            <div class="cison-exam-tracking__card">
                <h3><?php echo esc_html(cison_get_examination_full_name($result)); ?></h3>
                <p><strong>Reference Number:</strong> <?php echo esc_html($result['reference_number']); ?></p>
                <p><strong>Examination Stage:</strong> <?php echo esc_html($result['examination_stage']); ?></p>
                <p><strong>Payment Platform:</strong> <?php echo esc_html($result['payment_platform'] ? cison_get_examination_payment_platform_label($result['payment_platform']) : 'N/A'); ?></p>
                <p><strong>Application Status:</strong> <?php echo cison_render_examination_status_badge($result['application_status']); ?></p>
                <p><strong>Payment Status:</strong> <?php echo cison_render_examination_status_badge($result['payment_status']); ?></p>
                <p><strong>Submitted:</strong> <?php echo esc_html(date_i18n('M j, Y g:i a', strtotime($result['registration_date']))); ?></p>
                <p><strong>Last Updated:</strong> <?php echo esc_html(date_i18n('M j, Y g:i a', strtotime($result['updated_at'] ?: $result['registration_date']))); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .cison-exam-tracking {
            max-width: 820px;
            margin: 24px auto;
            padding: 24px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #fff;
        }

        .cison-exam-tracking__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .cison-exam-tracking label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .cison-exam-tracking input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
        }

        .cison-exam-tracking button {
            border: 0;
            border-radius: 999px;
            padding: 13px 20px;
            background: #0f766e;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        .cison-exam-tracking__alert {
            margin-top: 16px;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: 600;
        }

        .cison-exam-tracking__alert--error {
            background: #fee2e2;
            color: #991b1b;
        }

        .cison-exam-tracking__card {
            margin-top: 18px;
            padding: 18px;
            border-radius: 14px;
            background: #f8fafc;
        }

        .cison-exam-tracking__card h3 {
            margin-top: 0;
            margin-bottom: 12px;
        }

        @media (max-width: 768px) {
            .cison-exam-tracking__grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php

    return ob_get_clean();
}

add_shortcode('professional_examination_registration', 'cison_examination_registration_form_shortcode');
add_shortcode('mock_examination_registration', 'cison_examination_registration_form_shortcode');
add_shortcode('cison_examination_submissions', 'cison_examination_submissions_shortcode');
add_shortcode('view_examination_submissions', 'cison_examination_submissions_shortcode');
add_shortcode('cison_examination_tracking', 'cison_examination_tracking_shortcode');
