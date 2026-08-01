<?php

// register_activation_hook(__FILE__, 'evp_initialize_election_database');

// function evp_initialize_election_database()
// {
//     global $wpdb;
//     $charset_collate = $wpdb->get_charset_collate();

//     $table_elections = $wpdb->prefix . 'election_entries';
//     $sql_elections = "CREATE TABLE $table_elections (
//         id bigint(20) NOT NULL AUTO_INCREMENT,
//         name varchar(255) NOT NULL,
//         position varchar(255) NOT NULL,
//         created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
//         updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
//         PRIMARY KEY  (id)
//     ) $charset_collate;";

//     $table_candidates = $wpdb->prefix . 'election_candidates';
//     $sql_candidates = "CREATE TABLE $table_candidates (
//         id bigint(20) NOT NULL AUTO_INCREMENT,
//         election_id bigint(20) NOT NULL,
//         name varchar(255) NOT NULL,
//         description text DEFAULT '' NOT NULL,
//         manifesto longtext DEFAULT '' NOT NULL,
//         user_id bigint(20) DEFAULT 0 NOT NULL,
//         PRIMARY KEY  (id),
//         KEY election_link (election_id)
//     ) $charset_collate;";

//     $table_voters = $wpdb->prefix . 'election_voters';
//     $sql_voters = "CREATE TABLE $table_voters (
//         id bigint(20) NOT NULL AUTO_INCREMENT,
//         election_id bigint(20) NOT NULL,
//         candidate_id bigint(20) NOT NULL,
//         name varchar(255) DEFAULT '' NOT NULL,
//         user_id bigint(20) DEFAULT 0 NOT NULL,
//         ip_address varchar(45) NOT NULL,
//         created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
//         updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
//         PRIMARY KEY  (id),
//         UNIQUE KEY unique_voter (election_id, user_id, ip_address)
//     ) $charset_collate;";

//     require_once ABSPATH . 'wp-admin/includes/upgrade.php';
//     dbDelta($sql_elections);
//     dbDelta($sql_candidates);
//     dbDelta($sql_voters);
// }