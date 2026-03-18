<?php

function cison_get_examination_form_defaults()
{
    return array(
        'membership_id' => '',
        'title' => '',
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
        'gender' => '',
        'date_of_birth' => '',
        'examination_stage' => '',
        'highest_qualification' => '',
        'current_employer' => '',
        'years_experience' => '',
        'street' => '',
        'city' => '',
        'state' => '',
        'country' => 'NG',
        'notes' => '',
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
            $registration_id = cison_insert_examination_registration(wp_unslash($_POST));

            if (is_wp_error($registration_id)) {
                $feedback_message = $registration_id->get_error_message();
                $feedback_type = 'error';
            } else {
                $feedback_message = 'Your professional examination registration has been submitted successfully.';
                $feedback_type = 'success';
                $values = cison_get_examination_form_defaults();
            }
        }
    }

    $titles = array('Mr', 'Mrs', 'Ms', 'Dr', 'Prof');
    $genders = array('Male', 'Female', 'Prefer Not to Answer');
    $countries = array(
        'NG' => 'Nigeria',
        'GH' => 'Ghana',
        'KE' => 'Kenya',
        'US' => 'United States',
        'GB' => 'United Kingdom',
    );

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
                    <label for="cison_exam_membership_id">Membership ID</label>
                    <input id="cison_exam_membership_id" type="text" name="membership_id"
                        value="<?php echo esc_attr($values['membership_id']); ?>" placeholder="Optional">
                </div>

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

            <div class="cison-exam-registration__grid cison-exam-registration__grid--two">
                <div>
                    <label for="cison_exam_first_name">First Name <span>*</span></label>
                    <input id="cison_exam_first_name" type="text" name="first_name"
                        value="<?php echo esc_attr($values['first_name']); ?>" required>
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
                    <input id="cison_exam_stage" type="text" name="examination_stage"
                        value="<?php echo esc_attr($values['examination_stage']); ?>"
                        placeholder="e.g. Professional Stage I" required>
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

                <div>
                    <label for="cison_exam_experience">Years of Experience</label>
                    <input id="cison_exam_experience" type="text" name="years_experience"
                        value="<?php echo esc_attr($values['years_experience']); ?>">
                </div>
            </div>

            <div class="cison-exam-registration__grid">
                <div>
                    <label for="cison_exam_street">Street Address</label>
                    <input id="cison_exam_street" type="text" name="street"
                        value="<?php echo esc_attr($values['street']); ?>">
                </div>
            </div>

            <div class="cison-exam-registration__grid cison-exam-registration__grid--three">
                <div>
                    <label for="cison_exam_city">City</label>
                    <input id="cison_exam_city" type="text" name="city"
                        value="<?php echo esc_attr($values['city']); ?>">
                </div>

                <div>
                    <label for="cison_exam_state">State <span>*</span></label>
                    <input id="cison_exam_state" type="text" name="state"
                        value="<?php echo esc_attr($values['state']); ?>" required>
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
    <?php
    return ob_get_clean();
}

add_shortcode('professional_examination_registration', 'cison_examination_registration_form_shortcode');
add_shortcode('mock_examination_registration', 'cison_examination_registration_form_shortcode');
