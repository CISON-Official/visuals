<?php
function create_nsa_registration_table() {
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

function create_databases() {
    global $wpdb;
    $conference = $wpdb->prefix . 'nsa_registrations';

    if ($wpdb->get_var("SHOW TABLES LIKE '$conference'") !== $conference) {
        create_nsa_registration_table();
    };
}