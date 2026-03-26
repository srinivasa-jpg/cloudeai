<?php
/**
 * Database table creation and helper methods.
 *
 * @package Ashoka_Exam_Section
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ashoka_DB {

    /**
     * Create all custom tables using dbDelta().
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Students table.
        $sql = "CREATE TABLE {$wpdb->prefix}ashoka_students (
            sno INT AUTO_INCREMENT PRIMARY KEY,
            htno VARCHAR(50) NOT NULL UNIQUE,
            student_name VARCHAR(255) NOT NULL,
            branch VARCHAR(100) DEFAULT '',
            year VARCHAR(20) DEFAULT '',
            sem VARCHAR(10) DEFAULT '',
            section VARCHAR(5) DEFAULT '',
            admn_no VARCHAR(50) DEFAULT '',
            caste_category VARCHAR(50) DEFAULT '',
            admn_dt DATE DEFAULT NULL,
            year_of_completion VARCHAR(10) DEFAULT '',
            dob DATE DEFAULT NULL,
            gender TINYINT DEFAULT 0,
            father_name VARCHAR(255) DEFAULT '',
            mother_name VARCHAR(255) DEFAULT '',
            parent_mobile VARCHAR(15) DEFAULT '',
            student_mobile VARCHAR(15) DEFAULT '',
            email VARCHAR(255) DEFAULT '',
            dt_of_leaving DATE DEFAULT NULL,
            discon_date DATE DEFAULT NULL,
            roll_section_no VARCHAR(50) DEFAULT ''
        ) $charset_collate;";
        dbDelta( $sql );

        // Subjects table.
        $sql = "CREATE TABLE {$wpdb->prefix}ashoka_subjects (
            sno INT AUTO_INCREMENT PRIMARY KEY,
            sub_name VARCHAR(255) NOT NULL,
            sub_code VARCHAR(50) NOT NULL UNIQUE,
            credits DECIMAL(4,2) DEFAULT 0.00
        ) $charset_collate;";
        dbDelta( $sql );

        // Faculty table.
        $sql = "CREATE TABLE {$wpdb->prefix}ashoka_faculty (
            sno INT AUTO_INCREMENT PRIMARY KEY,
            faculty_name VARCHAR(255) NOT NULL,
            emp_code VARCHAR(50) NOT NULL
        ) $charset_collate;";
        dbDelta( $sql );

        // Branches table.
        $sql = "CREATE TABLE {$wpdb->prefix}ashoka_branches (
            sno INT AUTO_INCREMENT PRIMARY KEY,
            branch_name VARCHAR(100) NOT NULL UNIQUE
        ) $charset_collate;";
        dbDelta( $sql );

        // Faculty-Subject Mapping table.
        $sql = "CREATE TABLE {$wpdb->prefix}ashoka_faculty_subject_mapping (
            sno INT AUTO_INCREMENT PRIMARY KEY,
            faculty_id INT NOT NULL,
            sub_code VARCHAR(50) NOT NULL,
            branch VARCHAR(100) NOT NULL,
            year VARCHAR(20) NOT NULL,
            sem VARCHAR(10) NOT NULL
        ) $charset_collate;";
        dbDelta( $sql );

        // Internal Marks table.
        $sql = "CREATE TABLE {$wpdb->prefix}ashoka_internal_marks (
            sno INT AUTO_INCREMENT PRIMARY KEY,
            student_htno VARCHAR(50) NOT NULL,
            sub_code VARCHAR(50) NOT NULL,
            branch VARCHAR(100) NOT NULL,
            year VARCHAR(20) NOT NULL,
            sem VARCHAR(10) NOT NULL,
            mid1_marks DECIMAL(5,2) DEFAULT 0.00,
            mid2_marks DECIMAL(5,2) DEFAULT 0.00,
            total_marks DECIMAL(5,2) DEFAULT 0.00
        ) $charset_collate;";
        dbDelta( $sql );

        // External Marks table.
        $sql = "CREATE TABLE {$wpdb->prefix}ashoka_external_marks (
            sno INT AUTO_INCREMENT PRIMARY KEY,
            student_htno VARCHAR(50) NOT NULL,
            sub_code VARCHAR(50) NOT NULL,
            branch VARCHAR(100) NOT NULL,
            year VARCHAR(20) NOT NULL,
            sem VARCHAR(10) NOT NULL,
            external_marks DECIMAL(5,2) DEFAULT 0.00,
            exam_type VARCHAR(10) DEFAULT 'subject'
        ) $charset_collate;";
        dbDelta( $sql );
    }

    /**
     * Drop all custom tables.
     */
    public static function drop_tables() {
        global $wpdb;
        $tables = array(
            "{$wpdb->prefix}ashoka_external_marks",
            "{$wpdb->prefix}ashoka_internal_marks",
            "{$wpdb->prefix}ashoka_faculty_subject_mapping",
            "{$wpdb->prefix}ashoka_branches",
            "{$wpdb->prefix}ashoka_faculty",
            "{$wpdb->prefix}ashoka_subjects",
            "{$wpdb->prefix}ashoka_students",
        );
        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS `$table`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
    }

    // --------------- Helper query methods ---------------

    public static function get_branches() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ashoka_branches ORDER BY branch_name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public static function get_subjects() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ashoka_subjects ORDER BY sub_name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public static function get_faculty() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ashoka_faculty ORDER BY faculty_name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Calculate internal marks total.
     *
     * Formula: MAX(mid1, mid2) * 0.8 + MIN(mid1, mid2) * 0.2
     */
    public static function calc_internal_total( $mid1, $mid2 ) {
        $mid1 = (float) $mid1;
        $mid2 = (float) $mid2;
        return round( max( $mid1, $mid2 ) * 0.8 + min( $mid1, $mid2 ) * 0.2, 2 );
    }
}
