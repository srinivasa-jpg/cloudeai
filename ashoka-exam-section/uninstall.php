<?php
/**
 * Uninstall script – runs when the plugin is deleted from the WordPress admin.
 * Drops all custom tables and removes custom roles/capabilities.
 *
 * @package Ashoka_Exam_Section
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Load the classes needed for cleanup.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ashoka-db.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ashoka-roles.php';

// Drop all custom tables.
Ashoka_DB::drop_tables();

// Remove custom roles and capabilities.
Ashoka_Roles::remove_roles();

// Remove any plugin options (none currently, but good practice).
delete_option( 'ashoka_exam_section_version' );
