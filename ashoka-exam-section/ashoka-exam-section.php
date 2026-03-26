<?php
/**
 * Plugin Name: Ashoka Exam Section
 * Plugin URI:  https://github.com/srinivasa-jpg/cloudeai
 * Description: Manages Students, Subjects, Faculty, Branches, and Exam Marks from the WordPress admin side using custom database tables.
 * Version:     1.0.0
 * Author:      Ashoka Institute
 * License:     GPL-2.0+
 * Text Domain: ashoka-exam-section
 * Requires at least: 5.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ASHOKA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASHOKA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ASHOKA_PLUGIN_VERSION', '1.0.0' );

// Load includes.
require_once ASHOKA_PLUGIN_DIR . 'includes/class-ashoka-db.php';
require_once ASHOKA_PLUGIN_DIR . 'includes/class-ashoka-roles.php';
require_once ASHOKA_PLUGIN_DIR . 'includes/class-ashoka-branches.php';
require_once ASHOKA_PLUGIN_DIR . 'includes/class-ashoka-students.php';
require_once ASHOKA_PLUGIN_DIR . 'includes/class-ashoka-subjects.php';
require_once ASHOKA_PLUGIN_DIR . 'includes/class-ashoka-faculty.php';
require_once ASHOKA_PLUGIN_DIR . 'includes/class-ashoka-mapping.php';
require_once ASHOKA_PLUGIN_DIR . 'includes/class-ashoka-internal-marks.php';
require_once ASHOKA_PLUGIN_DIR . 'includes/class-ashoka-external-marks.php';
require_once ASHOKA_PLUGIN_DIR . 'includes/class-ashoka-faculty-marks.php';

// Bootstrap AJAX handlers on plugins_loaded so WordPress is fully initialised.
add_action( 'plugins_loaded', 'ashoka_init_hooks' );
function ashoka_init_hooks() {
    Ashoka_Faculty_Marks::init();
}

// Activation hook.
register_activation_hook( __FILE__, 'ashoka_activate' );
function ashoka_activate() {
    Ashoka_DB::create_tables();
    Ashoka_Roles::create_roles();
    // Store the DB version so we can run upgrades later.
    update_option( 'ashoka_exam_db_version', ASHOKA_PLUGIN_VERSION );
}

// Deactivation hook.
register_deactivation_hook( __FILE__, 'ashoka_deactivate' );
function ashoka_deactivate() {
    // Roles are kept on deactivation; removed only on uninstall.
}

// Enqueue admin assets.
add_action( 'admin_enqueue_scripts', 'ashoka_enqueue_admin_assets' );
function ashoka_enqueue_admin_assets( $hook ) {
    // Only load on our plugin pages.
    if ( strpos( $hook, 'ashoka' ) === false ) {
        return;
    }
    wp_enqueue_style(
        'ashoka-admin-style',
        ASHOKA_PLUGIN_URL . 'assets/css/admin-style.css',
        array(),
        ASHOKA_PLUGIN_VERSION
    );
    wp_enqueue_script(
        'ashoka-admin-script',
        ASHOKA_PLUGIN_URL . 'assets/js/admin-script.js',
        array( 'jquery' ),
        ASHOKA_PLUGIN_VERSION,
        true
    );
    wp_localize_script(
        'ashoka-admin-script',
        'ashokaAjax',
        array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ashoka_ajax_nonce' ),
        )
    );
}

// Register admin menus.
add_action( 'admin_menu', 'ashoka_register_menus' );
function ashoka_register_menus() {
    $cap = ashoka_get_min_capability();

    add_menu_page(
        __( 'Ashoka Exam', 'ashoka-exam-section' ),
        __( 'Ashoka Exam', 'ashoka-exam-section' ),
        $cap,
        'ashoka-exam',
        'ashoka_dashboard_page',
        'dashicons-welcome-learn-more',
        30
    );

    add_submenu_page(
        'ashoka-exam',
        __( 'Dashboard', 'ashoka-exam-section' ),
        __( 'Dashboard', 'ashoka-exam-section' ),
        $cap,
        'ashoka-exam',
        'ashoka_dashboard_page'
    );

    add_submenu_page(
        'ashoka-exam',
        __( 'Students', 'ashoka-exam-section' ),
        __( 'Students', 'ashoka-exam-section' ),
        $cap,
        'ashoka-students',
        array( 'Ashoka_Students', 'render_page' )
    );

    add_submenu_page(
        'ashoka-exam',
        __( 'Subjects', 'ashoka-exam-section' ),
        __( 'Subjects', 'ashoka-exam-section' ),
        'ashoka_manage_subjects',
        'ashoka-subjects',
        array( 'Ashoka_Subjects', 'render_page' )
    );

    add_submenu_page(
        'ashoka-exam',
        __( 'Faculty', 'ashoka-exam-section' ),
        __( 'Faculty', 'ashoka-exam-section' ),
        'ashoka_manage_faculty',
        'ashoka-faculty',
        array( 'Ashoka_Faculty', 'render_page' )
    );

    add_submenu_page(
        'ashoka-exam',
        __( 'Branches', 'ashoka-exam-section' ),
        __( 'Branches', 'ashoka-exam-section' ),
        'ashoka_manage_branches',
        'ashoka-branches',
        array( 'Ashoka_Branches', 'render_page' )
    );

    add_submenu_page(
        'ashoka-exam',
        __( 'Faculty-Subject Mapping', 'ashoka-exam-section' ),
        __( 'Faculty-Subject Mapping', 'ashoka-exam-section' ),
        'ashoka_manage_mapping',
        'ashoka-mapping',
        array( 'Ashoka_Mapping', 'render_page' )
    );

    add_submenu_page(
        'ashoka-exam',
        __( 'Internal Marks', 'ashoka-exam-section' ),
        __( 'Internal Marks', 'ashoka-exam-section' ),
        'ashoka_manage_marks',
        'ashoka-internal-marks',
        array( 'Ashoka_Internal_Marks', 'render_page' )
    );

    add_submenu_page(
        'ashoka-exam',
        __( 'External Marks', 'ashoka-exam-section' ),
        __( 'External Marks', 'ashoka-exam-section' ),
        'ashoka_manage_marks',
        'ashoka-external-marks',
        array( 'Ashoka_External_Marks', 'render_page' )
    );

    add_submenu_page(
        'ashoka-exam',
        __( 'Enter Marks', 'ashoka-exam-section' ),
        __( 'Enter Marks', 'ashoka-exam-section' ),
        'ashoka_enter_marks',
        'ashoka-enter-marks',
        array( 'Ashoka_Faculty_Marks', 'render_page' )
    );
}

/**
 * Return the minimum capability to access the top-level menu.
 */
function ashoka_get_min_capability() {
    if ( current_user_can( 'manage_options' ) ) {
        return 'manage_options';
    }
    if ( current_user_can( 'ashoka_manage_students' ) ) {
        return 'ashoka_manage_students';
    }
    if ( current_user_can( 'ashoka_view_students' ) ) {
        return 'ashoka_view_students';
    }
    return 'manage_options';
}

/**
 * Dashboard page callback.
 */
function ashoka_dashboard_page() {
    echo '<div class="wrap"><h1>' . esc_html__( 'Ashoka Exam Section – Dashboard', 'ashoka-exam-section' ) . '</h1>';
    echo '<p>' . esc_html__( 'Welcome to the Ashoka Exam Section plugin. Use the submenus on the left to manage students, subjects, faculty, branches, marks, and more.', 'ashoka-exam-section' ) . '</p>';
    echo '</div>';
}
