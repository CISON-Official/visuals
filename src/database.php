<?php
function create_nsa_registration_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'nsa_registrations';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        member_id varchar(20) NOT NULL,
        registering_for varchar(50) NOT NULL,
        title varchar(20) NOT NULL,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(20) NOT NULL,
        occupation varchar(100) DEFAULT '',
        organisation varchar(200) DEFAULT '',
        street varchar(200) NOT NULL,
        city varchar(100) NOT NULL,
        state varchar(100) NOT NULL,
        postcode varchar(20) NOT NULL,
        country varchar(2) NOT NULL DEFAULT 'NG',
        gender varchar(30) NOT NULL,
        hear_about varchar(50) DEFAULT '',
        order_id bigint(20) DEFAULT 0,
        payment_status varchar(20) DEFAULT 'pending',
        registration_date datetime DEFAULT CURRENT_TIMESTAMP,
        ip_address varchar(45) DEFAULT '',
        PRIMARY KEY (id),
        KEY member_id (member_id),
        KEY email (email),
        KEY order_id (order_id),
        KEY registration_date (registration_date)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function create_examination_registration_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'cison_examination_registrations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        reference_number varchar(40) DEFAULT '',
        is_member varchar(3) NOT NULL DEFAULT 'no',
        membership_id varchar(30) DEFAULT '',
        middle_name varchar(100) DEFAULT '',
        title varchar(20) NOT NULL,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(30) NOT NULL,
        gender varchar(30) DEFAULT '',
        date_of_birth date NULL,
        examination_stage varchar(100) NOT NULL,
        highest_qualification varchar(150) DEFAULT '',
        current_employer varchar(150) DEFAULT '',
        street varchar(200) DEFAULT '',
        city varchar(100) DEFAULT '',
        state varchar(100) NOT NULL,
        country varchar(2) NOT NULL DEFAULT 'NG',
        payment_platform varchar(50) DEFAULT '',
        payment_status varchar(30) DEFAULT 'pending',
        application_status varchar(30) DEFAULT 'submitted',
        notes text NULL,
        registration_date datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        ip_address varchar(45) DEFAULT '',
        PRIMARY KEY (id),
        KEY email (email),
        KEY reference_number (reference_number),
        KEY examination_stage (examination_stage),
        KEY application_status (application_status),
        KEY registration_date (registration_date)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function alter_nsa_registration_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'nsa_registrations';
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

    if ($table_exists !== $table_name) {
        return;
    }

    $columns_data = $wpdb->get_results("DESC $table_name");
    $columns = wp_list_pluck($columns_data, 'Field');
    $alter_clauses = array();

    if (!in_array('middle_name', $columns, true)) {
        $alter_clauses[] = "ADD COLUMN middle_name varchar(100) DEFAULT '' AFTER first_name";
    }

    $who_paid_col = null;
    foreach ($columns_data as $col) {
        if ($col->Field === 'who_paid') {
            $who_paid_col = $col;
            break;
        }
    }

    if (!in_array('who_paid', $columns, true)) {
        $alter_clauses[] = "ADD COLUMN who_paid varchar(700) DEFAULT 'self' AFTER payment_status";
    } elseif ($who_paid_col->Type !== 'varchar(2000)') {
        // Column exists but size/type is different (e.g., varchar(50))
        $alter_clauses[] = "MODIFY COLUMN who_paid varchar(2000) DEFAULT 'self'";
    }

    if ($alter_clauses) {
        $wpdb->query("ALTER TABLE $table_name " . implode(', ', $alter_clauses));
    }
}


function alter_examination_registration_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'cison_examination_registrations';
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

    if ($table_exists !== $table_name) {
        return;
    }

    $columns = $wpdb->get_col("DESC $table_name", 0);
    $alter_clauses = array();

    if (!in_array('reference_number', $columns, true)) {
        $alter_clauses[] = "ADD COLUMN reference_number varchar(40) DEFAULT '' AFTER id";
    }

    if (!in_array('is_member', $columns, true)) {
        $alter_clauses[] = "ADD COLUMN is_member varchar(3) NOT NULL DEFAULT 'no' AFTER reference_number";
    }

    if (!in_array('middle_name', $columns, true)) {
        $alter_clauses[] = "ADD COLUMN middle_name varchar(100) DEFAULT '' AFTER first_name";
    }

    if (!in_array('payment_platform', $columns, true)) {
        $alter_clauses[] = "ADD COLUMN payment_platform varchar(50) DEFAULT '' AFTER country";
    }

    if (!in_array('payment_status', $columns, true)) {
        $alter_clauses[] = "ADD COLUMN payment_status varchar(30) DEFAULT 'pending' AFTER payment_platform";
    }

    if (!in_array('application_status', $columns, true)) {
        $alter_clauses[] = "ADD COLUMN application_status varchar(30) DEFAULT 'submitted' AFTER payment_status";
    }

    if (!in_array('updated_at', $columns, true)) {
        $alter_clauses[] = "ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP AFTER registration_date";
    }

    if ($alter_clauses) {
        $wpdb->query("ALTER TABLE $table_name " . implode(', ', $alter_clauses));
    }

    $reference_index = $wpdb->get_var("SHOW INDEX FROM $table_name WHERE Key_name = 'reference_number'");
    if (!$reference_index) {
        $wpdb->query("ALTER TABLE $table_name ADD KEY reference_number (reference_number)");
    }

    $application_status_index = $wpdb->get_var("SHOW INDEX FROM $table_name WHERE Key_name = 'application_status'");
    if (!$application_status_index) {
        $wpdb->query("ALTER TABLE $table_name ADD KEY application_status (application_status)");
    }
}

function create_databases()
{
    global $wpdb;

    create_nsa_registration_table();
    alter_nsa_registration_table();
    create_examination_registration_table();
    alter_examination_registration_table();
}
