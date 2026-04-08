<?php
/**
 * Plugin Name: NSA Bulk Registration System
 * Description: Complete bulk attendee registration with WooCommerce integration - Global Registration Options
 * Version: 2.1
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ============================================================
// 1. ENQUEUE SCRIPTS AND STYLES
// ============================================================
function nsa_enqueue_scripts() {
    if (!is_page() && !has_shortcode(get_post()->post_content, 'nsa_registration_form')) {
        return;
    }

    // Enqueue Bootstrap (optional - you can use your own)
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true);
    
    // Localize script with AJAX URL and nonce
    wp_localize_script('jquery', 'nsa_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('nsa_registration_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'nsa_enqueue_scripts');

// ============================================================
// 2. ADD TO CART HANDLER (with quantity support)
// ============================================================
function nsa_add_to_cart_handler() {
    // Verify nonce
    if (!check_ajax_referer('nsa_registration_nonce', 'nonce', false)) {
        wp_send_json_error('Security verification failed');
    }

    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = max(1, intval($_POST['quantity'] ?? 1));

    if (!$product_id) {
        wp_send_json_error('No product ID provided');
    }

    if (!class_exists('WooCommerce') || !WC()) {
        wp_send_json_error('WooCommerce is not available');
    }

    if (!WC()->cart) {
        WC()->initialize_cart();
    }

    $product = wc_get_product($product_id);
    if (!$product || !$product->exists()) {
        wp_send_json_error('Product not found');
    }

    // Empty cart before adding new items
    WC()->cart->empty_cart();
    
    // Add product to cart
    $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity);

    if ($cart_item_key) {
        WC()->cart->calculate_totals();
        wp_send_json_success(array(
            'message' => 'Product added to cart successfully',
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_total' => WC()->cart->get_cart_total()
        ));
    } else {
        wp_send_json_error('Failed to add product to cart');
    }
}
add_action('wp_ajax_nsa_add_to_cart', 'nsa_add_to_cart_handler');
add_action('wp_ajax_nopriv_nsa_add_to_cart', 'nsa_add_to_cart_handler');

// ============================================================
// 3. CLEAR CART HANDLER
// ============================================================
function nsa_clear_cart_handler() {
    check_ajax_referer('nsa_registration_nonce', 'nonce');
    
    if (!class_exists('WooCommerce') || !WC()) {
        wp_send_json_error('WooCommerce not available');
    }
    
    if (WC()->cart) {
        WC()->cart->empty_cart();
        wp_send_json_success('Cart cleared successfully');
    } else {
        wp_send_json_error('Cart not available');
    }
}
add_action('wp_ajax_nsa_clear_cart', 'nsa_clear_cart_handler');
add_action('wp_ajax_nopriv_nsa_clear_cart', 'nsa_clear_cart_handler');

// ============================================================
// 4. LOAD CHECKOUT IN MODAL
// ============================================================
function nsa_load_checkout_handler() {
    check_ajax_referer('nsa_registration_nonce', 'nonce');
    
    if (!class_exists('WooCommerce') || !WC()) {
        wp_send_json_error('WooCommerce not available');
    }
    
    if (WC()->cart->is_empty()) {
        wp_send_json_error('Cart is empty. Please add products first.');
    }
    
    ob_start();
    
    // Load WooCommerce checkout template
    if (function_exists('woocommerce_checkout')) {
        woocommerce_checkout();
    } else {
        echo do_shortcode('[woocommerce_checkout]');
    }
    
    $html = ob_get_clean();
    
    wp_send_json_success(array(
        'html' => $html,
        'cart_total' => WC()->cart->get_cart_total()
    ));
}
add_action('wp_ajax_nsa_load_checkout', 'nsa_load_checkout_handler');
add_action('wp_ajax_nopriv_nsa_load_checkout', 'nsa_load_checkout_handler');

// ============================================================
// 5. SAVE BULK REGISTRATIONS (with global options and no address)
// ============================================================
function nsa_save_bulk_registrations() {
    // Verify nonce
    if (!check_ajax_referer('nsa_registration_nonce', 'nonce', false)) {
        wp_send_json_error('Security verification failed');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'nsa_registrations';

    // Validate organization details
    $org_name = sanitize_text_field($_POST['org_name'] ?? '');
    $org_email = sanitize_email($_POST['org_email'] ?? '');
    $org_phone = sanitize_text_field($_POST['org_phone'] ?? '');

    if (empty($org_name)) {
        wp_send_json_error('Organization name is required');
    }
    
    if (empty($org_email) || !is_email($org_email)) {
        wp_send_json_error('Valid organization email is required');
    }

    // Get global registration options
    $global_options = array(
        'workshop' => isset($_POST['global_workshop']) ? sanitize_text_field($_POST['global_workshop']) : 'no',
        'conference_type' => sanitize_text_field($_POST['global_conference_type'] ?? 'none')
    );

    // Build registering_for string based on global options
    $registering_parts = array();
    if ($global_options['workshop'] === 'yes') {
        $registering_parts[] = 'workshop';
    }
    if ($global_options['conference_type'] === 'on-site') {
        $registering_parts[] = 'conference';
    } elseif ($global_options['conference_type'] === 'virtual') {
        $registering_parts[] = 'virtual';
    }
    
    $global_registering_for = implode(', ', $registering_parts);
    
    if (empty($global_registering_for)) {
        wp_send_json_error('Please select at least one registration option (Workshop or Conference)');
    }

    // Get global "How did you hear about this event?"
    $global_hear_about = sanitize_text_field($_POST['global_hear_about'] ?? '');

    // Format who_paid as "OrgName|orgemail"
    $who_paid = $org_name . '|' . $org_email;

    // Get attendees data
    $attendees_raw = stripslashes($_POST['attendees'] ?? '[]');
    $attendees = json_decode($attendees_raw, true);

    if (!is_array($attendees) || empty($attendees)) {
        wp_send_json_error('No attendees data provided');
    }

    // Required fields for each attendee (address fields removed)
    $required_fields = [
        'title', 'first_name', 'last_name', 'email', 'phone', 'gender'
    ];
    
    $inserted_ids = [];
    $errors = [];
    $ip_address = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');

    foreach ($attendees as $index => $attendee) {
        $attendee_num = $index + 1;
        
        // Sanitize all fields
        $data = array(
            'member_id' => sanitize_text_field($attendee['member_id'] ?? ''),
            'registering_for' => $global_registering_for, // Use global registration options
            'title' => sanitize_text_field($attendee['title'] ?? ''),
            'first_name' => sanitize_text_field($attendee['first_name'] ?? ''),
            'middle_name' => sanitize_text_field($attendee['middle_name'] ?? ''),
            'last_name' => sanitize_text_field($attendee['last_name'] ?? ''),
            'email' => sanitize_email($attendee['email'] ?? ''),
            'phone' => sanitize_text_field($attendee['phone'] ?? ''),
            'occupation' => sanitize_text_field($attendee['occupation'] ?? ''),
            'organisation' => $org_name,
            'street' => '', // Empty string - address removed
            'city' => '', // Empty string - address removed
            'state' => '', // Empty string - address removed
            'postcode' => '', // Empty string - address removed
            'country' => 'NG', // Default country
            'gender' => sanitize_text_field($attendee['gender'] ?? ''),
            'hear_about' => $global_hear_about, // Use global value
            'payment_status' => 'pending',
            'who_paid' => $who_paid,
            'ip_address' => $ip_address,
        );

        // Validate required fields
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = "Attendee {$attendee_num}: missing required field '{$field}'";
            }
        }

        // Validate email format
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors[] = "Attendee {$attendee_num}: invalid email address";
        }

        // Insert if no errors
        if (empty($errors)) {
            $result = $wpdb->insert($table_name, $data);
            
            if ($result !== false) {
                $inserted_ids[] = $wpdb->insert_id;
            } else {
                $errors[] = "Attendee {$attendee_num}: database error - " . $wpdb->last_error;
            }
        }
    }

    // Return response
    if (!empty($errors)) {
        wp_send_json_error(array(
            'message' => 'Registration validation failed',
            'errors' => $errors
        ));
    } else {
        // Store registration IDs in session for later linking
        if (WC()->session) {
            WC()->session->set('nsa_registration_ids', json_encode($inserted_ids));
        }
        
        wp_send_json_success(array(
            'message' => count($inserted_ids) . ' registration(s) saved successfully',
            'registration_ids' => $inserted_ids,
            'count' => count($inserted_ids),
            'registering_for' => $global_registering_for
        ));
    }
}
add_action('wp_ajax_nsa_save_registrations', 'nsa_save_bulk_registrations');
add_action('wp_ajax_nopriv_nsa_save_registrations', 'nsa_save_bulk_registrations');

// ============================================================
// 6. LINK REGISTRATIONS TO ORDER AFTER PAYMENT
// ============================================================
function nsa_link_registrations_to_order($order_id) {
    if (!WC()->session) {
        return;
    }
    
    $ids_json = WC()->session->get('nsa_registration_ids');
    if (!$ids_json) {
        return;
    }
    
    $ids = json_decode($ids_json, true);
    if (!is_array($ids) || empty($ids)) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'nsa_registrations';
    
    foreach ($ids as $reg_id) {
        $wpdb->update(
            $table_name,
            array(
                'order_id' => $order_id,
                'payment_status' => 'paid'
            ),
            array('id' => intval($reg_id)),
            array('%d', '%s'),
            array('%d')
        );
    }
    
    // Clear session data
    WC()->session->__unset('nsa_registration_ids');
}
add_action('woocommerce_payment_complete', 'nsa_link_registrations_to_order');
add_action('woocommerce_order_status_completed', 'nsa_link_registrations_to_order');

// ============================================================
// 7. SHORTCODE FOR REGISTRATION FORM (No Address, Global Hear About)
// ============================================================
function nsa_registration_form_shortcode() {
    ob_start();
    ?>
    <div class="nsa-registration-container" id="nsaRegistrationApp">
        <!-- Step Indicator -->
        <div class="nsa-steps">
            <div class="nsa-step active" data-step="1">
                <div class="nsa-step-number">1</div>
                <div class="nsa-step-label">Registration Options</div>
            </div>
            <div class="nsa-step-line"></div>
            <div class="nsa-step" data-step="2">
                <div class="nsa-step-number">2</div>
                <div class="nsa-step-label">Organization Details</div>
            </div>
            <div class="nsa-step-line"></div>
            <div class="nsa-step" data-step="3">
                <div class="nsa-step-number">3</div>
                <div class="nsa-step-label">Attendee Details</div>
            </div>
            <div class="nsa-step-line"></div>
            <div class="nsa-step" data-step="4">
                <div class="nsa-step-number">4</div>
                <div class="nsa-step-label">Payment</div>
            </div>
        </div>

        <!-- Step 1: Global Registration Options -->
        <div id="nsa-step-1" class="nsa-step-content">
            <div class="nsa-card">
                <div class="nsa-card-header">
                    <h3>🎫 Registration Options (Apply to All Attendees)</h3>
                    <p>Select the registration package that applies to every attendee in this bulk registration</p>
                </div>
                <div class="nsa-card-body">
                    <div class="nsa-form-group">
                        <label class="nsa-label">Pre-Conference Workshop</label>
                        <div class="nsa-radio-group">
                            <label>
                                <input type="radio" name="global_workshop" value="yes"> Yes, include Workshop
                            </label>
                            <label>
                                <input type="radio" name="global_workshop" value="no" checked> No, Workshop only
                            </label>
                        </div>
                        <small class="nsa-help-text">Workshop can be combined with either conference option</small>
                    </div>
                    
                    <div class="nsa-form-group">
                        <label class="nsa-label">Conference Registration <span class="required">*</span></label>
                        <div class="nsa-radio-group">
                            <label>
                                <input type="radio" name="global_conference" value="on-site"> On-Site Conference
                            </label>
                            <label>
                                <input type="radio" name="global_conference" value="virtual"> Virtual Conference
                            </label>
                            <label>
                                <input type="radio" name="global_conference" value="none" checked> No Conference
                            </label>
                        </div>
                        <div class="nsa-error-message" id="confError"></div>
                    </div>
                    
                    <div class="nsa-info-box" id="registrationSummary">
                        <strong>Selected Package:</strong>
                        <span id="packageSummary">No conference selected</span>
                    </div>
                </div>
            </div>
            
            <div class="nsa-buttons">
                <button class="nsa-btn nsa-btn-primary" id="gotoStep2">Continue to Organization Details →</button>
            </div>
        </div>

        <!-- Step 2: Organization Details -->
        <div id="nsa-step-2" class="nsa-step-content" style="display: none;">
            <div class="nsa-card">
                <div class="nsa-card-header">
                    <h3>🏢 Billing & Organization Information</h3>
                    <p>Enter the details of the organization or individual responsible for payment</p>
                </div>
                <div class="nsa-card-body">
                    <div class="nsa-form-group">
                        <label>Organization Name <span class="required">*</span></label>
                        <input type="text" id="org_name" class="nsa-input" placeholder="Enter organization name">
                        <div class="nsa-error-message"></div>
                    </div>
                    
                    <div class="nsa-form-group">
                        <label>Organization Email <span class="required">*</span></label>
                        <input type="email" id="org_email" class="nsa-input" placeholder="finance@organization.com">
                        <div class="nsa-error-message"></div>
                    </div>
                    
                    <div class="nsa-form-group">
                        <label>Organization Phone</label>
                        <input type="tel" id="org_phone" class="nsa-input" placeholder="+1234567890">
                    </div>
                    
                    <div class="nsa-form-group">
                        <label>How did you hear about this event? <span class="required">*</span></label>
                        <select id="global_hear_about" class="nsa-input" required>
                            <option value="">Select an option</option>
                            <option value="Social Media">Social Media</option>
                            <option value="Google Search">Google Search</option>
                            <option value="Word of Mouth">Word of Mouth</option>
                            <option value="From a Friend">From a Friend</option>
                            <option value="News Media">News Media</option>
                            <option value="Email Newsletter">Email Newsletter</option>
                            <option value="Conference Website">Conference Website</option>
                            <option value="Professional Network">Professional Network</option>
                            <option value="Other">Other</option>
                        </select>
                        <div class="nsa-error-message"></div>
                    </div>
                    
                    <div class="nsa-who-paid-preview" id="whoPaidPreview" style="display: none;">
                        <strong>Payment recorded as:</strong>
                        <span id="whoPaidValue"></span>
                    </div>
                </div>
            </div>
            
            <div class="nsa-buttons">
                <button class="nsa-btn nsa-btn-secondary" id="backToStep1">← Back</button>
                <button class="nsa-btn nsa-btn-primary" id="gotoStep3">Continue to Attendees →</button>
            </div>
        </div>

        <!-- Step 3: Attendee Details (No address fields) -->
        <div id="nsa-step-3" class="nsa-step-content" style="display: none;">
            <div class="nsa-attendee-header">
                <h3>👥 Attendee Registration</h3>
                <button class="nsa-btn nsa-btn-success" id="addAttendeeBtn">+ Add Attendee</button>
            </div>
            
            <div id="attendeeList"></div>
            
            <div id="emptyAttendeeState" class="nsa-empty-state">
                <div class="nsa-empty-icon">👤</div>
                <p>No attendees added yet</p>
                <p class="nsa-text-muted">Click "Add Attendee" to begin registration</p>
            </div>
            
            <div class="nsa-buttons">
                <button class="nsa-btn nsa-btn-secondary" id="backToStep2">← Back</button>
                <button class="nsa-btn nsa-btn-primary" id="gotoCheckout" disabled>Proceed to Checkout →</button>
            </div>
        </div>

        <!-- Checkout Modal -->
        <div id="checkoutModal" class="nsa-modal" style="display: none;">
            <div class="nsa-modal-content">
                <div class="nsa-modal-header">
                    <h3>Complete Payment</h3>
                    <span class="nsa-modal-close">&times;</span>
                </div>
                <div class="nsa-modal-body" id="checkoutContainer">
                    <div class="nsa-loading">Loading checkout...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendee Card Template (No address fields, no hear_about) -->
    <template id="attendeeCardTemplate">
        <div class="nsa-attendee-card" data-attendee-id="">
            <div class="nsa-attendee-header">
                <div class="nsa-attendee-title">
                    <span class="nsa-attendee-icon">👤</span>
                    <strong>Attendee #<span class="attendee-number"></span></strong>
                    <span class="nsa-attendee-name-preview"></span>
                </div>
                <div class="nsa-attendee-actions">
                    <button class="nsa-btn-icon toggle-card" title="Collapse/Expand">▲</button>
                    <button class="nsa-btn-icon remove-attendee" title="Remove Attendee">✕</button>
                </div>
            </div>
            
            <div class="nsa-attendee-body">
                <div class="nsa-form-row">
                    <div class="nsa-form-group">
                        <label>Title <span class="required">*</span></label>
                        <select class="nsa-input" data-field="title" required>
                            <option value="">Select</option>
                            <option>Mr.</option>
                            <option>Mrs.</option>
                            <option>Ms.</option>
                            <option>Dr.</option>
                            <option>Prof.</option>
                            <option>Engr.</option>
                            <option>Rev.</option>
                            <option>Hon.</option>
                        </select>
                    </div>
                    
                    <div class="nsa-form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" class="nsa-input" data-field="first_name" required>
                    </div>
                    
                    <div class="nsa-form-group">
                        <label>Middle Name</label>
                        <input type="text" class="nsa-input" data-field="middle_name">
                    </div>
                    
                    <div class="nsa-form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" class="nsa-input" data-field="last_name" required>
                    </div>
                </div>
                
                <div class="nsa-form-row">
                    <div class="nsa-form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" class="nsa-input" data-field="email" required>
                    </div>
                    
                    <div class="nsa-form-group">
                        <label>Confirm Email <span class="required">*</span></label>
                        <input type="email" class="nsa-input" data-field="confirm_email" required>
                    </div>
                    
                    <div class="nsa-form-group">
                        <label>Phone <span class="required">*</span></label>
                        <input type="tel" class="nsa-input" data-field="phone" required>
                    </div>
                </div>
                
                <div class="nsa-form-row">
                    <div class="nsa-form-group">
                        <label>Occupation</label>
                        <input type="text" class="nsa-input" data-field="occupation">
                    </div>
                    
                    <div class="nsa-form-group">
                        <label>Gender <span class="required">*</span></label>
                        <select class="nsa-input" data-field="gender" required>
                            <option value="">Select</option>
                            <option>Male</option>
                            <option>Female</option>
                            <option>Prefer Not to Answer</option>
                        </select>
                    </div>
                </div>
                
                <div class="nsa-form-group">
                    <label>CISON Membership</label>
                    <div class="nsa-radio-group">
                        <label><input type="radio" name="is_member" value="yes"> Yes</label>
                        <label><input type="radio" name="is_member" value="no" checked> No</label>
                    </div>
                    <div class="member-id-field" style="display: none;">
                        <input type="text" class="nsa-input" data-field="member_id" placeholder="CISON ID (8 digits)" pattern="[0-9]{8}">
                        <small class="nsa-help-text">Enter 8-digit CISON member ID</small>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <style>
        /* Container Styles */
        .nsa-registration-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        /* Step Indicator */
        .nsa-steps {
            display: flex;
            align-items: center;
            margin-bottom: 40px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            color: white;
        }
        
        .nsa-step {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            opacity: 0.7;
            transition: opacity 0.3s;
        }
        
        .nsa-step.active {
            opacity: 1;
        }
        
        .nsa-step.completed {
            opacity: 1;
        }
        
        .nsa-step-number {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            font-weight: bold;
        }
        
        .nsa-step.active .nsa-step-number {
            background: white;
            color: #667eea;
        }
        
        .nsa-step.completed .nsa-step-number {
            background: #4caf50;
            color: white;
        }
        
        .nsa-step-line {
            width: 50px;
            height: 2px;
            background: rgba(255, 255, 255, 0.3);
            margin: 0 10px;
        }
        
        /* Cards */
        .nsa-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            margin-bottom: 24px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .nsa-card-header {
            padding: 20px 24px;
            background: linear-gradient(135deg, #f5f7fa 0%, #f3f4f6 100%);
            border-bottom: 1px solid #e0e0e0;
        }
        
        .nsa-card-header h3 {
            margin: 0 0 8px 0;
            font-size: 1.25rem;
            color: #333;
        }
        
        .nsa-card-header p {
            margin: 0;
            color: #666;
            font-size: 0.875rem;
        }
        
        .nsa-card-body {
            padding: 24px;
        }
        
        /* Form Elements */
        .nsa-form-group {
            margin-bottom: 20px;
        }
        
        .nsa-form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .nsa-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            color: #333;
        }
        
        .required {
            color: #dc3545;
        }
        
        .nsa-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.15s ease;
        }
        
        .nsa-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .nsa-input.is-invalid {
            border-color: #dc3545;
            background-color: #fff8f8;
        }
        
        .nsa-error-message {
            color: #dc3545;
            font-size: 0.75rem;
            margin-top: 5px;
            display: none;
        }
        
        .nsa-help-text {
            display: block;
            color: #6c757d;
            font-size: 0.75rem;
            margin-top: 4px;
        }
        
        /* Radio and Checkbox Groups */
        .nsa-radio-group {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }
        
        .nsa-radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
            margin-bottom: 0;
        }
        
        /* Info Box */
        .nsa-info-box {
            margin-top: 20px;
            padding: 15px;
            background: #e8f0fe;
            border-left: 4px solid #667eea;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        
        /* Attendee Cards */
        .nsa-attendee-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 20px;
            background: white;
            transition: box-shadow 0.2s;
        }
        
        .nsa-attendee-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .nsa-attendee-card.is-invalid {
            border-color: #dc3545;
            background-color: #fff8f8;
        }
        
        .nsa-attendee-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: #f9fafb;
            cursor: pointer;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .nsa-attendee-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .nsa-attendee-icon {
            font-size: 20px;
        }
        
        .nsa-attendee-name-preview {
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .nsa-attendee-actions {
            display: flex;
            gap: 10px;
        }
        
        .nsa-btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px 10px;
            font-size: 16px;
            border-radius: 4px;
            transition: background 0.15s;
        }
        
        .nsa-btn-icon:hover {
            background: #e5e7eb;
        }
        
        .nsa-attendee-body {
            padding: 20px;
        }
        
        .nsa-attendee-body.collapsed {
            display: none;
        }
        
        /* Buttons */
        .nsa-btn {
            padding: 10px 24px;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
        }
        
        .nsa-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .nsa-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .nsa-btn-primary:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }
        
        .nsa-btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .nsa-btn-secondary:hover {
            background: #5c636a;
        }
        
        .nsa-btn-success {
            background: #10b981;
            color: white;
        }
        
        .nsa-btn-success:hover {
            background: #059669;
        }
        
        .nsa-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 15px;
        }
        
        /* Empty State */
        .nsa-empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #f9fafb;
            border: 2px dashed #d1d5db;
            border-radius: 12px;
        }
        
        .nsa-empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        .nsa-text-muted {
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        /* Who Paid Preview */
        .nsa-who-paid-preview {
            margin-top: 20px;
            padding: 12px;
            background: #e8f0fe;
            border-radius: 6px;
            font-size: 0.875rem;
            color: #1e3a8a;
        }
        
        /* Modal */
        .nsa-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .nsa-modal-content {
            background: white;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            border-radius: 12px;
            overflow: auto;
            animation: slideIn 0.3s;
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .nsa-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .nsa-modal-header h3 {
            margin: 0;
        }
        
        .nsa-modal-close {
            font-size: 28px;
            cursor: pointer;
            color: white;
            transition: transform 0.2s;
        }
        
        .nsa-modal-close:hover {
            transform: scale(1.1);
        }
        
        .nsa-modal-body {
            padding: 20px;
        }
        
        .nsa-loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nsa-form-row {
                grid-template-columns: 1fr;
            }
            
            .nsa-steps {
                flex-direction: column;
                gap: 15px;
            }
            
            .nsa-step-line {
                display: none;
            }
            
            .nsa-buttons {
                flex-direction: column;
            }
            
            .nsa-step {
                width: 100%;
            }
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        let attendeeCounter = 0;
        let currentStep = 1;
        
        // Product IDs mapping based on global selections
        const PRODUCTS = {
            workshop_only: 12816,
            conference_only: 12817,
            virtual_only: 12818,
            workshop_conference: 12670,
            workshop_virtual: 12672
        };
        
        // Step navigation
        function showStep(step) {
            $('#nsa-step-1, #nsa-step-2, #nsa-step-3').hide();
            $(`#nsa-step-${step}`).show();
            
            $('.nsa-step').removeClass('active completed');
            for (let i = 1; i < step; i++) {
                $(`.nsa-step[data-step="${i}"]`).addClass('completed');
            }
            $(`.nsa-step[data-step="${step}"]`).addClass('active');
            
            currentStep = step;
        }
        
        // Update registration summary
        function updateRegistrationSummary() {
            const hasWorkshop = $('input[name="global_workshop"]:checked').val() === 'yes';
            const conferenceType = $('input[name="global_conference"]:checked').val();
            
            let summary = '';
            if (hasWorkshop) {
                summary += '✓ Pre-Conference Workshop<br>';
            }
            
            if (conferenceType === 'on-site') {
                summary += '✓ On-Site Conference';
            } else if (conferenceType === 'virtual') {
                summary += '✓ Virtual Conference';
            } else {
                summary += '✗ No Conference Selected';
            }
            
            $('#packageSummary').html(summary);
        }
        
        // Validate registration options
        function validateRegistrationOptions() {
            const conferenceType = $('input[name="global_conference"]:checked').val();
            if (!conferenceType) {
                $('#confError').text('Please select a conference option').show();
                return false;
            }
            $('#confError').hide();
            return true;
        }
        
        // Add attendee card
        function addAttendee() {
            attendeeCounter++;
            const template = $('#attendeeCardTemplate').html();
            const $card = $(template);
            
            $card.attr('data-attendee-id', attendeeCounter);
            $card.find('.attendee-number').text(attendeeCounter);
            
            // Make radio group unique
            $card.find('input[name="is_member"]').attr('name', `is_member_${attendeeCounter}`);
            
            // Bind events
            bindCardEvents($card);
            
            $('#attendeeList').append($card);
            $('#emptyAttendeeState').hide();
            updateAttendeeCount();
            updateProceedButton();
            
            // Scroll to new card
            $card[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        // Bind card events
        function bindCardEvents($card) {
            // Toggle collapse
            $card.find('.toggle-card').on('click', function() {
                const $body = $card.find('.nsa-attendee-body');
                $body.toggleClass('collapsed');
                $(this).text($body.hasClass('collapsed') ? '▼' : '▲');
            });
            
            $card.find('.nsa-attendee-header').on('click', function(e) {
                if (!$(e.target).closest('.nsa-attendee-actions').length) {
                    $card.find('.toggle-card').click();
                }
            });
            
            // Remove attendee
            $card.find('.remove-attendee').on('click', function() {
                if ($('#attendeeList .nsa-attendee-card').length === 1) {
                    alert('You need at least one attendee. Please add another before removing this one.');
                    return;
                }
                $card.remove();
                updateAttendeeNumbers();
                updateAttendeeCount();
                updateProceedButton();
            });
            
            // Name preview
            $card.find('[data-field="first_name"], [data-field="last_name"]').on('input', function() {
                const firstName = $card.find('[data-field="first_name"]').val();
                const lastName = $card.find('[data-field="last_name"]').val();
                const preview = [firstName, lastName].filter(Boolean).join(' ');
                $card.find('.nsa-attendee-name-preview').text(preview ? `— ${preview}` : '');
            });
            
            // CISON member toggle
            $card.find('input[name^="is_member"]').on('change', function() {
                const $memberField = $card.find('.member-id-field');
                if ($(this).val() === 'yes') {
                    $memberField.slideDown();
                    $memberField.find('input').prop('required', true);
                } else {
                    $memberField.slideUp();
                    $memberField.find('input').prop('required', false).val('');
                }
            });
            
            // Clear validation on input
            $card.find('.nsa-input').on('input change', function() {
                $(this).removeClass('is-invalid');
                $(this).siblings('.nsa-error-message').hide();
            });
        }
        
        function updateAttendeeNumbers() {
            $('#attendeeList .nsa-attendee-card').each(function(index) {
                $(this).find('.attendee-number').text(index + 1);
                $(this).attr('data-attendee-id', index + 1);
            });
        }
        
        function updateAttendeeCount() {
            const count = $('#attendeeList .nsa-attendee-card').length;
            if (count === 0) {
                $('#emptyAttendeeState').show();
            } else {
                $('#emptyAttendeeState').hide();
            }
        }
        
        function updateProceedButton() {
            const hasAttendees = $('#attendeeList .nsa-attendee-card').length > 0;
            $('#gotoCheckout').prop('disabled', !hasAttendees);
        }
        
        // Validate all attendees
        function validateAllAttendees() {
            let isValid = true;
            const errors = [];
            
            $('#attendeeList .nsa-attendee-card').each(function(index) {
                const $card = $(this);
                const num = index + 1;
                let cardValid = true;
                
                // Check required fields
                $card.find('[data-field][required]').each(function() {
                    if (!$(this).val()) {
                        $(this).addClass('is-invalid');
                        cardValid = false;
                    }
                });
                
                // Check email match
                const email = $card.find('[data-field="email"]').val();
                const confirmEmail = $card.find('[data-field="confirm_email"]').val();
                if (email !== confirmEmail) {
                    $card.find('[data-field="confirm_email"]').addClass('is-invalid');
                    errors.push(`Attendee ${num}: Email addresses do not match`);
                    cardValid = false;
                }
                
                // Check email format
                if (email && !isValidEmail(email)) {
                    $card.find('[data-field="email"]').addClass('is-invalid');
                    errors.push(`Attendee ${num}: Invalid email format`);
                    cardValid = false;
                }
                
                if (!cardValid) {
                    $card.addClass('is-invalid');
                    isValid = false;
                } else {
                    $card.removeClass('is-invalid');
                }
            });
            
            return { isValid, errors };
        }
        
        // Collect attendee data
        function collectAttendeeData() {
            const attendees = [];
            
            $('#attendeeList .nsa-attendee-card').each(function() {
                const $card = $(this);
                
                const attendee = {
                    title: $card.find('[data-field="title"]').val(),
                    first_name: $card.find('[data-field="first_name"]').val(),
                    middle_name: $card.find('[data-field="middle_name"]').val(),
                    last_name: $card.find('[data-field="last_name"]').val(),
                    email: $card.find('[data-field="email"]').val(),
                    phone: $card.find('[data-field="phone"]').val(),
                    occupation: $card.find('[data-field="occupation"]').val(),
                    gender: $card.find('[data-field="gender"]').val(),
                    member_id: $card.find('[data-field="member_id"]').val() || ''
                };
                
                attendees.push(attendee);
            });
            
            return attendees;
        }
        
        // Determine product based on global selections
        function determineProduct() {
            const hasWorkshop = $('input[name="global_workshop"]:checked').val() === 'yes';
            const conferenceType = $('input[name="global_conference"]:checked').val();
            
            if (hasWorkshop && conferenceType === 'on-site') return PRODUCTS.workshop_conference;
            if (hasWorkshop && conferenceType === 'virtual') return PRODUCTS.workshop_virtual;
            if (hasWorkshop) return PRODUCTS.workshop_only;
            if (conferenceType === 'on-site') return PRODUCTS.conference_only;
            if (conferenceType === 'virtual') return PRODUCTS.virtual_only;
            return null;
        }
        
        // Process checkout
        async function processCheckout() {
            // Validate registration options
            if (!validateRegistrationOptions()) {
                return;
            }
            
            // Validate organization details
            const orgName = $('#org_name').val().trim();
            const orgEmail = $('#org_email').val().trim();
            const hearAbout = $('#global_hear_about').val();
            
            if (!orgName) {
                $('#org_name').addClass('is-invalid').siblings('.nsa-error-message').text('Organization name is required').show();
                showStep(2);
                return;
            }
            
            if (!orgEmail || !isValidEmail(orgEmail)) {
                $('#org_email').addClass('is-invalid').siblings('.nsa-error-message').text('Valid email is required').show();
                showStep(2);
                return;
            }
            
            if (!hearAbout) {
                $('#global_hear_about').addClass('is-invalid').siblings('.nsa-error-message').text('Please tell us how you heard about this event').show();
                showStep(2);
                return;
            }
            
            // Validate attendees
            const validation = validateAllAttendees();
            if (!validation.isValid) {
                showStep(3);
                alert('Please fix the following errors:\n\n• ' + validation.errors.join('\n• '));
                return;
            }
            
            const attendees = collectAttendeeData();
            const attendeeCount = attendees.length;
            
            // Save registrations
            $('#gotoCheckout').prop('disabled', true).text('Saving...');
            
            try {
                const saveResponse = await $.ajax({
                    url: nsa_ajax.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'nsa_save_registrations',
                        nonce: nsa_ajax.nonce,
                        org_name: orgName,
                        org_email: orgEmail,
                        org_phone: $('#org_phone').val().trim(),
                        attendees: JSON.stringify(attendees),
                        global_workshop: $('input[name="global_workshop"]:checked').val(),
                        global_conference_type: $('input[name="global_conference"]:checked').val(),
                        global_hear_about: hearAbout
                    }
                });
                
                if (!saveResponse.success) {
                    alert('Error saving registrations: ' + (saveResponse.data.message || 'Unknown error'));
                    $('#gotoCheckout').prop('disabled', false).text('Proceed to Checkout →');
                    return;
                }
                
                // Determine product and quantity
                const productId = determineProduct();
                if (!productId) {
                    alert('Unable to determine registration product');
                    $('#gotoCheckout').prop('disabled', false).text('Proceed to Checkout →');
                    return;
                }
                
                // Clear cart and add product
                await $.ajax({
                    url: nsa_ajax.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'nsa_clear_cart',
                        nonce: nsa_ajax.nonce
                    }
                });
                
                const cartResponse = await $.ajax({
                    url: nsa_ajax.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'nsa_add_to_cart',
                        product_id: productId,
                        quantity: attendeeCount,
                        nonce: nsa_ajax.nonce
                    }
                });
                
                if (!cartResponse.success) {
                    alert('Error adding to cart: ' + cartResponse.data);
                    $('#gotoCheckout').prop('disabled', false).text('Proceed to Checkout →');
                    return;
                }
                
                // Load checkout modal
                $('#gotoCheckout').text('Loading checkout...');
                const checkoutResponse = await $.ajax({
                    url: nsa_ajax.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'nsa_load_checkout',
                        nonce: nsa_ajax.nonce
                    }
                });
                
                if (checkoutResponse.success) {
                    $('#checkoutContainer').html(checkoutResponse.data.html);
                    $('#checkoutModal').show();
                    
                    // Update checkout summary
                    const summary = `${orgName} · ${attendeeCount} attendee(s) · Package: ${saveResponse.data.registering_for}`;
                    $('.nsa-modal-header p').remove();
                    $('.nsa-modal-header').append(`<p style="margin: 5px 0 0; font-size: 12px;">${summary}</p>`);
                    
                    // Trigger WooCommerce checkout update
                    setTimeout(() => {
                        $(document.body).trigger('update_checkout');
                    }, 100);
                } else {
                    alert('Error loading checkout: ' + checkoutResponse.data);
                }
                
                $('#gotoCheckout').prop('disabled', false).text('Proceed to Checkout →');
                
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                $('#gotoCheckout').prop('disabled', false).text('Proceed to Checkout →');
            }
        }
        
        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }
        
        // Live preview for who_paid
        function updateWhoPaidPreview() {
            const orgName = $('#org_name').val().trim();
            const orgEmail = $('#org_email').val().trim();
            
            if (orgName || orgEmail) {
                $('#whoPaidValue').text(`${orgName || '?'}|${orgEmail || '?'}`);
                $('#whoPaidPreview').show();
            } else {
                $('#whoPaidPreview').hide();
            }
        }
        
        // Event listeners
        $('#gotoStep2').on('click', () => {
            if (validateRegistrationOptions()) {
                showStep(2);
            }
        });
        
        $('#gotoStep3').on('click', () => {
            const orgName = $('#org_name').val().trim();
            const orgEmail = $('#org_email').val().trim();
            const hearAbout = $('#global_hear_about').val();
            
            if (!orgName) {
                $('#org_name').addClass('is-invalid').siblings('.nsa-error-message').text('Organization name is required').show();
                return;
            }
            if (!orgEmail || !isValidEmail(orgEmail)) {
                $('#org_email').addClass('is-invalid').siblings('.nsa-error-message').text('Valid email is required').show();
                return;
            }
            if (!hearAbout) {
                $('#global_hear_about').addClass('is-invalid').siblings('.nsa-error-message').text('Please tell us how you heard about this event').show();
                return;
            }
            
            if ($('#attendeeList .nsa-attendee-card').length === 0) {
                addAttendee();
            }
            showStep(3);
        });
        
        $('#backToStep1').on('click', () => showStep(1));
        $('#backToStep2').on('click', () => showStep(2));
        $('#gotoCheckout').on('click', processCheckout);
        $('#addAttendeeBtn').on('click', addAttendee);
        
        $('input[name="global_workshop"], input[name="global_conference"]').on('change', updateRegistrationSummary);
        $('#org_name, #org_email').on('input', updateWhoPaidPreview);
        
        // Clear validation on input
        $('#global_hear_about').on('change', function() {
            $(this).removeClass('is-invalid');
            $(this).siblings('.nsa-error-message').hide();
        });
        
        // Modal close
        $('.nsa-modal-close, #checkoutModal').on('click', function(e) {
            if (e.target === this || $(e.target).hasClass('nsa-modal-close')) {
                $('#checkoutModal').hide();
                if (window.paymentCompleted) {
                    location.reload();
                }
            }
        });
        
        // Listen for WooCommerce payment completion
        $(document.body).on('order_received', function() {
            window.paymentCompleted = true;
        });
        
        // Initial setup
        showStep(1);
        updateRegistrationSummary();
        addAttendee(); // Add first attendee by default
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('nsa_registration_form', 'nsa_registration_form_shortcode');